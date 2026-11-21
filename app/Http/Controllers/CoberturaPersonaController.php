<?php

namespace App\Http\Controllers;

use App\Http\Requests\Portal\AgregarObraSocialRequest;
use App\Models\Persona;
use App\Models\PlanObraSocial;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Gestion de obras sociales de una Persona desde backoffice (Gestor de
 * quirofano y Administrador). El paciente en su portal solo puede elegir
 * cual de sus coberturas vigentes es la predeterminada.
 */
class CoberturaPersonaController extends Controller
{
    public function store(AgregarObraSocialRequest $request, Persona $persona): RedirectResponse
    {
        $yaLaTiene = $persona->planObraSociales()
            ->vigentes()
            ->where('idPlan', $request->integer('idPlan'))
            ->exists();

        if ($yaLaTiene) {
            return back()->withInput()->with('error', 'La persona ya tiene ese plan entre sus obras sociales vigentes.');
        }

        $nueva = PlanObraSocial::agregar(
            $persona->idPersona,
            $request->integer('idPlan'),
            $request->input('nroBeneficiario'),
        );

        $nueva->load('plan.obrasocial');
        $etiqueta = trim(
            ($nueva->plan?->obrasocial?->nombreObraSocial ?? '').' · '.($nueva->plan?->nombrePlan ?? ''),
            ' ·',
        );

        Auditor::registrar(
            Auditor::ALTA,
            $nueva,
            'Cobertura de '.$persona->nombre_completo.($etiqueta !== '' ? ' ('.$etiqueta.')' : ''),
        );

        return back()->with(
            'exito',
            $nueva->esPredeterminadaPlanObraSocial
                ? 'Obra social agregada y marcada como predeterminada.'
                : 'Obra social agregada correctamente.',
        );
    }

    public function marcarPredeterminada(Persona $persona, int $cobertura): RedirectResponse
    {
        $elegida = $persona->planObraSociales()->with('plan.obrasocial')->findOrFail($cobertura);

        if (! $elegida->estaVigente()) {
            return back()->with('error', 'Solo se puede marcar como predeterminada una obra social vigente.');
        }

        $yaEra = $elegida->esPredeterminadaPlanObraSocial;

        DB::transaction(function () use ($persona, $elegida) {
            $persona->planObraSociales()
                ->whereKeyNot($elegida->getKey())
                ->update(['esPredeterminadaPlanObraSocial' => false]);
            $elegida->update(['esPredeterminadaPlanObraSocial' => true]);
        });

        if (! $yaEra) {
            $etiqueta = trim(
                ($elegida->plan?->obrasocial?->nombreObraSocial ?? '').' · '.($elegida->plan?->nombrePlan ?? ''),
                ' ·',
            );

            Auditor::registrar(
                Auditor::EDICION,
                $elegida,
                'Cobertura predeterminada de '.$persona->nombre_completo.($etiqueta !== '' ? ' ('.$etiqueta.')' : ''),
                ['esPredeterminadaPlanObraSocial' => ['antes' => 'No', 'despues' => 'Sí']],
            );
        }

        return back()->with('exito', 'La obra social quedó marcada como predeterminada.');
    }

    public function darDeBaja(Persona $persona, int $cobertura): RedirectResponse
    {
        $baja = $persona->planObraSociales()->with('plan.obrasocial')->findOrFail($cobertura);

        if (! $baja->estaVigente()) {
            return back()->with('error', 'Esa obra social ya estaba dada de baja.');
        }

        $antes = Auditor::foto($baja);
        $eraPredeterminada = $baja->esPredeterminadaPlanObraSocial;

        $baja->update([
            'fechaFinPlanObraSocial' => now(),
            'esPredeterminadaPlanObraSocial' => false,
        ]);

        Auditor::registrar(
            Auditor::BAJA,
            $baja,
            'Cobertura de '.$persona->nombre_completo,
            Auditor::diferencia($baja, $antes),
        );

        if ($eraPredeterminada) {
            return back()->with('aviso', 'Diste de baja la obra social predeterminada. Elegí otra o agregá una nueva.');
        }

        return back()->with('exito', 'La obra social quedó dada de baja.');
    }
}
