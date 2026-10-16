<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

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

        $esVigente = fn ($cobertura) =>$cobertura->fechaFinPlanObraSocial === null
            ||  $cobertura->fechaFinPlanObraSocial->isFuture();

        $vigentes = $coberturas->filter($esVigente);
        $historicas = $coberturas->reject($esVigente);

        return view('portal.perfil', [
            'vigentes' => $vigentes,
            'historicas' => $historicas,
        ]);
    }
}
