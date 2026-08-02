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

        return view('dashboard', [
            'hoy' => $hoy,
            'cirugias' => $cirugias,
            'cirugiasDeHoy' => $cirugiasDeHoy,
            'cirugiasFiltradas' => $this->cirugiasFiltradas($request, $hoy),
            'enRiesgoCount' => $cirugias->filter(fn (ResumenCirugia $r) => $r->enRiesgo())->count(),
            'quirofanosActivos' => Quirofano::whereNull('fechaBajaQuirofano')->count(),
            'agenda' => $this->agenda($cirugias),
            'indicadores' => $this->indicadores($cirugias, $cirugiasDeHoy),
            'filtros' => $request->only(['q', 'estado', 'desde', 'hasta', 'idQuirofano', 'idObraSocial']),
            'estadosCirugia' => EstadoCirugia::whereNull('fechaBajaEstadoCirugia')->orderBy('nombreEstadoCirugia')->get(),
            'quirofanosCatalogo' => Quirofano::whereNull('fechaBajaQuirofano')->orderBy('nroQuirofano')->get(),
            'obrasSocialesCatalogo' => ObraSocial::whereNull('fechaBajaObraSocial')->orderBy('nombreObraSocial')->get(),
        ]);
    }

    /**
     * "Estado de los pacientes" con buscador y filtros. Lo que se puede
     * resolver en SQL se filtra ahi (fecha, quirofano, texto); estado y obra
     * social dependen de logica que ya vive en ResumenCirugia, asi que se
     * filtran despues sobre la coleccion ya armada.
     */
    private function cirugiasFiltradas(Request $request, Carbon $hoy): Collection
    {
        $desde = $request->filled('desde') ? Carbon::parse($request->query('desde')) : $hoy;
        $hasta = $request->filled('hasta') ? Carbon::parse($request->query('hasta'))->endOfDay() : null;
        $texto = $request->query('q');

        $query = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', $desde);

        if ($hasta) {
            $query->where('fechaHoraCirugia', '<=', $hasta);
        }

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
