<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Support\ResumenCirugia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnestesistaController extends Controller
{
    /**
     * Bandeja del anestesista: las evaluaciones que tiene asignadas, separadas
     * por lo que le falta hacer a cada una.
     */
    public function __invoke(Request $request): View
    {
        $personal = $request->user()->personal;

        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $evaluaciones = Cirugia::query()
            ->with([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE])
            ->where('idPersonalAnestesista', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        $completas = $evaluaciones->filter(fn (ResumenCirugia $r) => $r->evaluacionCompleta());

        return view('paneles.anestesista', [
            'personal' => $personal,
            'evaluaciones' => $evaluaciones,
            'pendientes' => $evaluaciones->reject(fn (ResumenCirugia $r) => $r->evaluacionCompleta())->values(),
            'indicadores' => [
                'total' => $evaluaciones->count(),
                'completas' => $completas->count(),
                'pendientes' => $evaluaciones->count() - $completas->count(),
                // ASA III o superior necesita monitoreo adicional.
                'riesgoAlto' => $evaluaciones->filter(
                    fn (ResumenCirugia $r) => in_array($r->asa(), ['ASA III', 'ASA IV', 'ASA V'], true)
                )->count(),
                'cuestionarios' => $evaluaciones->filter(
                    fn (ResumenCirugia $r) => $r->cuestionario()->isNotEmpty()
                )->count(),
            ],
        ]);
    }
}
