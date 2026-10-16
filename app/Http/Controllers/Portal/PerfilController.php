<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Perfil del paciente: datos personales y obras sociales.
 *
 * Esqueleto del contrato del portal; el contenido real se completa despues.
 */
class PerfilController extends Controller
{
    public function show(): View
    {
        $persona = auth('paciente')->user();

        $coberturas = $persona->planObraSociales()
            ->with('plan.obraSocial')
            ->get();

        $vigentes = $coberturas->whereNull('fechaFinPlanObraSocial');
        $historicas = $coberturas->whereNotNull('fechaFinPlanObraSocial');

        return view('portal.perfil', [
            'vigentes' => $vigentes,
            'historicas' => $historicas,
        ]);
    }
}
