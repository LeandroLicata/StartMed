<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Support\ResumenCirugia;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CirugiaController extends Controller
{
    private const TABS = ['resumen', 'materiales', 'hemoderivados', 'profilaxis', 'autorizacion', 'mensajes'];

    /**
     * Expediente completo de una cirugía: el estado de cada módulo en una
     * sola pantalla, organizado en solapas.
     */
    public function show(Cirugia $cirugia, Request $request): View
    {
        $cirugia->load([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE]);

        $tab = $request->query('tab', 'resumen');

        return view('cirugias.show', [
            'caso' => new ResumenCirugia($cirugia),
            'tabActivo' => in_array($tab, self::TABS, true) ? $tab : 'resumen',
        ]);
    }
}
