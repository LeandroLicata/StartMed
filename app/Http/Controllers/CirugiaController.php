<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltraCirugias;
use App\Models\Cirugia;
use App\Models\ObraSocial;
use App\Models\Quirofano;
use App\Services\ReprogramarCirugiaService;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CirugiaController extends Controller
{
    use FiltraCirugias;

    private const TABS = ['resumen', 'estudios', 'materiales', 'hemoderivados', 'profilaxis', 'autorizacion'];

    private const POR_PAGINA = 20;

    /**
     * Listado de todas las cirugias (pasadas y futuras), con los mismos
     * filtros que "Cirugias de la semana" del tablero y "Agenda", pero sin
     * acotar por fecha salvo que el gestor lo pida.
     */
    public function index(Request $request): View
    {
        $query = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->whereNotNull('fechaHoraCirugia');

        if ($request->filled('desde')) {
            $query->where('fechaHoraCirugia', '>=', Carbon::parse($request->query('desde')));
        }

        if ($request->filled('hasta')) {
            $query->where('fechaHoraCirugia', '<=', Carbon::parse($request->query('hasta'))->endOfDay());
        }

        $todas = $this->aplicarFiltros($query, $request);

        $pagina = (int) $request->query('page', 1);

        $cirugias = new LengthAwarePaginator(
            $todas->forPage($pagina, self::POR_PAGINA)->values(),
            $todas->count(),
            self::POR_PAGINA,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('cirugias.index', array_merge([
            'cirugias' => $cirugias,
            'filtros' => $request->only(['q', 'estado', 'idQuirofano', 'idObraSocial', 'desde', 'hasta']),
            'hayFiltrosActivos' => $request->hasAny(['q', 'estado', 'idQuirofano', 'idObraSocial', 'desde', 'hasta']),
        ], $this->catalogosFiltro()));
    }

    /**
     * Expediente completo de una cirugía: el estado de cada módulo en una
     * sola pantalla, organizado en solapas.
     */
    public function show(Cirugia $cirugia, Request $request): View
    {
        $cirugia->load([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE]);

        $tab = $request->query('tab', 'resumen');

        return view('cirugias.show', [
            'caso' => new ResumenCirugia($cirugia),
            'tabActivo' => in_array($tab, self::TABS, true) ? $tab : 'resumen',
            'quirofanos' => Quirofano::whereNull('fechaBajaQuirofano')
                ->with(['quirofanoEstados' => fn ($q) => $q->whereNull('fechaFinQuirofanoEstado')->with('estadoQuirofano')])
                ->orderBy('nroQuirofano')
                ->get()
                ->filter(function (Quirofano $q) {
                    $estado = $q->quirofanoEstados->first()?->estadoQuirofano?->nombreEstadoQuirofano;

                    return $estado === null || $estado === 'Disponible';
                })
                ->values(),
            'coberturas' => $cirugia->paciente->planObraSociales()
                ->whereNull('fechaFinPlanObraSocial')
                ->with('plan.obrasocial')
                ->get(),
            'obrasSociales' => ObraSocial::whereNull('fechaBajaObraSocial')
                ->where('nombreObraSocial', '!=', 'Sin obra social')
                ->with(['planes' => fn ($q) => $q->whereNull('fechaBajaPlan')])
                ->orderBy('nombreObraSocial')
                ->get(),
        ]);
    }

    public function reprogramar(Request $request, Cirugia $cirugia, ReprogramarCirugiaService $service): RedirectResponse
    {
        $fechaHoraCirugia = $request->input('fecha') && $request->input('hora_inicio')
            ? $request->input('fecha').' '.$request->input('hora_inicio')
            : null;

        $fechaHoraFinCirugia = $request->input('fecha') && $request->input('hora_fin')
            ? $request->input('fecha').' '.$request->input('hora_fin')
            : null;

        $request->merge([
            'fechaHoraCirugia' => $fechaHoraCirugia,
            'fechaHoraFinCirugia' => $fechaHoraFinCirugia,
        ]);

        $datos = $request->validate([
            'fechaHoraCirugia' => ['required', 'date'],
            'fechaHoraFinCirugia' => ['nullable', 'date', 'after:fechaHoraCirugia'],
            'idQuirofano' => ['required', 'exists:Quirofano,idQuirofano'],
            'cobertura' => ['required', 'in:particular,existente,nueva,misma'],
            'idPlanObraSocial' => ['required_if:cobertura,existente', 'nullable', 'exists:PlanObraSocial,idPlanObraSocial'],
            'idPlan' => ['required_if:cobertura,nueva', 'nullable', 'exists:Plan,idPlan'],
            'nroBeneficiario' => ['nullable', 'string', 'max:60'],
        ]);

        $datos['idPersona'] = $cirugia->idPersonaPaciente;

        // Validar que la cirugía esté a más de 24 horas (opcional si la UI ya lo restringe, pero es buena práctica)
        // Pero el usuario dijo "una vez pasado las 24hs... se libera" (eso lo hace el comando).
        // Y "El botón siempre estará visible/habilitado".
        // Si siempre está habilitado, no restringimos por 24hs aquí en backend para el botón manual,
        // a menos que sea una regla estricta. Asumo que el gestor siempre puede reprogramar.

        // Chequear disponibilidad de quirófano
        $inicio = Carbon::parse($datos['fechaHoraCirugia']);
        $fin = ! empty($datos['fechaHoraFinCirugia']) ? Carbon::parse($datos['fechaHoraFinCirugia']) : $inicio->copy()->addMinutes(120);

        $quirofanoOcupado = Cirugia::whereHas(
            'cirugiaQuirofanos',
            fn ($q) => $q->where('idQuirofano', $datos['idQuirofano'])->whereNull('fechaHoraDesasignacion')
        )
            ->where('idCirugia', '!=', $cirugia->idCirugia)
            ->where('fechaHoraCirugia', '<', $fin)
            ->where('fechaHoraFinCirugia', '>', $inicio)
            ->exists();

        if ($quirofanoOcupado) {
            throw ValidationException::withMessages([
                'idQuirofano' => 'Ese quirófano ya tiene una cirugía programada en un horario que se superpone.',
            ]);
        }

        $nuevaCirugia = $service->reprogramar($cirugia, $datos);

        return redirect()->route('cirugias.show', $nuevaCirugia)
            ->with('exito', 'Cirugía reprogramada exitosamente.');
    }
}
