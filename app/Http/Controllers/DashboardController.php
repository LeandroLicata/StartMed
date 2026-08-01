<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\Quirofano;
use App\Support\ResumenCirugia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tablero del gestor de quirofano: el estado de las cirugias proximas y
     * que le falta a cada una para poder realizarse.
     */
    public function __invoke(): View
    {
        $hoy = Carbon::today();

        $cirugias = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', $hoy)
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $cirugia) => new ResumenCirugia($cirugia));

        $deHoy = $cirugias->filter(fn (ResumenCirugia $r) => $r->esDeHoy());

        return view('dashboard', [
            'hoy' => $hoy,
            'cirugias' => $cirugias,
            'cirugiasDeHoy' => $deHoy,
            'enRiesgo' => $cirugias->filter(fn (ResumenCirugia $r) => $r->enRiesgo())->values(),
            'quirofanosActivos' => Quirofano::whereNull('fechaBajaQuirofano')->count(),
            'agenda' => $this->agenda($cirugias),
            'indicadores' => $this->indicadores($cirugias, $deHoy),
        ]);
    }

    /**
     * Cirugias de hoy agrupadas por quirofano.
     *
     * @param  Collection<int, ResumenCirugia>  $cirugias
     * @return Collection<string, Collection<int, ResumenCirugia>>
     */
    private function agenda(Collection $cirugias): Collection
    {
        return $cirugias
            ->filter(fn (ResumenCirugia $r) => $r->esDeHoy() && $r->quirofano !== null)
            ->groupBy(fn (ResumenCirugia $r) => $r->quirofano->nombreQuirofano)
            ->sortKeys();
    }

    /**
     * @param  Collection<int, ResumenCirugia>  $cirugias
     * @param  Collection<int, ResumenCirugia>  $deHoy
     * @return array<string, int>
     */
    private function indicadores(Collection $cirugias, Collection $deHoy): array
    {
        $listas = $deHoy->filter(fn (ResumenCirugia $r) => $r->estaLista());

        return [
            'cirugiasHoy' => $deHoy->count(),
            'proximas' => $cirugias->count(),
            'listas' => $listas->count(),
            'porcentajeListas' => $deHoy->isEmpty()
                ? 0
                : (int) round($listas->count() / $deHoy->count() * 100),
            'sinAutorizacion' => $cirugias->filter(fn (ResumenCirugia $r) => ! $r->autorizacionAprobada())->count(),
            'estudiosPendientes' => $cirugias->filter(fn (ResumenCirugia $r) => $r->estudiosPendientes() > 0)->count(),
        ];
    }
}
