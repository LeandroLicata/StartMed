<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltraCirugias;
use App\Models\AutCirugia;
use App\Models\AutoCirugiaEstado;
use App\Models\Cirugia;
use App\Models\CirugiaTipoEstudio;
use App\Models\Establecimiento;
use App\Models\EstadoAutCirugia;
use App\Models\EstadoHisopadoSarm;
use App\Models\EstadoPedidoHemoderivado;
use App\Models\EstadoPedidoTipoHemoderivado;
use App\Models\HisopadoSarm;
use App\Models\HisopadoSarmEstado;
use App\Models\ObraSocial;
use App\Models\PedidoHemoderivado;
use App\Models\PedidoHemoderivadoEstado;
use App\Models\PedidoTipoHemoderivado;
use App\Models\PedidoTipoHemoderivadoEstado;
use App\Models\Quirofano;
use App\Models\TipoEstudio;
use App\Models\TipoHemoderivado;
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
            'establecimientosHisopado' => Establecimiento::orderBy('nombreEstablecimiento')
                ->get()
                ->mapWithKeys(fn ($e) => [$e->idEstablecimiento => $e->nombreEstablecimiento]),
            'estadosAutorizacion' => EstadoAutCirugia::orderBy('nombreEstadoAutCirugia')
                ->get()
                ->mapWithKeys(fn ($e) => [$e->idEstadoAutCirugia => $e->nombreEstadoAutCirugia]),
            'tiposEstudios' => TipoEstudio::whereNull('fechaBajaTipoEstudio')
                ->orderBy('nombreTipoEstudio')
                ->get()
                ->mapWithKeys(fn ($e) => [$e->idTipoEstudio => $e->nombreTipoEstudio]),
            'tiposHemoderivados' => TipoHemoderivado::orderBy('nombreTipoHemoderivado')
                ->get()
                ->mapWithKeys(fn ($e) => [$e->idTipoHemoderivado => $e->nombreTipoHemoderivado]),
            'estadosHemoderivados' => EstadoPedidoTipoHemoderivado::orderBy('nombreEstadoPedidoTipoHemoderivado')
                ->get()
                ->mapWithKeys(fn ($e) => [$e->idEstadoPedidoTipoHemoderivado => $e->nombreEstadoPedidoTipoHemoderivado]),
        ]);
    }

    /**
     * Actualiza el laboratorio, la fecha estimada de resultados y las
     * observaciones del hisopado SAMR de una cirugía.
     */
    public function actualizarHisopado(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'idEstablecimiento' => ['nullable', 'exists:Establecimiento,idEstablecimiento'],
            'fechaEstimadaResultadosHisopadoSarm' => ['nullable', 'date'],
            'observacionesHisopadoSarm' => ['nullable', 'string', 'max:255'],
        ]);

        $hisopado = HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->firstOrFail();

        $hisopado->update($datos);

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'profilaxis'])
            ->with('exito', 'Datos del hisopado actualizados.');
    }

    /**
     * Registra el resultado del hisopado SAMR (Positivo o Negativo).
     *
     * Cierra el estado vigente (fechaFin = now()) y abre uno nuevo,
     * siguiendo el patron de date-ranges del sistema.
     */
    public function actualizarEstadoHisopado(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:Negativo,Positivo'],
            'observacionesHisopadoSarm' => ['nullable', 'string', 'max:255'],
        ]);

        $hisopado = HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->firstOrFail();

        // Cerrar el estado vigente.
        HisopadoSarmEstado::where('idHisopadoSarm', $hisopado->idHisopadoSarm)
            ->whereNull('fechaFinAsignacionHisopadoSarmEstado')
            ->update(['fechaFinAsignacionHisopadoSarmEstado' => now()]);

        // Abrir el nuevo estado.
        HisopadoSarmEstado::create([
            'idHisopadoSarm' => $hisopado->idHisopadoSarm,
            'idEstadoHisopadoSarm' => EstadoHisopadoSarm::where('nombreEstadoHisopadoSarm', $datos['estado'])
                ->value('idEstadoHisopadoSarm'),
            'fechaInicioAsignacionHisopadoSarmEstado' => now(),
        ]);

        // Guardar observaciones si vienen.
        if (! empty($datos['observacionesHisopadoSarm'])) {
            $hisopado->update(['observacionesHisopadoSarm' => $datos['observacionesHisopadoSarm']]);
        }

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'profilaxis'])
            ->with('exito', 'Resultado del hisopado registrado correctamente.');
    }

    /**
     * Cambia el estado de la autorización del financiador.
     */
    public function actualizarEstadoAutorizacion(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'idEstadoAutCirugia' => ['required', 'exists:EstadoAutCirugia,idEstadoAutCirugia'],
            'observacionesAutoCirugiaEstado' => ['nullable', 'string', 'max:255'],
        ]);

        $autorizacion = AutCirugia::where('idCirugia', $cirugia->idCirugia)->firstOrFail();

        // Cerrar estado vigente.
        AutoCirugiaEstado::where('idAutCirugia', $autorizacion->idAutCirugia)
            ->whereNull('fechaFinAutoCirugiaEstado')
            ->update(['fechaFinAutoCirugiaEstado' => now()]);

        // Abrir nuevo estado.
        AutoCirugiaEstado::create([
            'idAutCirugia' => $autorizacion->idAutCirugia,
            'idEstadoAutCirugia' => $datos['idEstadoAutCirugia'],
            'fechaInicioAutoCirugiaEstado' => now(),
            'observacionesAutoCirugiaEstado' => $datos['observacionesAutoCirugiaEstado'],
        ]);

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'autorizacion'])
            ->with('exito', 'Estado de la autorización actualizado.');
    }

    /**
     * Agrega un nuevo estudio prequirúrgico a la cirugía.
     */
    public function agregarEstudio(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'idTipoEstudio' => ['required', 'exists:TipoEstudio,idTipoEstudio'],
        ]);

        // Verificar si ya está asignado
        if (CirugiaTipoEstudio::where('idCirugia', $cirugia->idCirugia)
            ->where('idTipoEstudio', $datos['idTipoEstudio'])
            ->exists()) {
            return back()->with('error', 'El estudio ya está asignado a esta cirugía.');
        }

        CirugiaTipoEstudio::create([
            'idCirugia' => $cirugia->idCirugia,
            'idTipoEstudio' => $datos['idTipoEstudio'],
            'fechaAsignacionCirugiaTipoEstudio' => now(),
        ]);

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'estudios'])
            ->with('exito', 'Estudio agregado correctamente.');
    }

    /**
     * Actualiza los datos de un estudio prequirúrgico existente (fecha esperada, resultado, archivo).
     */
    public function actualizarEstudio(Request $request, Cirugia $cirugia, CirugiaTipoEstudio $estudio): RedirectResponse
    {
        // Validar que el estudio pertenece a la cirugía
        if ($estudio->idCirugia !== $cirugia->idCirugia) {
            abort(404);
        }

        $datos = $request->validate([
            'fechaEsperadaResultadoCirugiaTipoEstudio' => ['nullable', 'date'],
            'resultadoCirugiaTipoEstudio' => ['nullable', 'string'],
            'archivoResultadoEstudio' => ['nullable', 'file'], // MOCK: solo validamos que sea archivo si viene
        ]);

        $updateData = [
            'fechaEsperadaResultadoCirugiaTipoEstudio' => $datos['fechaEsperadaResultadoCirugiaTipoEstudio'] ?? null,
            'resultadoCirugiaTipoEstudio' => $datos['resultadoCirugiaTipoEstudio'] ?? null,
        ];

        // MOCK: Si se sube un archivo, marcamos como subido hoy. En el futuro, se guarda en el gestor documental.
        if ($request->hasFile('archivoResultadoEstudio')) {
            $updateData['fechaSubidaCirugiaTipoEstudio'] = now();
            // $updateData['urlArchivoCirugiaTipoEstudio'] = ...; // Guardar URL provista por el gestor
        }

        $estudio->update($updateData);

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'estudios'])
            ->with('exito', 'Estudio actualizado correctamente.');
    }

    public function storePedidoHemoderivado(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'observacionesPedidoHemoderivados' => ['nullable', 'string'],
            'componentes' => ['required', 'array', 'min:1'],
            'componentes.*.idTipoHemoderivado' => ['required', 'exists:TipoHemoderivado,idTipoHemoderivado'],
            'componentes.*.idEstablecimiento' => ['required', 'exists:Establecimiento,idEstablecimiento'],
            'componentes.*.cantidad' => ['required', 'integer', 'min:1'],
            'componentes.*.descripcion' => ['nullable', 'string'],
        ]);

        $pedido = PedidoHemoderivado::create([
            'idCirugia' => $cirugia->idCirugia,
            'observacionesPedidoHemoderivados' => $datos['observacionesPedidoHemoderivados'] ?? null,
            'fechaPedidoHemoderivado' => now(),
        ]);

        $estadoSolicitado = EstadoPedidoHemoderivado::where('nombreEstadoPedidoHemoderivado', 'Solicitado')->value('idEstadoPedidoHemoderivado');

        PedidoHemoderivadoEstado::create([
            'idPedidoHemoderivado' => $pedido->idPedidoHemoderivado,
            'idEstadoPedidoHemoderivado' => $estadoSolicitado,
            'fechaAsignacionPedidoHemoderivadoEstado' => now(),
        ]);

        $estadoComponenteSolicitado = EstadoPedidoTipoHemoderivado::where('nombreEstadoPedidoTipoHemoderivado', 'Solicitado')->value('idEstadoPedidoTipoHemoderivado');

        foreach ($datos['componentes'] as $comp) {
            $componente = PedidoTipoHemoderivado::create([
                'idPedidoHemoderivado' => $pedido->idPedidoHemoderivado,
                'idTipoHemoderivado' => $comp['idTipoHemoderivado'],
                'idEstablecimiento' => $comp['idEstablecimiento'],
                'cantidadPedidoTipoHemoderivado' => $comp['cantidad'],
                'descripcionPedidoTipoHemoderivado' => $comp['descripcion'] ?? null,
            ]);

            PedidoTipoHemoderivadoEstado::create([
                'idPedidoTipoHemoderivado' => $componente->idPedidoTipoHemoderivado,
                'idEstadoPedidoTipoHemoderivado' => $estadoComponenteSolicitado,
                'fechaAsignacionPedidoTipoHemoderivadoEstado' => now(),
            ]);
        }

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'hemoderivados'])
            ->with('exito', 'Pedido de hemoderivados creado con sus componentes.');
    }

    /**
     * Actualiza el estado de un componente de hemoderivado (Solicitado, Reservado, Condicional, Entregado, etc.).
     */
    public function actualizarEstadoComponenteHemoderivado(Request $request, Cirugia $cirugia, PedidoTipoHemoderivado $componente): RedirectResponse
    {
        // Validar que el componente pertenece a un pedido de esta cirugía
        if ($componente->pedidoHemoderivado->idCirugia !== $cirugia->idCirugia) {
            abort(404);
        }

        $datos = $request->validate([
            'idEstadoPedidoTipoHemoderivado' => ['required', 'exists:EstadoPedidoTipoHemoderivado,idEstadoPedidoTipoHemoderivado'],
        ]);

        $estadoAnterior = $componente->pedidoTipoHemoderivadoEstados()
            ->whereNull('fechaFinAsignacionPedidoTipoHemoderivadoEstado')
            ->latest('idPedidoTipoHemoderivadoEstado')
            ->first();

        // Si ya está en ese estado, no hacemos nada
        if ($estadoAnterior && $estadoAnterior->idEstadoPedidoTipoHemoderivado == $datos['idEstadoPedidoTipoHemoderivado']) {
            return back();
        }

        if ($estadoAnterior) {
            $estadoAnterior->update(['fechaFinAsignacionPedidoTipoHemoderivadoEstado' => now()]);
        }

        $componente->pedidoTipoHemoderivadoEstados()->create([
            'idEstadoPedidoTipoHemoderivado' => $datos['idEstadoPedidoTipoHemoderivado'],
            'fechaAsignacionPedidoTipoHemoderivadoEstado' => now(),
        ]);

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'hemoderivados'])
            ->with('exito', 'Estado del componente actualizado correctamente.');
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
