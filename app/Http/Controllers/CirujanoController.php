<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\TipoCirugia;
use App\Support\ResumenCirugia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CirujanoController extends Controller
{
    /**
     * Agenda del cirujano logueado: sus proximas cirugias, su actividad del
     * mes y el catalogo de procedimientos que puede indicar.
     */
    public function __invoke(Request $request): View
    {
        $personal = $request->user()->personal;

        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $proximas = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        $desde = Carbon::today()->startOfMonth();

        $delMes = Cirugia::query()
            ->with('cirugiaEstados.estadoCirugia')
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereBetween('fechaHoraCirugia', [$desde, Carbon::today()->endOfMonth()])
            ->get();

        $realizadas = $delMes->filter(fn (Cirugia $c) => $this->estadoDe($c) === 'Realizada');
        $suspendidas = $delMes->filter(fn (Cirugia $c) => $this->estadoDe($c) === 'Suspendida');
        $cerradas = $realizadas->count() + $suspendidas->count();

        return view('paneles.cirujano', [
            'personal' => $personal,
            'proximas' => $proximas,
            'conImplante' => $proximas->filter(fn (ResumenCirugia $r) => $r->cirugia->requiereImplante)->count(),
            'indicadores' => [
                'realizadas' => $realizadas->count(),
                'suspendidas' => $suspendidas->count(),
                'tasaSuspension' => $cerradas > 0
                    ? round($suspendidas->count() / $cerradas * 100, 1)
                    : 0.0,
                'mes' => $desde->translatedFormat('F'),
            ],
            'procedimientos' => TipoCirugia::query()
                ->whereNull('fechaBajaTipoCirugia')
                ->withCount('cirugias')
                ->orderByDesc('cirugias_count')
                ->get(),
        ]);
    }

    private function estadoDe(Cirugia $cirugia): ?string
    {
        return $cirugia->cirugiaEstados
            ->firstWhere('fechaDesasignacionCirugiaEstado', null)
            ?->estadoCirugia?->nombreEstadoCirugia;
    }
}
