<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ActualizarDatosRequest;
use App\Services\AvisoCambioCoberturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Perfil del paciente: datos personales y eleccion de obra social
 * predeterminada. El alta y la baja de coberturas los gestiona el hospital
 * (Gestor de quirofano o Administrador). El cambio de email esta en
 * CambioEmailController, porque su confirmacion no pide sesion.
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

        return view('portal.perfil', [
            'persona' => $persona->load('grupoSanguineo'),
            'vigentes' => $vigentes,
            'historicas' => $historicas,
        ]);
    }

    /**
     * Guarda solo los datos que el paciente puede editar: teléfonos y donación.
     * Lo de solo lectura (DNI, nacimiento, grupo) queda afuera porque no está
     * en las reglas del request, y validated() devuelve solo lo validado.
     */
    public function actualizarDatos(ActualizarDatosRequest $request): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $persona->update($request->validated());

        return redirect()->route('portal.perfil')
            ->with('exito', 'Tus datos quedaron guardados.');
    }

    public function marcarPredeterminada(int $cobertura): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $elegida = $persona->planObraSociales()->findOrFail($cobertura);

        if (! $elegida->estaVigente()) {
            return back()->with('error', 'Solo podés elegir una obra social vigente.');
        }

        $antes = AvisoCambioCoberturaService::predeterminadaDe($persona->idPersona);

        DB::transaction(function () use ($persona, $elegida) {
            // Se desmarcan solo las demas: si la elegida ya tenia el flag en
            // memoria, update() no ve cambios y no la volveria a marcar.
            $persona->planObraSociales()
                ->whereKeyNot($elegida->getKey())
                ->update(['esPredeterminadaPlanObraSocial' => false]);
            $elegida->update(['esPredeterminadaPlanObraSocial' => true]);
        });

        AvisoCambioCoberturaService::avisarSiCambio($persona->idPersona, $antes);

        return redirect()->route('portal.perfil')
            ->with('exito', 'Listo, quedó como tu obra social predeterminada.');
    }
}
