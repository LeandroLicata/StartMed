<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\Quirofano;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DireccionController extends Controller
{
    /** Meses hacia atrás que cubre la serie histórica. */
    private const MESES = 6;

    /**
     * Vista ejecutiva: volumen, suspensiones y uso de quirofanos.
     *
     * Todo se calcula sobre `Cirugia` y su historial de estados. No hay tabla
     * de metricas precomputadas ni de motivos de suspension, asi que el
     * desglose por causa no se puede mostrar todavia (ver la vista).
     */
    public function __invoke(): View
    {
        $desde = Carbon::today()->subMonthsNoOverflow(self::MESES - 1)->startOfMonth();

        $cirugias = Cirugia::query()
            ->with('cirugiaEstados.estadoCirugia', 'cirugiaQuirofanos')
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', $desde)
            ->get();

        $porMes = $this->serieMensual($cirugias, $desde);
        $mesActual = $porMes->last();
        $mesAnterior = $porMes->slice(-2, 1)->first();

        $cerradas = $cirugias->filter(fn (Cirugia $c) => in_array($this->estadoDe($c), ['Realizada', 'Suspendida'], true));
        $suspendidas = $cerradas->filter(fn (Cirugia $c) => $this->estadoDe($c) === 'Suspendida');

        return view('paneles.direccion', [
            'desde' => $desde,
            'porMes' => $porMes,
            'mesActual' => $mesActual,
            'mesAnterior' => $mesAnterior,
            'totalPeriodo' => $cirugias->count(),
            'tasaSuspension' => $cerradas->isEmpty()
                ? 0.0
                : round($suspendidas->count() / $cerradas->count() * 100, 1),
            'suspendidas' => $suspendidas->count(),
            'porQuirofano' => $this->porQuirofano($cirugias),
            'quirofanos' => Quirofano::whereNull('fechaBajaQuirofano')->orderBy('nroQuirofano')->get(),
        ]);
    }

    /**
     * Serie mensual de cirugías cerradas, realizadas y suspendidas.
     *
     * @param  Collection<int, Cirugia>  $cirugias
     * @return Collection<int, array{etiqueta: string, total: int, realizadas: int, suspendidas: int, tasa: float}>
     */
    private function serieMensual(Collection $cirugias, Carbon $desde): Collection
    {
        $agrupadas = $cirugias->groupBy(fn (Cirugia $c) => $c->fechaHoraCirugia->format('Y-m'));

        return collect(range(0, self::MESES - 1))
            ->map(function (int $i) use ($desde, $agrupadas) {
                $mes = $desde->copy()->addMonthsNoOverflow($i);
                $delMes = $agrupadas->get($mes->format('Y-m'), collect());

                $realizadas = $delMes->filter(fn (Cirugia $c) => $this->estadoDe($c) === 'Realizada')->count();
                $suspendidas = $delMes->filter(fn (Cirugia $c) => $this->estadoDe($c) === 'Suspendida')->count();
                $cerradas = $realizadas + $suspendidas;

                return [
                    'etiqueta' => $mes->translatedFormat('M'),
                    'total' => $delMes->count(),
                    'realizadas' => $realizadas,
                    'suspendidas' => $suspendidas,
                    'tasa' => $cerradas > 0 ? round($suspendidas / $cerradas * 100, 1) : 0.0,
                ];
            });
    }

    /**
     * Cuántas cirugías pasó cada quirófano en el período.
     *
     * @param  Collection<int, Cirugia>  $cirugias
     * @return Collection<int, int> idQuirofano => cantidad
     */
    private function porQuirofano(Collection $cirugias): Collection
    {
        return $cirugias
            ->flatMap(fn (Cirugia $c) => $c->cirugiaQuirofanos->pluck('idQuirofano'))
            ->countBy();
    }

    private function estadoDe(Cirugia $cirugia): ?string
    {
        return $cirugia->cirugiaEstados
            ->firstWhere('fechaDesasignacionCirugiaEstado', null)
            ?->estadoCirugia?->nombreEstadoCirugia;
    }
}
