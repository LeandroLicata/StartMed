<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Http\Requests\Portal\AgregarObraSocialRequest;

/**
 * Perfil del paciente: datos personales y obras sociales.
 */
class PerfilController extends Controller
{
    public function show(): View
    {
        $persona = auth('paciente')->user();

        $coberturas = $persona->planObraSociales()
            ->with('plan.obrasocial')
            ->get();

        $vigentes = $coberturas->filter(fn ($cobertura) => $cobertura->estaVigente());
        $historicas = $coberturas->reject(fn ($cobertura) => $cobertura->estaVigente());

        $planes = Plan::disponiblesParaPaciente();

        return view('portal.perfil', [
            'vigentes' => $vigentes,
            'historicas' => $historicas,
            'planes' => $planes,
        ]);
    }

    public function marcarPredeterminada(int $cobertura): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $elegida = $persona->planObraSociales()->findOrFail($cobertura);

        if (! $elegida->estaVigente()) {
            return back()->with('error', 'Solo podés elegir una obra social vigente.');
        }

        DB::transaction(function () use ($persona, $elegida) {
            $persona->planObraSociales()->update(['esPredeterminadaPlanObraSocial' => false]);
            $elegida->update(['esPredeterminadaPlanObraSocial' => true]);
        });

        return redirect()->route('portal.perfil')
            ->with('exito', 'Listo, quedó como tu obra social predeterminada.');
    }

    public function darDeBaja(int $cobertura): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $baja = $persona->planObraSociales()->findOrFail($cobertura);

        if (! $baja->estaVigente()) {
            return back()->with('error', 'Esa obra social ya estaba dada de baja.');
        }

        $eraPredeterminada = $baja->esPredeterminadaPlanObraSocial;

        $baja->update([
            'fechaFinPlanObraSocial' => now(),
            'esPredeterminadaPlanObraSocial' => false,
        ]);

        if ($eraPredeterminada) {
            return redirect()->route('portal.perfil')
                ->with('aviso', 'Diste de baja tu obra social predeterminada. Elegí otra o agregá una nueva.');
        }

        return redirect()->route('portal.perfil')
            ->with('exito', 'La obra social quedó dada de baja.');
    }

    public function agregar(AgregarObraSocialRequest $request): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $yaLaTiene = $persona->planObraSociales()
            ->where('idPlan', $request->integer('idPlan'))
            ->get()
            ->contains(fn ($cobertura) => $cobertura->estaVigente());

        if ($yaLaTiene) {
            return back()->withInput()->with('error', 'Ya tenés ese plan entre tus obras sociales vigentes.');
        }

        $persona->planObraSociales()->create([
            'idPlan' => $request->integer('idPlan'),
            'nroBeneficiaroPlanObraSocial' => $request->input('nroBeneficiario'),
            'fechaInicioPlanObraSocial' => now(),
        ]);

        return redirect()->route('portal.perfil')
            ->with('exito', 'Agregaste la obra social.');
    }
}