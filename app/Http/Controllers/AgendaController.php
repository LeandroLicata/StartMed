<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltraCirugias;
use App\Models\Cirugia;
use App\Support\ResumenCirugia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Calendario del gestor: vista mensual (default) o semanal para ver de un
 * vistazo como esta organizada la agenda, y el detalle de un dia puntual con
 * los mismos filtros que "Cirugias de la semana" del tablero.
 */
class AgendaController extends Controller
{
    use FiltraCirugias;

    public function index(Request $request): View
    {
        return $request->query('vista') === 'semana'
            ? $this->vistaSemana($request)
            : $this->vistaMes($request);
    }

    public function dia(Request $request, string $fecha): View
    {
        $fecha = Carbon::parse($fecha);

        $query = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->whereBetween('fechaHoraCirugia', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()]);

        $desdeSemana = $request->query('desde') === 'semana';
        $volverA = $desdeSemana
            ? route('agenda', ['vista' => 'semana', 'semana' => $fecha->copy()->startOfWeek()->toDateString()])
            : route('agenda', ['mes' => $fecha->format('Y-m')]);

        return view('agenda.dia', array_merge([
            'fecha' => $fecha,
            'cirugias' => $this->aplicarFiltros($query, $request),
            'filtros' => $request->only(['q', 'estado', 'idQuirofano', 'idObraSocial']),
            'hayFiltrosActivos' => $request->hasAny(['q', 'estado', 'idQuirofano', 'idObraSocial']),
            'volverA' => $volverA,
        ], $this->catalogosFiltro()));
    }

    private function vistaMes(Request $request): View
    {
        $referencia = $request->filled('mes')
            ? Carbon::parse($request->query('mes').'-01')
            : Carbon::today();

        $inicioMes = $referencia->copy()->startOfMonth();
        $finMes = $referencia->copy()->endOfMonth();
        $inicioGrilla = $inicioMes->copy()->startOfWeek();
        $finGrilla = $finMes->copy()->endOfWeek();

        $cirugiasPorDia = $this->cirugiasEnRango($inicioGrilla, $finGrilla)
            ->groupBy(fn (ResumenCirugia $r) => $r->cuando()->toDateString());

        $semanas = collect();
        $cursor = $inicioGrilla->copy();

        while ($cursor->lte($finGrilla)) {
            $semana = collect();

            for ($i = 0; $i < 7; $i++) {
                $delDia = $cirugiasPorDia->get($cursor->toDateString(), collect());

                $semana->push([
                    'fecha' => $cursor->copy(),
                    'delMes' => $cursor->month === $inicioMes->month,
                    'cantidad' => $delDia->count(),
                    'enRiesgo' => $delDia->filter(fn (ResumenCirugia $r) => $r->enRiesgo())->count(),
                ]);

                $cursor->addDay();
            }

            $semanas->push($semana);
        }

        return view('agenda.mes', [
            'semanas' => $semanas,
            'referencia' => $inicioMes,
            'mesAnterior' => $inicioMes->copy()->subMonth()->format('Y-m'),
            'mesSiguiente' => $inicioMes->copy()->addMonth()->format('Y-m'),
        ]);
    }

    private function vistaSemana(Request $request): View
    {
        $referencia = $request->filled('semana') ? Carbon::parse($request->query('semana')) : Carbon::today();
        $inicio = $referencia->copy()->startOfWeek();
        $fin = $referencia->copy()->endOfWeek();

        $cirugias = $this->cirugiasEnRango($inicio, $fin);

        $dias = collect(range(0, 6))->map(function (int $i) use ($inicio, $cirugias) {
            $fecha = $inicio->copy()->addDays($i);
            $delDia = $cirugias->filter(fn (ResumenCirugia $r) => $r->cuando()->isSameDay($fecha))->values();

            $columns = [];
            foreach ($delDia as $caso) {
                $start = $caso->minutosDesdeMedianoche();
                $end = $start + $caso->duracionEnMinutos();

                $placed = false;
                foreach ($columns as $colIndex => $colEndTime) {
                    if ($start >= $colEndTime) {
                        $caso->overlapCol = $colIndex;
                        $columns[$colIndex] = $end;
                        $placed = true;
                        break;
                    }
                }

                if (! $placed) {
                    $caso->overlapCol = count($columns);
                    $columns[] = $end;
                }
            }

            $maxCols = count($columns);
            foreach ($delDia as $caso) {
                $caso->overlapTotal = max(1, $maxCols);
            }

            return [
                'fecha' => $fecha,
                'cirugias' => $delDia,
                'enRiesgo' => $delDia->filter(fn (ResumenCirugia $r) => $r->enRiesgo())->count(),
            ];
        });

        return view('agenda.semana', [
            'dias' => $dias,
            'inicio' => $inicio,
            'fin' => $fin,
            'semanaAnterior' => $inicio->copy()->subWeek()->toDateString(),
            'semanaSiguiente' => $inicio->copy()->addWeek()->toDateString(),
        ]);
    }

    /** @return Collection<int, ResumenCirugia> */
    private function cirugiasEnRango(Carbon $desde, Carbon $hasta): Collection
    {
        return Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->whereBetween('fechaHoraCirugia', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));
    }
}
