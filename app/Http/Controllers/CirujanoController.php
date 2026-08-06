<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\TipoCirugia;
use App\Support\Paginador;
use App\Support\ResumenCirugia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CirujanoController extends Controller
{
    /**
     * Los tres listados del panel crecen sin techo: la agenda de un cirujano,
     * lo que le falta a cada caso y el catalogo de procedimientos. Cada uno
     * pagina con su propio nombre de pagina para no arrastrar a los otros.
     */
    private const POR_PAGINA = 10;

    private const PROCEDIMIENTOS_POR_PAGINA = 12;

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

        $ultimasCirugias = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->orderByDesc('fechaHoraCirugia')
            ->limit(3)
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        // Los indicadores y "que falta resolver" salen de la colección entera:
        // lo que se pagina es lo que se dibuja, no lo que se cuenta.
        $conPendientes = $proximas->reject(fn (ResumenCirugia $r) => $r->estaLista())->values();

        return view('paneles.cirujano', [
            'personal' => $personal,
            'hoy' => $hoy,
            'proximas' => Paginador::deColeccion($proximas, $request, self::POR_PAGINA, 'proximas'),
            'conPendientes' => Paginador::deColeccion($conPendientes, $request, self::POR_PAGINA, 'pendientes'),
            'ultimasCirugias' => $ultimasCirugias,
            'conImplante' => $proximas->filter(fn (ResumenCirugia $r) => $r->cirugia->requiereImplante)->count(),
            'indicadores' => [
                'realizadas' => $realizadas->count(),
                'realizadasCompletas' => $realizadasCompletas->count(),
                'suspendidas' => $suspendidas->count(),
                'tasaSuspension' => $cerradas > 0 ? round($suspendidas->count() / $cerradas * 100, 1) : 0.0,
                'mes' => $desde->translatedFormat('F'),
            ],
            'procedimientos' => TipoCirugia::query()
                ->whereNull('fechaBajaTipoCirugia')
                ->withCount('cirugias')
                ->orderByDesc('cirugias_count')
                ->paginate(self::PROCEDIMIENTOS_POR_PAGINA, ['*'], 'procedimientos')
                ->withQueryString(),
        ]);
    }

    public function historial(Request $request): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $historial = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->orderByDesc('fechaHoraCirugia')
            ->paginate(10);

        $historialResumen = $historial->through(fn ($c) => new ResumenCirugia($c));

        return view('paneles.cirujano-historial', [
            'personal' => $personal,
            'historial' => $historialResumen,
        ]);
    }

    public function detalle(Request $request, Cirugia $cirugia): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');
        abort_unless($cirugia->idPersonalCirujano === $personal->idPersonal, 403, 'No tienes permisos para ver esta cirugia.');

        $cirugia->load(ResumenCirugia::RELACIONES);

        return view('paneles.cirujano-detalle', [
            'personal' => $personal,
            'cirugia' => new ResumenCirugia($cirugia),
        ]);
    }

    public function agenda(Request $request): View
    {
        return $request->query('vista') === 'semana'
            ? $this->agendaSemana($request)
            : $this->agendaMes($request);
    }

    public function agendaDia(Request $request, string $fecha): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $fecha = Carbon::parse($fecha);

        $cirugias = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereBetween('fechaHoraCirugia', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        $desdeSemana = $request->query('desde') === 'semana';
        $volverA = $desdeSemana
            ? route('cirujano.agenda', ['vista' => 'semana', 'semana' => $fecha->copy()->startOfWeek()->toDateString()])
            : route('cirujano.agenda', ['mes' => $fecha->format('Y-m')]);

        return view('paneles.cirujano-agenda-dia', [
            'fecha' => $fecha,
            'cirugias' => $cirugias,
            'volverA' => $volverA,
        ]);
    }

    private function agendaMes(Request $request): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $referencia = $request->filled('mes')
            ? Carbon::parse($request->query('mes').'-01')
            : Carbon::today();

        $inicioMes = $referencia->copy()->startOfMonth();
        $finMes = $referencia->copy()->endOfMonth();
        $inicioGrilla = $inicioMes->copy()->startOfWeek();
        $finGrilla = $finMes->copy()->endOfWeek();

        $cirugiasPorDia = $this->cirugiasDelCirujanoEnRango($personal->idPersonal, $inicioGrilla, $finGrilla)
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

        return view('paneles.cirujano-agenda-mes', [
            'semanas' => $semanas,
            'referencia' => $inicioMes,
            'mesAnterior' => $inicioMes->copy()->subMonth()->format('Y-m'),
            'mesSiguiente' => $inicioMes->copy()->addMonth()->format('Y-m'),
        ]);
    }

    private function agendaSemana(Request $request): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $referencia = $request->filled('semana') ? Carbon::parse($request->query('semana')) : Carbon::today();
        $inicio = $referencia->copy()->startOfWeek();
        $fin = $referencia->copy()->endOfWeek();

        $cirugias = $this->cirugiasDelCirujanoEnRango($personal->idPersonal, $inicio, $fin);

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

        return view('paneles.cirujano-agenda-semana', [
            'dias' => $dias,
            'inicio' => $inicio,
            'fin' => $fin,
            'semanaAnterior' => $inicio->copy()->subWeek()->toDateString(),
            'semanaSiguiente' => $inicio->copy()->addWeek()->toDateString(),
        ]);
    }

    /** @return Collection<int, ResumenCirugia> */
    private function cirugiasDelCirujanoEnRango(int $idPersonal, Carbon $desde, Carbon $hasta): Collection
    {
        return Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $idPersonal)
            ->whereBetween('fechaHoraCirugia', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));
    }
}
