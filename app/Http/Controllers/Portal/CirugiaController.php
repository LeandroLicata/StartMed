<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cirugia;
use Illuminate\View\View;

/**
 * Cirugias del paciente: proximas, historial y el detalle de cada una.
 *
 * Esqueleto del contrato del portal; el contenido real se completa despues.
 * La pertenencia de `{cirugia}` ya la verifica el middleware
 * `cirugia.del.paciente` del grupo de rutas: aca no se repite.
 */
class CirugiaController extends Controller
{
    public function proximas(): View
    {
        return view('portal.pendiente', ['titulo' => 'Próximas cirugías']);
    }

    public function historial(): View
    {
        return view('portal.pendiente', ['titulo' => 'Historial de cirugías']);
    }

    public function show(Cirugia $cirugia): View
    {
        return view('portal.pendiente', ['titulo' => 'Detalle de la cirugía']);
    }
}
