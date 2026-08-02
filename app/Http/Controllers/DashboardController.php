<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\EstadoCirugia;
use App\Models\ObraSocial;
use App\Models\Quirofano;
use App\Support\ResumenCirugia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tablero del gestor de quirofano: el estado de las cirugias proximas y
     * que le falta a cada una para poder realizarse.
     */
    public function __invoke(Request $request): View
    {
        $hoy = Carbon::today();

        $cirugias = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', $hoy)
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $cirugia) => new ResumenCirugia($cirugia));

        $cirugiasDeHoy = $cirugias->filter(fn (ResumenCirugia $r) => $r->esDeHoy())->values();

        $inicioSemana = $hoy->copy()->startOfWeek();
        $finSemana = $hoy->copy()->endOfWeek();

        // "Desde"/"Hasta" quedan precargados con la semana actual (lunes a
        // domingo) para que "Cirugias de la semana" arranque mostrando eso,
        // pero el gestor los puede pisar para ampliar o acotar la busqueda.
        $desde = $request->query('desde', $inicioSemana->toDateString());
        $hasta = $request->query('hasta', $finSemana->toDateString());

        return view('dashboard', [
            'hoy' => $hoy,
            'cirugias' => $cirugias,
            'cirugiasDeHoy' => $cirugiasDeHoy,
            'cirugiasFiltradas' => $this->cirugiasFiltradas($request, $desde, $hasta),
            'enRiesgoCount' => $cirugias->filter(fn (ResumenCirugia $r) => $r->enRiesgo())->count(),
            'quirofanosActivos' => Quirofano::whereNull('fechaBajaQuirofano')->count(),
            'agenda' => $this->agenda($cirugias),
            'indicadores' => $this->indicadores($cirugias, $cirugiasDeHoy),
            'filtros' => array_merge(
                $request->only(['q', 'estado', 'idQuirofano', 'idObraSocial']),
                ['desde' => $desde, 'hasta' => $hasta],
            ),
            'hayFiltrosActivos' => $request->hasAny(['q', 'estado', 'idQuirofano', 'idObraSocial', 'desde', 'hasta']),
            'estadosCirugia' => EstadoCirugia::whereNull('fechaBajaEstadoCirugia')->orderBy('nombreEstadoCirugia')->get(),
            'quirofanosCatalogo' => Quirofano::whereNull('fechaBajaQuirofano')->orderBy('nroQuirofano')->get(),
            'obrasSocialesCatalogo' => ObraSocial::whereNull('fechaBajaObraSocial')->orderBy('nombreObraSocial')->get(),
        ]);
    }

    /**
     * "Cirugias de la semana": arranca acotado a la semana actual (lunes a
     * domingo) y el gestor lo puede filtrar como a "Estado de los pacientes"
     * antes. Lo que se puede resolver en SQL se filtra ahi (fecha, quirofano,
     * texto); estado y obra social dependen de logica que ya vive en
     * ResumenCirugia, asi que se filtran despues sobre la coleccion ya armada.
     */
    private function cirugiasFiltradas(Request $request, string $desde, string $hasta): Collection
    {
        $texto = $request->query('q');

        $query = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', Carbon::parse($desde))
            ->where('fechaHoraCirugia', '<=', Carbon::parse($hasta)->endOfDay());

        if ($request->filled('idQuirofano')) {
            $query->whereHas(
                'cirugiaQuirofanos',
                fn ($q) => $q->whereNull('fechaHoraDesasignacion')->where('idQuirofano', $request->query('idQuirofano')),
            );
        }

        if ($texto) {
            $query->whereHas('paciente', function ($q) use ($texto) {
                $q->where('apellidos', 'like', "%{$texto}%")
                    ->orWhere('nombres', 'like', "%{$texto}%")
                    ->orWhere('documento', 'like', "%{$texto}%");
            });
        }

        $resultado = $query->orderBy('fechaHoraCirugia')->get()
            ->map(fn (Cirugia $cirugia) => new ResumenCirugia($cirugia));

        if ($request->filled('estado')) {
            $resultado = $resultado->filter(fn (ResumenCirugia $r) => $r->estado() === $request->query('estado'));
        }

        if ($request->filled('idObraSocial')) {
            $resultado = $resultado->filter(
                fn (ResumenCirugia $r) => (string) $r->plan?->obrasocial?->idObraSocial === $request->query('idObraSocial'),
            );
        }

        return $resultado->values();
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
