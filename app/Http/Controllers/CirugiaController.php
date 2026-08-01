<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Support\ResumenCirugia;
use Illuminate\View\View;

class CirugiaController extends Controller
{
    /**
     * Expediente completo de una cirugía: el estado de cada módulo en una
     * sola pantalla, con solapas.
     */
    public function show(Cirugia $cirugia): View
    {
        $cirugia->load([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE]);

        return view('cirugias.show', [
            'caso' => new ResumenCirugia($cirugia),
        ]);
    }
}
