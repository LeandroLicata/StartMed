<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltraCirugias;
use App\Models\AutCirugia;
use App\Models\AutoCirugiaEstado;
use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\CirugiaPersonal;
use App\Models\CirugiaQuirofano;
use App\Models\CirugiaTipoEstudio;
use App\Models\Establecimiento;
use App\Models\EstadoAutCirugia;
use App\Models\EstadoCirugia;
use App\Models\EstadoHisopadoSarm;
use App\Models\EstadoPedidoHemoderivado;
use App\Models\EstadoPedidoMaterial;
use App\Models\EstadoPedidoTipoHemoderivado;
use App\Models\HisopadoSarm;
use App\Models\HisopadoSarmEstado;
use App\Models\Material;
use App\Models\MaterialProveedorTipoMedida;
use App\Models\ObraSocial;
use App\Models\PedidoHemoderivado;
use App\Models\PedidoHemoderivadoEstado;
use App\Models\PedidoMaterial;
use App\Models\PedidoMaterialEstado;
use App\Models\PedidoTipoHemoderivado;
use App\Models\PedidoTipoHemoderivadoEstado;
use App\Models\Personal;
use App\Models\PreparacionPaciente;
use App\Models\PreparacionPacienteTipoPreparacion;
use App\Models\PreparacionPacienteTipoPreparacionTipoIndicacion;
use App\Models\Profilaxis;
use App\Models\ProfilaxisAtbHisopadoSarm;
use App\Models\ProfilaxisAtbHisopadoSarmProfilaxis;
use App\Models\ProfilaxisRol;
use App\Models\Quirofano;
use App\Models\Rol;
use App\Models\TipoEstudio;
use App\Models\TipoHemoderivado;
use App\Models\TipoIndicacion;
use App\Models\TipoPreparacion;
use App\Services\ReprogramarCirugiaService;
use App\Support\DocumentoNoDisponible;
use App\Support\GestorDocumental;
use App\Support\Paginador;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CirugiaController extends Controller
{
    use FiltraCirugias;

    private const TABS = ['resumen', 'preparacion', 'estudios', 'materiales', 'hemoderivados', 'profilaxis', 'autorizacion'];

    private const POR_PAGINA = 20;

    /**
     * Adjuntos clínicos del expediente (resultados de estudios y de hisopados).
     * Un solo juego de reglas para los dos: son el mismo tipo de documento y no
     * hay razón para que uno acepte lo que el otro rechaza.
     */
    private const REGLAS_ARCHIVO = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'];

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

        return view('cirugias.index', array_merge([
            'cirugias' => Paginador::deColeccion(
                $this->aplicarFiltros($query, $request),
                $request,
                self::POR_PAGINA,
            ),
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
            'profilaxisOpciones' => Profilaxis::whereNull('fechaBajaProfilaxis')
                ->orderBy('nombreProfilaxis')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->idProfilaxis => $p->nombreProfilaxis]),
            'profilaxisRoles' => ProfilaxisRol::whereNull('fechaBajaProfilaxisRol')
                ->orderBy('nombreProfilaxisRol')
                ->get()
                ->mapWithKeys(fn ($r) => [$r->idProfilaxisRol => $r->nombreProfilaxisRol]),
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
            'tiposPreparacion' => TipoPreparacion::whereNull('fechaBajaTipoPreparacion')
                ->orderBy('idTipoPreparacion')
                ->get(),
            'tiposIndicacion' => TipoIndicacion::whereNull('fechaBajaTipoIndicacion')
                ->orderBy('idTipoIndicacion')
                ->get(),
        ]);
    }

    /**
     * Crea o reemplaza las indicaciones de preparación prequirúrgica de la cirugía.
     *
     * Recibe un array `indicaciones[idTipoPreparacion][idTipoIndicacion] = horas`
     * (solo las indicaciones que el usuario marcó como activas) y una
     * observación general opcional.
     *
     * La estrategia es "delete + insert": se borran todos los bloques
     * existentes y se vuelven a crear con los valores nuevos, lo que evita
     * tener que rastrear qué cambió individualmente.
     */
    public function guardarPreparacion(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'observacionesPreparacionPaciente' => ['nullable', 'string', 'max:255'],
            'indicaciones' => ['nullable', 'array'],
            'indicaciones.*' => ['array'],
            'indicaciones.*.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // Buscar o crear el registro raíz de preparación.
        $preparacion = PreparacionPaciente::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            ['observacionesPreparacionPaciente' => null],
        );

        $preparacion->update([
            'observacionesPreparacionPaciente' => $datos['observacionesPreparacionPaciente'] ?? null,
        ]);

        // Borrar todos los bloques actuales (cascada hacia las indicaciones).
        $preparacion->preparacionPacienteTipoPreparaciones()->each(function ($bloque) {
            $bloque->preparacionPacienteTipoPreparacionTipoIndicaciones()->delete();
            $bloque->delete();
        });

        // Recrear con lo que vino del formulario.
        foreach ($datos['indicaciones'] ?? [] as $idTipoPreparacion => $indicaciones) {
            if (empty($indicaciones)) {
                continue;
            }

            $bloque = PreparacionPacienteTipoPreparacion::create([
                'idPreparacionPaciente' => $preparacion->idPreparacionPaciente,
                'idTipoPreparacion' => $idTipoPreparacion,
            ]);

            foreach ($indicaciones as $idTipoIndicacion => $horas) {
                PreparacionPacienteTipoPreparacionTipoIndicacion::create([
                    'idPreparacionPacienteTipoPreparacion' => $bloque->idPreparacionPacienteTipoPreparacion,
                    'idTipoIndicacion' => $idTipoIndicacion,
                    'hsReglaCantidadIngestaAnteriorCirugia' => $horas !== '' ? (int) $horas : null,
                ]);
            }
        }

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'resumen'])
            ->with('exito', 'Indicaciones de preparación actualizadas.');
    }

    /**
     * Actualiza el laboratorio, la fecha estimada de resultados y las
     * observaciones del hisopado SAMR de una cirugía.
     */
    public function actualizarHisopado(Request $request, Cirugia $cirugia, GestorDocumental $gestor): RedirectResponse
    {
        $datos = $request->validate([
            'idEstablecimiento' => ['nullable', 'exists:Establecimiento,idEstablecimiento'],
            'fechaEstimadaResultadosHisopadoSarm' => ['nullable', 'date'],
            'observacionesHisopadoSarm' => ['nullable', 'string', 'max:255'],
            'archivoHisopadoSarm' => self::REGLAS_ARCHIVO,
        ]);

        $hisopado = HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->firstOrFail();
        $subioArchivo = $request->hasFile('archivoHisopadoSarm');

        unset($datos['archivoHisopadoSarm']);

        try {
            $datos += $this->punteroDelArchivo($request, $cirugia, $gestor);
        } catch (DocumentoNoDisponible $e) {
            report($e);

            return back()->with('error', 'No se pudo guardar el archivo en el gestor documental. Probá de nuevo en unos minutos.');
        }

        $hisopado->update($datos);

        $mensaje = $subioArchivo
            ? 'Datos del hisopado actualizados y archivo subido correctamente.'
            : 'Datos del hisopado actualizados.';

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'profilaxis'])
            ->with('exito', $mensaje);
    }

    /**
     * Registra el resultado del hisopado SAMR (Positivo o Negativo).
     *
     * Cierra el estado vigente (fechaFin = now()) y abre uno nuevo,
     * siguiendo el patron de date-ranges del sistema.
     */
    public function actualizarEstadoHisopado(Request $request, Cirugia $cirugia, GestorDocumental $gestor): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:Negativo,Positivo'],
            'observacionesHisopadoSarm' => ['nullable', 'string', 'max:255'],
            'archivoHisopadoSarm' => self::REGLAS_ARCHIVO,
        ]);

        $hisopado = HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->firstOrFail();

        /*
         * El archivo se sube antes de tocar los estados: si el gestor falla, el
         * hisopado no puede quedar registrado como Negativo con el adjunto
         * perdido. Un resultado de SAMR decide el protocolo antibiótico.
         */
        try {
            $adjunto = $this->punteroDelArchivo($request, $cirugia, $gestor);
        } catch (DocumentoNoDisponible $e) {
            report($e);

            return back()->with('error', 'No se pudo guardar el archivo en el gestor documental. El resultado no se registró.');
        }

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

        // Guardar observaciones y adjunto si vienen.
        $cambios = $adjunto;

        if (! empty($datos['observacionesHisopadoSarm'])) {
            $cambios['observacionesHisopadoSarm'] = $datos['observacionesHisopadoSarm'];
        }

        if ($cambios !== []) {
            $hisopado->update($cambios);
        }

        $mensaje = $adjunto !== []
            ? 'Resultado del hisopado registrado y archivo subido correctamente.'
            : 'Resultado del hisopado registrado correctamente.';

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'profilaxis'])
            ->with('exito', $mensaje);
    }

    /**
     * Redirige al adjunto del hisopado en el gestor documental.
     *
     * Mismo criterio que verArchivoEstudio(): la URL se firma acá y vence.
     */
    public function verArchivoHisopado(Cirugia $cirugia, GestorDocumental $gestor): RedirectResponse
    {
        $hisopado = HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->firstOrFail();

        if (blank($hisopado->urlHisopadoSarm)) {
            abort(404);
        }

        try {
            return redirect()->away($gestor->urlTemporal($hisopado->urlHisopadoSarm));
        } catch (DocumentoNoDisponible $e) {
            report($e);

            return redirect()
                ->route('cirugias.show', [$cirugia, 'tab' => 'profilaxis'])
                ->with('error', 'El archivo no está disponible en el gestor documental.');
        }
    }

    /**
     * Sube el adjunto del hisopado, si el formulario lo trajo.
     *
     * Devuelve los campos a mergear en el update — vacío cuando no vino archivo,
     * para que guardar el formulario sin adjuntar nada no borre el que ya estaba.
     *
     * @return array{urlHisopadoSarm?: string}
     *
     * @throws DocumentoNoDisponible
     */
    private function punteroDelArchivo(Request $request, Cirugia $cirugia, GestorDocumental $gestor): array
    {
        if (! $request->hasFile('archivoHisopadoSarm')) {
            return [];
        }

        return [
            'urlHisopadoSarm' => $gestor->guardar(
                $request->file('archivoHisopadoSarm'),
                'hisopados/'.$cirugia->idCirugia,
            ),
        ];
    }

    public function agregarProfilaxis(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'idProfilaxis' => ['required', 'exists:Profilaxis,idProfilaxis'],
            'idProfilaxisRol' => ['required', 'exists:ProfilaxisRol,idProfilaxisRol'],
            'indicaciones' => ['nullable', 'string', 'max:255'],
        ]);

        $hisopado = HisopadoSarm::firstOrCreate([
            'idCirugia' => $cirugia->idCirugia,
        ]);

        $profilaxisSarm = ProfilaxisAtbHisopadoSarm::firstOrCreate([
            'idHisopadoSarm' => $hisopado->idHisopadoSarm,
        ]);

        ProfilaxisAtbHisopadoSarmProfilaxis::create([
            'idProfilaxisAtbHisopadoSarm' => $profilaxisSarm->idProfilaxisAtbHisopadoSarm,
            'idProfilaxis' => $datos['idProfilaxis'],
            'idProfilaxisRol' => $datos['idProfilaxisRol'],
            'indicacionesProfilaxisAtbHisopadoSarmProfilaxis' => $datos['indicaciones'],
        ]);

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'profilaxis'])
            ->with('exito', 'Profilaxis agregada correctamente.');
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

        $autorizacion = AutCirugia::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            ['idPlan' => $cirugia->paciente?->planObraSociales?->first()?->idPlan]
        );

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
    public function actualizarEstudio(Request $request, Cirugia $cirugia, CirugiaTipoEstudio $estudio, GestorDocumental $gestor): RedirectResponse
    {
        // Validar que el estudio pertenece a la cirugía
        if ($estudio->idCirugia !== $cirugia->idCirugia) {
            abort(404);
        }

        $datos = $request->validate([
            'fechaEsperadaResultadoCirugiaTipoEstudio' => ['nullable', 'date'],
            'resultadoCirugiaTipoEstudio' => ['nullable', 'string'],
            'archivoResultadoEstudio' => self::REGLAS_ARCHIVO,
        ]);

        /*
         * Dos formularios distintos pegan acá: el de subir archivo manda solo el
         * archivo, y el modal de gestionar manda solo fecha y resultado. Así que
         * se toca únicamente lo que vino — un `?? null` de arriba abajo hacía que
         * subir el resultado borrara la fecha esperada y el texto del estudio.
         */
        $cambios = array_intersect_key($datos, array_flip([
            'fechaEsperadaResultadoCirugiaTipoEstudio',
            'resultadoCirugiaTipoEstudio',
        ]));

        $subioArchivo = $request->hasFile('archivoResultadoEstudio');

        if ($subioArchivo) {
            /*
             * El puntero al documento y la fecha de subida se escriben juntos: si
             * el gestor falla no queremos el estudio marcado como «Subido» sin
             * archivo detrás, que es lo que hacía la versión anterior.
             */
            try {
                $cambios['urlArchivoCirugiaTipoEstudio'] = $gestor->guardar(
                    $request->file('archivoResultadoEstudio'),
                    'estudios/'.$cirugia->idCirugia,
                );
            } catch (DocumentoNoDisponible $e) {
                report($e);

                return back()->with('error', 'No se pudo guardar el archivo en el gestor documental. Probá de nuevo en unos minutos.');
            }

            $cambios['fechaSubidaCirugiaTipoEstudio'] = now();
        }

        $estudio->update($cambios);

        // El botón de subida manda un solo campo y dispara este mismo endpoint:
        // el mensaje tiene que confirmar el archivo, no un genérico "actualizado"
        // que no dice si lo que se guardó fue el PDF o sólo la fecha.
        $mensaje = $subioArchivo
            ? 'Archivo subido correctamente al gestor documental.'
            : 'Estudio actualizado correctamente.';

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'estudios'])
            ->with('exito', $mensaje);
    }

    /**
     * Redirige al resultado del estudio en el gestor documental.
     *
     * El puntero que guarda la base nunca sale al HTML: la URL se firma acá, con
     * la sesión y el rol ya verificados por el middleware, y vence en minutos.
     * Es lo que hace que copiar el link de un hemograma no lo deje público.
     */
    public function verArchivoEstudio(Cirugia $cirugia, CirugiaTipoEstudio $estudio, GestorDocumental $gestor): RedirectResponse
    {
        if ($estudio->idCirugia !== $cirugia->idCirugia || blank($estudio->urlArchivoCirugiaTipoEstudio)) {
            abort(404);
        }

        try {
            return redirect()->away($gestor->urlTemporal($estudio->urlArchivoCirugiaTipoEstudio));
        } catch (DocumentoNoDisponible $e) {
            report($e);

            return redirect()
                ->route('cirugias.show', [$cirugia, 'tab' => 'estudios'])
                ->with('error', 'El archivo no está disponible en el gestor documental.');
        }
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

    public function buscarMateriales(Request $request)
    {
        $q = $request->query('q');
        $materiales = Material::whereNull('fechaBajaMaterial')
            ->where(function ($query) use ($q) {
                $query->where('nombreMaterial', 'like', "%{$q}%")
                    ->orWhere('codMaterial', 'like', "%{$q}%");
            })
            ->take(20)
            ->get(['idMaterial', 'nombreMaterial', 'codMaterial']);

        return response()->json($materiales);
    }

    public function proveedoresMaterial(Material $material)
    {
        $proveedores = $material->materialProveedores()->with([
            'proveedor:idProveedor,nombreProveedor',
            'materialProveedorTipoMedidas' => function ($q) {
                $q->whereNull('fechaFinAsignacionMaterialTipoMedida')
                    ->where('disponibleMaterialTipoMedida', 1);
            },
            'materialProveedorTipoMedidas.tipoMedida:idTipoMedida,nombreTipoMedida',
        ])->get();

        return response()->json($proveedores);
    }

    public function storePedidoMaterial(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $datos = $request->validate([
            'idMaterial' => ['required', 'exists:Material,idMaterial'],
            'idProveedor' => ['required', 'exists:Proveedor,idProveedor'],
            'idTipoMedida' => ['required', 'exists:TipoMedida,idTipoMedida'],
            'cantidadPedidoMaterial' => ['required', 'integer', 'min:1'],
        ]);

        $unitario = $this->precioVigente(
            $datos['idMaterial'],
            $datos['idProveedor'],
            $datos['idTipoMedida'],
        );

        $pedido = PedidoMaterial::create([
            'idCirugia' => $cirugia->idCirugia,
            'idMaterial' => $datos['idMaterial'],
            'idProveedor' => $datos['idProveedor'],
            'idTipoMedida' => $datos['idTipoMedida'],
            'cantidadPedidoMaterial' => $datos['cantidadPedidoMaterial'],
            // El unitario queda copiado en el pedido: es lo que se cotizo hoy.
            'precioUnitarioPedidoMaterial' => $unitario,
            'subtotalPedidoMaterial' => $unitario * $datos['cantidadPedidoMaterial'],
            'fechaPedidoMaterial' => now(),
        ]);

        $estadoSolicitado = EstadoPedidoMaterial::where('nombreEstadoPedidoMaterial', 'Solicitado')->value('idEstadoPedidoMaterial');

        if ($estadoSolicitado) {
            PedidoMaterialEstado::create([
                'idPedidoMaterial' => $pedido->idPedidoMaterial,
                'idEstadoPedidoMaterial' => $estadoSolicitado,
            ]);
        }

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'materiales'])
            ->with('exito', 'Material agregado al pedido.');
    }

    public function updatePedidoMaterial(Request $request, Cirugia $cirugia, PedidoMaterial $pedido): RedirectResponse
    {
        $datos = $request->validate([
            'cantidadPedidoMaterial' => ['required', 'integer', 'min:1'],
        ]);

        // Se recalcula con el unitario que el pedido tiene congelado, no con la
        // lista de precios de hoy: cambiar la cantidad de un pedido de hace un
        // mes no puede traerle el aumento del proveedor.
        $unitario = $pedido->precioUnitarioPedidoMaterial
            // Pedidos anteriores a que existiera la columna: se deduce de lo
            // que ya se habia cotizado, sin volver a la lista de precios.
            ?? ($pedido->cantidadPedidoMaterial > 0
                ? $pedido->subtotalPedidoMaterial / $pedido->cantidadPedidoMaterial
                : 0);

        $pedido->update([
            'cantidadPedidoMaterial' => $datos['cantidadPedidoMaterial'],
            'precioUnitarioPedidoMaterial' => $unitario,
            'subtotalPedidoMaterial' => $unitario * $datos['cantidadPedidoMaterial'],
        ]);

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'materiales'])
            ->with('exito', 'Cantidad de material actualizada.');
    }

    /**
     * Lo que ese proveedor cobra hoy por ese material en esa unidad.
     *
     * El precio depende de la presentacion, no del proveedor: el implante de
     * 0,5 m y el de 1 m son dos articulos distintos. Sin precio cargado el
     * pedido no se puede valorizar, y dejarlo pasar guardaria un subtotal cero
     * que nadie vuelve a mirar.
     */
    private function precioVigente(int $idMaterial, int $idProveedor, int $idTipoMedida): float
    {
        $precio = MaterialProveedorTipoMedida::query()
            ->whereHas('materialProveedor', fn ($q) => $q
                ->where('idMaterial', $idMaterial)
                ->where('idProveedor', $idProveedor))
            ->where('idTipoMedida', $idTipoMedida)
            ->whereNull('fechaFinAsignacionMaterialTipoMedida')
            ->value('precioExternoMaterialProveedorTipoMedida');

        if ($precio === null) {
            throw ValidationException::withMessages([
                'idTipoMedida' => 'Ese proveedor no tiene precio cargado para este material en esa unidad.',
            ]);
        }

        return (float) $precio;
    }

    public function destroyPedidoMaterial(Cirugia $cirugia, PedidoMaterial $pedido): RedirectResponse
    {
        // Se podrían eliminar los estados si hubiera foreign keys con cascade,
        // pero por precaución los borramos manualmente primero.
        PedidoMaterialEstado::where('idPedidoMaterial', $pedido->idPedidoMaterial)->delete();
        $pedido->delete();

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'materiales'])
            ->with('exito', 'Material eliminado del pedido.');
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

    public function personalDisponible(Request $request, Cirugia $cirugia)
    {
        $rol = $request->query('rol'); // 'Cirujano' o 'Anestesista'
        abort_unless(in_array($rol, ['Cirujano', 'Anestesista']), 400, 'Rol inválido.');

        $inicio = $cirugia->fechaHoraCirugia;
        $fin = $cirugia->fechaHoraFinCirugia ?? $inicio->copy()->addMinutes(120);

        // Obtenemos todos los profesionales con el rol
        $personal = Personal::with('persona')
            ->whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', $rol))
            ->get();

        // Filtramos por disponibilidad
        $disponibles = $personal->filter(function ($p) use ($rol, $inicio, $fin, $cirugia) {
            $columna = 'idPersonal'.$rol;

            // Buscar otras cirugias en ese horario para este profesional
            $otrasCirugias = Cirugia::where($columna, $p->idPersonal)
                ->where('idCirugia', '!=', $cirugia->idCirugia)
                ->with('cirugiaEstados.estadoCirugia')
                ->get();

            $ocupado = $otrasCirugias->contains(function (Cirugia $otra) use ($inicio, $fin) {
                $otroFin = $otra->fechaHoraFinCirugia ?? $otra->fechaHoraCirugia->copy()->addMinutes(120);

                if (! ($inicio->lt($otroFin) && $otra->fechaHoraCirugia->lt($fin))) {
                    return false;
                }

                $vigente = $otra->cirugiaEstados->firstWhere('fechaDesasignacionCirugiaEstado', null);

                return $vigente?->estadoCirugia?->nombreEstadoCirugia !== 'Suspendida';
            });

            return ! $ocupado;
        });

        // Mapeamos a json (id, nombre completo)
        $resultado = $disponibles->map(function ($p) {
            return [
                'id' => $p->idPersonal,
                'nombre' => $p->persona->nombre_completo,
            ];
        })->values();

        return response()->json($resultado);
    }

    public function reasignarPersonal(Request $request, Cirugia $cirugia)
    {
        $datos = $request->validate([
            'rol' => 'required|in:Cirujano,Anestesista',
            'idPersonal' => 'required|exists:Personal,idPersonal',
        ]);

        $rol = $datos['rol'];
        $idPersonal = $datos['idPersonal'];

        // Doble validación de disponibilidad
        $inicio = $cirugia->fechaHoraCirugia;
        $fin = $cirugia->fechaHoraFinCirugia ?? $inicio->copy()->addMinutes(120);
        $columna = 'idPersonal'.$rol;

        $otrasCirugias = Cirugia::where($columna, $idPersonal)
            ->where('idCirugia', '!=', $cirugia->idCirugia)
            ->with('cirugiaEstados.estadoCirugia')
            ->get();

        $ocupado = $otrasCirugias->contains(function (Cirugia $otra) use ($inicio, $fin) {
            $otroFin = $otra->fechaHoraFinCirugia ?? $otra->fechaHoraCirugia->copy()->addMinutes(120);

            if (! ($inicio->lt($otroFin) && $otra->fechaHoraCirugia->lt($fin))) {
                return false;
            }

            $vigente = $otra->cirugiaEstados->firstWhere('fechaDesasignacionCirugiaEstado', null);

            return $vigente?->estadoCirugia?->nombreEstadoCirugia !== 'Suspendida';
        });

        if ($ocupado) {
            return back()->withErrors(['idPersonal' => 'El profesional seleccionado ya no está disponible en ese horario.']);
        }

        // Actualizamos
        $cirugia->update([$columna => $idPersonal]);

        $idRol = Rol::where('nombreRol', $rol)->value('idRol');

        // Cerramos la asignación anterior vigente de este rol
        CirugiaPersonal::where('idCirugia', $cirugia->idCirugia)
            ->where('idRol', $idRol)
            ->whereNull('fechaFinAsignacionCirugiaPersonal')
            ->update(['fechaFinAsignacionCirugiaPersonal' => now()]);

        // Guardamos historial
        CirugiaPersonal::create([
            'idCirugia' => $cirugia->idCirugia,
            'idPersonal' => $idPersonal,
            'idRol' => $idRol,
            'fechaInicioAsignacionCirugiaPersonal' => now(),
        ]);

        return back()->with('exito', "{$rol} reasignado correctamente.");
    }

    /**
     * Agrega un requerimiento a la cirugía (implante, hemoderivados, hisopado)
     */
    public function agregarRequerimiento(Request $request, Cirugia $cirugia)
    {
        $datos = $request->validate([
            'requerimiento' => 'required|in:implante,hemoderivados,hisopado',
        ]);

        $req = $datos['requerimiento'];

        DB::transaction(function () use ($cirugia, $req) {
            if ($req === 'implante') {
                $cirugia->update(['requiereImplante' => true]);
            } elseif ($req === 'hemoderivados') {
                PedidoHemoderivado::firstOrCreate([
                    'idCirugia' => $cirugia->idCirugia,
                ], [
                    'fechaPedidoHemoderivado' => now(),
                ]);
            } elseif ($req === 'hisopado') {
                $hisopado = HisopadoSarm::firstOrCreate([
                    'idCirugia' => $cirugia->idCirugia,
                ], [
                    'fechaSolicitacionHisopadoSarm' => now(),
                ]);

                if ($hisopado->wasRecentlyCreated) {
                    HisopadoSarmEstado::create([
                        'idHisopadoSarm' => $hisopado->idHisopadoSarm,
                        'idEstadoHisopadoSarm' => EstadoHisopadoSarm::where('nombreEstadoHisopadoSarm', 'Pendiente')
                            ->value('idEstadoHisopadoSarm'),
                        'fechaInicioAsignacionHisopadoSarmEstado' => now(),
                    ]);
                }
            }
        });

        return back()->with('exito', 'Requerimiento agregado correctamente.');
    }

    /**
     * Cancela la cirugía y libera los turnos.
     */
    public function cancelar(Cirugia $cirugia)
    {
        DB::transaction(function () use ($cirugia) {
            // Cancelar la cirugia (EstadoCirugia = Cancelado o similar, si no existe Cancelado, buscamos Suspendida o lo creamos, vamos a asumir 'Suspendida' o 'Cancelado'. En otro metodo usaron 'Suspendida')
            // Voy a usar 'Cancelada' si existe, o 'Suspendida'. Vamos a usar el ID correspondiente.
            $estadoSuspendida = EstadoCirugia::whereIn('nombreEstadoCirugia', ['Cancelada', 'Cancelado', 'Suspendida'])->first();

            if ($estadoSuspendida) {
                // Cerrar estado actual
                CirugiaEstado::where('idCirugia', $cirugia->idCirugia)
                    ->whereNull('fechaDesasignacionCirugiaEstado')
                    ->update(['fechaDesasignacionCirugiaEstado' => now()]);

                CirugiaEstado::create([
                    'idCirugia' => $cirugia->idCirugia,
                    'idEstadoCirugia' => $estadoSuspendida->idEstadoCirugia,
                    'fechaAsignacionCirugiaEstado' => now(),
                ]);
            }

            // Liberar quirófano
            CirugiaQuirofano::where('idCirugia', $cirugia->idCirugia)
                ->whereNull('fechaDesasignacionCirugiaQuirofano')
                ->update(['fechaDesasignacionCirugiaQuirofano' => now()]);

            // Liberar personal
            CirugiaPersonal::where('idCirugia', $cirugia->idCirugia)
                ->whereNull('fechaFinAsignacionCirugiaPersonal')
                ->update(['fechaFinAsignacionCirugiaPersonal' => now()]);
        });

        return redirect()->route('cirugias.show', $cirugia)->with('exito', 'La cirugía ha sido cancelada y los horarios liberados.');
    }
}
