<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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
        $historicas = $coberturas->reject(fn ( $cobertura) => $cobertura->estaVigente());

        return view('portal.perfil', [
            'vigentes' => $vigentes,
            'historicas' => $historicas,
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
            ->with('exito','Listo, quedó como tu obra social predeterminada.');
    }
}
