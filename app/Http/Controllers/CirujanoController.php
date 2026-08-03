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
        $hoy = $proximas->filter(fn (ResumenCirugia $r) => $r->esDeHoy())->values();

        $desde = Carbon::today()->startOfMonth();

        $delMes = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereBetween('fechaHoraCirugia', [$desde, Carbon::today()->endOfMonth()])
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        $realizadas = $delMes->filter(fn (ResumenCirugia $r) => $r->estado() === 'Realizada');
        $suspendidas = $delMes->filter(fn (ResumenCirugia $r) => $r->estado() === 'Suspendida');
        $realizadasCompletas = $realizadas->filter(fn (ResumenCirugia $r) => $r->estaLista());
        $cerradas = $realizadas->count() + $suspendidas->count();

        return view('paneles.cirujano', [
            'personal' => $personal,
            'hoy' => $hoy,
            'proximas' => $proximas,
            'conImplante' => $proximas->filter(fn (ResumenCirugia $r) => $r->cirugia->requiereImplante)->count(),
            'indicadores' => [
                'realizadas' => $realizadas->count(),
                'realizadasCompletas' => $realizadasCompletas->count(),
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

    public function agenda(Request $request): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $mes = $request->query('mes')
            ? Carbon::createFromFormat('Y-m', $request->query('mes'))->startOfMonth()
            : Carbon::today()->startOfMonth();

        $inicioGrilla = $mes->copy()->startOfWeek(Carbon::MONDAY);
        $finGrilla = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $cirugias = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->whereBetween('fechaHoraCirugia', [$inicioGrilla, $finGrilla])
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c))
            ->groupBy(fn (ResumenCirugia $r) => $r->cuando()->format('Y-m-d'));

        $semanas = collect();
        $cursor = $inicioGrilla->copy();

        while ($cursor <= $finGrilla) {
            $semana = collect();

            for ($i = 0; $i < 7; $i++) {
                $semana->push([
                    'fecha' => $cursor->copy(),
                    'esDelMes' => $cursor->month === $mes->month,
                    'esHoy' => $cursor->isToday(),
                    'cirugias' => $cirugias->get($cursor->format('Y-m-d'), collect()),
                ]);
                $cursor->addDay();
            }

            $semanas->push($semana);
        }

        return view('paneles.cirujano-agenda', [
            'personal' => $personal,
            'mes' => $mes,
            'semanas' => $semanas,
            'mesAnterior' => $mes->copy()->subMonth()->format('Y-m'),
            'mesSiguiente' => $mes->copy()->addMonth()->format('Y-m'),
        ]);
    }
}
