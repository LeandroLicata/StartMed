<?php

namespace App\Support;

use App\Models\AutoCirugiaEstado;
use App\Models\Cirugia;
use App\Models\CirugiaPersonal;
use App\Models\ConsentimientoPaciente;
use App\Models\HisopadoSarm;
use App\Models\PedidoHemoderivado;
use App\Models\Persona;
use App\Models\Plan;
use App\Models\ProfilaxisAtbHisopadoSarmProfilaxis;
use App\Models\Quirofano;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vista de lectura de una cirugia: junta el estado vigente de cada modulo
 * (autorizacion, estudios, anestesia, materiales) y responde si el caso esta
 * en condiciones de realizarse.
 *
 * El esquema guarda los estados como historial con rangos de fecha, asi que
 * "vigente" siempre significa el registro cuya fecha de fin es NULL.
 *
 * Espera la cirugia con sus relaciones ya cargadas: no dispara consultas.
 */
class ResumenCirugia
{
    /**
     * Todo lo que esta clase lee. Se usa como `Cirugia::with(ResumenCirugia::RELACIONES)`
     * para que ningun acceso caiga en una consulta perezosa.
     */
    public const RELACIONES = [
        'paciente.tipoDocumento',
        'paciente.grupoSanguineo',
        'paciente.planObraSociales.plan.obraSocial',
        'cirujano.persona',
        'anestesista.persona',
        'tipoCirugia',
        'cirugiaEstados.estadoCirugia',
        'cirugiaQuirofanos.quirofano',
        'cirugiaTipoEstudios.tipoEstudio',
        'autCirugias.plan.obraSocial',
        'autCirugias.autoCirugiaEstados.estadoAutCirugia',
        'pedidoMateriales.material',
        'pedidoMateriales.proveedor',
        'pedidoMateriales.tipoMedida',
        'pedidoMateriales.pedidoMaterialEstados.estadoPedidoMaterial',
        'evaluacionAnestesicas.evaluacionAnestesicaEstados.estadoEvaluacionAnestesica',
        'evaluacionAnestesicas.evaluacionTipoAsas.tipoAsa',
        'evaluacionAnestesicas.evaluacionTipoAnestesias.tipoAnestesia',
        // pendientes()/estaLista() la necesitan, asi que va en la base y no en
        // RELACIONES_EXPEDIENTE (si no, cada cirugia del tablero dispara una
        // consulta aparte al leer consentimientoFirmado()).
        'consentimientoPacientes.configConsentimiento',
    ];

    /** Relaciones adicionales que solo hacen falta en el expediente completo. */
    public const RELACIONES_EXPEDIENTE = [
        'cirugiaPersonales.personal.persona',
        'cirugiaPersonales.rol',
        'pedidoHemoderivados.pedidoTipoHemoderivados.tipoHemoderivado',
        'pedidoHemoderivados.pedidoTipoHemoderivados.establecimiento',
        'hisopadoSarms.establecimiento',
        'hisopadoSarms.hisopadoSarmEstados.estadoHisopadoSarm',
        'hisopadoSarms.profilaxisAtbHisopadoSarms.profilaxisAtbHisopadoSarmProfilaxis.profilaxis',
        'hisopadoSarms.profilaxisAtbHisopadoSarms.profilaxisAtbHisopadoSarmProfilaxis.profilaxisRol',
        'preparacionPacientes.preparacionPacienteTipoPreparaciones.tipoPreparacion',
        'preparacionPacientes.preparacionPacienteTipoPreparaciones.preparacionPacienteTipoPreparacionTipoIndicaciones.tipoIndicacion',
        'examenCirugiaPreAnestesicas.examenPreAnestesicoConfiges.examenPreAnestesicoConfigPreguntas.configTipoExamenPreAnestesicoPregunta',
        'examenCirugiaPreAnestesicas.examenPreAnestesicoConfiges.examenPreAnestesicoConfigPreguntas.examenPreAnestesicoConfigPreguntaRespuestas.configTipoExamenPreAnestesicoPreguntaRespuesta',
    ];

    /** Cuanto antes de la cirugia tiene que llegar el paciente. */
    public const MINUTOS_ANTICIPACION_LLEGADA = 30;

    /** Desde cuantas horas antes de la cirugia se le pide al paciente que confirme. */
    public const HORAS_CONFIRMACION_ASISTENCIA = 48;

    /**
     * Estados en los que ya no tiene sentido preguntarle si viene: la cirugia
     * termino, se cayo o el paciente ya respondio. Va por exclusion y no por
     * lista de los que si, porque estos nombres ya estan en los protegidos de
     * Catalogos; la lista contraria obligaria a proteger tambien 'Programada'.
     */
    public const ESTADOS_SIN_CONFIRMACION = [
        'Realizada', 'Suspendida', 'Reprogramada',
        'A reprogramar', 'Asistencia de paciente confirmada',
    ];

    public readonly ?Quirofano $quirofano;

    public readonly ?Persona $paciente;

    public readonly ?Plan $plan;

    public int $overlapCol = 0;

    public int $overlapTotal = 1;

    public function __construct(public readonly Cirugia $cirugia)
    {
        $this->paciente = $cirugia->paciente;

        $this->quirofano = $cirugia->cirugiaQuirofanos
            ->firstWhere('fechaHoraDesasignacion', null)?->quirofano;

        $this->plan = $cirugia->autCirugias->first()?->plan
            ?? $cirugia->paciente?->planObraSociales?->first()?->plan;
    }

    public function cuando(): ?Carbon
    {
        return $this->cirugia->fechaHoraCirugia;
    }

    public function fin(): ?Carbon
    {
        return $this->cirugia->fechaHoraFinCirugia;
    }

    /**
     * A que hora tiene que llegar el paciente. No hay columna: es una regla
     * fija sobre la hora de la cirugia.
     */
    public function horaLlegada(): ?Carbon
    {
        return $this->cuando()?->copy()->subMinutes(self::MINUTOS_ANTICIPACION_LLEGADA);
    }

    public function minutosDesdeMedianoche(): int
    {
        if (! $this->cuando()) {
            return 0;
        }

        return ($this->cuando()->hour * 60) + $this->cuando()->minute;
    }

    public function duracionEnMinutos(): int
    {
        if (! $this->cuando()) {
            return 120; // Default 2 hours if no start
        }
        if (! $this->fin()) {
            return 120; // Default 2 hours
        }

        return $this->cuando()->diffInMinutes($this->fin());
    }

    public function esDeHoy(): bool
    {
        return $this->cuando()?->isToday() ?? false;
    }

    /**
     * Una cirugia de un dia anterior: en el portal se mira, no se completa.
     * Es la misma regla que separa Proximas de Historial, y la que cada
     * formulario del portal tiene que volver a mirar del lado del servidor.
     * Sin fecha cuenta como anterior: no hay nada que preparar para ella.
     */
    public function esAnterior(): bool
    {
        return $this->cuando()?->lt(today()) ?? true;
    }

    public function procedimiento(): string
    {
        return $this->cirugia->tipoCirugia?->nombreTipoCirugia
            ?? $this->cirugia->descripcionCirugia
            ?? 'Sin procedimiento';
    }

    public function nombrePaciente(): string
    {
        return $this->paciente?->nombre_completo ?? 'Paciente sin datos';
    }

    public function cirujano(): ?string
    {
        return $this->cirugia->cirujano?->persona?->nombre_completo;
    }

    public function anestesista(): ?string
    {
        return $this->cirugia->anestesista?->persona?->nombre_completo;
    }

    // --- Estado general -----------------------------------------------------

    public function estado(): string
    {
        return $this->cirugia->cirugiaEstados
            ->firstWhere('fechaDesasignacionCirugiaEstado', null)
            ?->estadoCirugia?->nombreEstadoCirugia ?? 'Sin estado';
    }

    // --- Autorizacion del acto quirurgico -----------------------------------

    public function autorizacion(): string
    {
        if ($this->plan?->es_sin_cobertura) {
            return 'Sin cobertura';
        }

        $autorizacion = $this->cirugia->autCirugias->first();

        if (! $autorizacion) {
            return 'No solicitada';
        }

        return $autorizacion->autoCirugiaEstados
            ->firstWhere('fechaFinAutoCirugiaEstado', null)
            ?->estadoAutCirugia?->nombreEstadoAutCirugia ?? 'Sin estado';
    }

    public function autorizacionAprobada(): bool
    {
        return in_array($this->autorizacion(), ['Aprobada', 'Sin cobertura'], true);
    }

    /**
     * Obra social y plan con que se opera, para mostrar: «Swiss Medical · SMG 20».
     * Si el plan ya lleva el nombre de la obra social («OSDE 410»), va solo.
     */
    public function cobertura(): ?string
    {
        $plan = $this->plan?->nombrePlan;
        $obraSocial = $this->plan?->obraSocial?->nombreObraSocial;

        if ($plan && $obraSocial && str_contains(mb_strtolower($plan), mb_strtolower($obraSocial))) {
            return $plan;
        }

        return collect([$obraSocial, $plan])->filter()->join(' · ') ?: null;
    }

    public function nroAutorizacion(): ?string
    {
        return $this->cirugia->autCirugias->first()?->nroAprobacionAutCirugia;
    }

    /**
     * @return Collection<int, AutoCirugiaEstado>
     */
    public function historialAutorizacion(): Collection
    {
        $autorizacion = $this->cirugia->autCirugias->first();
        if (! $autorizacion) {
            return collect();
        }

        return $autorizacion->autoCirugiaEstados()
            ->with('estadoAutCirugia')
            ->orderByDesc('fechaInicioAutoCirugiaEstado')
            ->get();
    }

    // --- Estudios prequirurgicos --------------------------------------------

    public function estudiosTotal(): int
    {
        return $this->cirugia->cirugiaTipoEstudios->count();
    }

    public function estudiosSubidos(): int
    {
        return $this->cirugia->cirugiaTipoEstudios
            ->whereNotNull('fechaSubidaCirugiaTipoEstudio')
            ->count();
    }

    public function estudiosPendientes(): int
    {
        return $this->estudiosTotal() - $this->estudiosSubidos();
    }

    /** @return Collection<int, string> */
    public function nombresEstudiosPendientes(): Collection
    {
        return $this->cirugia->cirugiaTipoEstudios
            ->whereNull('fechaSubidaCirugiaTipoEstudio')
            ->map(fn ($e) => $e->tipoEstudio?->nombreTipoEstudio ?? 'Estudio')
            ->values();
    }

    // --- Evaluacion anestesica ----------------------------------------------

    public function evaluacion(): string
    {
        $evaluacion = $this->cirugia->evaluacionAnestesicas->first();

        if (! $evaluacion) {
            return 'Sin evaluación';
        }

        return $evaluacion->evaluacionAnestesicaEstados
            ->firstWhere('fechaFinEvaluacionAnestesicaEstado', null)
            ?->estadoEvaluacionAnestesica?->nombreEstadoEvaluacionAnestesica ?? 'Sin estado';
    }

    public function evaluacionCompleta(): bool
    {
        return $this->evaluacion() === 'Completada';
    }

    public function asa(): ?string
    {
        return $this->cirugia->evaluacionAnestesicas->first()
            ?->evaluacionTipoAsas
            ->firstWhere('fechaFinTipoAsa', null)
            ?->tipoAsa?->aliasTipoAsa;
    }

    // --- Materiales ---------------------------------------------------------

    public function requiereMateriales(): bool
    {
        return $this->cirugia->pedidoMateriales->isNotEmpty();
    }

    public function materiales(): string
    {
        if (! $this->requiereMateriales()) {
            return 'No requiere';
        }

        $estados = $this->cirugia->pedidoMateriales
            ->map(fn ($p) => $p->pedidoMaterialEstados->first()?->estadoPedidoMaterial?->nombreEstadoPedidoMaterial)
            ->filter()
            ->unique();

        // Si los items estan en estados distintos, manda el menos avanzado.
        foreach (['Rechazado', 'Solicitado', 'Presupuestado', 'En auditoría', 'Aprobado', 'Entregado'] as $estado) {
            if ($estados->contains($estado)) {
                return $estado;
            }
        }

        return 'Sin estado';
    }

    public function materialesAprobados(): bool
    {
        return in_array($this->materiales(), ['No requiere', 'Aprobado', 'Entregado'], true);
    }

    public function importeMateriales(): float
    {
        return (float) $this->cirugia->pedidoMateriales->sum('subtotalPedidoMaterial');
    }

    // --- Semaforo -----------------------------------------------------------

    public function estaLista(): bool
    {
        return $this->pendientes()->isEmpty();
    }

    /**
     * Todo lo que le falta a la cirugia para poder realizarse.
     *
     * @return Collection<int, string>
     */
    public function pendientes(): Collection
    {
        $pendientes = collect();

        if (! $this->autorizacionAprobada()) {
            $pendientes->push('Autorización '.mb_strtolower($this->autorizacion()));
        }

        if ($this->estudiosPendientes() > 0) {
            $pendientes->push($this->estudiosPendientes().' '.
                str('estudio')->plural($this->estudiosPendientes()).' sin subir');
        }

        if (! $this->evaluacionCompleta()) {
            $pendientes->push('Evaluación anestésica '.mb_strtolower($this->evaluacion()));
        }

        if (! $this->materialesAprobados()) {
            $pendientes->push('Materiales: '.mb_strtolower($this->materiales()));
        }

        if (! $this->consentimientoFirmado()) {
            $pendientes->push('Consentimiento sin firmar');
        }

        return $pendientes;
    }

    public function enRiesgo(): bool
    {
        return $this->estado() === 'En riesgo' || ! $this->estaLista();
    }

    /**
     * Si la etiqueta del semaforo («Listo», «En riesgo») tapa el estado
     * vigente y hay que mostrarlo aparte. Pasa justo cuando la cirugia es
     * inminente, que es cuando el paciente confirma o pide reprogramar: sin
     * esto, el staff no veria su respuesta.
     */
    public function semaforoTapaEstado(): bool
    {
        $etiqueta = match (true) {
            $this->estaLista() => null,
            $this->semaforo() === 'error' => 'En riesgo',
            default => $this->estado(),
        };

        return $etiqueta !== $this->estado() && $this->estado() !== 'Sin estado';
    }

    /**
     * Semaforo para la interfaz: verde si esta lista, rojo si falta algo y la
     * cirugia es inminente, amarillo si falta algo pero hay margen.
     */
    public function semaforo(): string
    {
        if ($this->estaLista()) {
            return 'exito';
        }

        return $this->diasRestantes() !== null && $this->diasRestantes() <= 2
            ? 'error'
            : 'aviso';
    }

    public function diasRestantes(): ?int
    {
        return $this->cuando()
            ? (int) Carbon::today()->diffInDays($this->cuando()->copy()->startOfDay(), false)
            : null;
    }

    // --- Expediente ---------------------------------------------------------

    public function tipoAnestesia(): ?string
    {
        return $this->cirugia->evaluacionAnestesicas->first()
            ?->evaluacionTipoAnestesias
            ->firstWhere('fechaFinTipoAnestesia', null)
            ?->tipoAnestesia?->nombreTipoAnestesia;
    }

    public function alertaProfilaxis(): ?string
    {
        return $this->hisopadoSarmVigente()?->profilaxisAtbHisopadoSarms->first()?->alertaProfilaxisAtbHisopadoSarm;
    }

    /** @return Collection<int, ProfilaxisAtbHisopadoSarmProfilaxis> */
    public function profilaxis(): Collection
    {
        return $this->hisopadoSarmVigente()?->profilaxisAtbHisopadoSarms->first()?->profilaxisAtbHisopadoSarmProfilaxis ?? collect();
    }

    /**
     * Estado y datos del hisopado SAMR pedido para esta cirugía, aplanados
     * para mostrarlos. `null` si todavía no se pidió.
     *
     * @return ?array{estado: string, fechaSolicitacion: ?Carbon, fechaEstimada: ?Carbon, establecimiento: ?string, observaciones: ?string}
     */
    public function hisopadoSarm(): ?array
    {
        $hisopado = $this->hisopadoSarmVigente();

        if (! $hisopado) {
            return null;
        }

        return [
            'estado' => $hisopado->hisopadoSarmEstados
                ->firstWhere('fechaFinAsignacionHisopadoSarmEstado', null)
                ?->estadoHisopadoSarm?->nombreEstadoHisopadoSarm ?? 'Sin estado',
            'fechaSolicitacion' => $hisopado->fechaSolicitacionHisopadoSarm,
            'fechaEstimada' => $hisopado->fechaEstimadaResultadosHisopadoSarm,
            'establecimiento' => $hisopado->establecimiento?->nombreEstablecimiento,
            'observaciones' => $hisopado->observacionesHisopadoSarm,
            // Puntero al adjunto en el gestor documental, no una URL: la vista
            // lo usa sólo para saber si hay algo que mostrar.
            'archivo' => $hisopado->urlHisopadoSarm,
        ];
    }

    private function hisopadoSarmVigente(): ?HisopadoSarm
    {
        return $this->cirugia->hisopadoSarms->first();
    }

    /** @return Collection<int, PedidoHemoderivado> */
    public function pedidosHemoderivados(): Collection
    {
        return $this->cirugia->pedidoHemoderivados()
            ->with(['pedidoTipoHemoderivados.tipoHemoderivado', 'pedidoTipoHemoderivados.establecimiento'])
            ->orderByDesc('fechaPedidoHemoderivado')
            ->get();
    }

    public function consentimiento(): ?ConsentimientoPaciente
    {
        return $this->cirugia->consentimientoPacientes->first();
    }

    public function consentimientoFirmado(): bool
    {
        return $this->consentimiento()?->fechaFirmaConsentimiento !== null;
    }

    /**
     * Indicaciones de preparación con sus horas y fecha/hora límite calculada,
     * aplanadas para mostrarlas.
     *
     * @return Collection<int, array{bloque: string, indicacion: string, horas: ?int, ultimaIngesta: ?Carbon}>
     */
    public function preparacion(): Collection
    {
        $cuando = $this->cuando();

        return collect($this->cirugia->preparacionPacientes->first()?->preparacionPacienteTipoPreparaciones ?? [])
            ->flatMap(fn ($bloque) => collect($bloque->preparacionPacienteTipoPreparacionTipoIndicaciones)
                ->map(fn ($indicacion) => [
                    'bloque' => $bloque->tipoPreparacion?->nombreTipoPreparacion ?? '',
                    'indicacion' => $indicacion->tipoIndicacion?->nombreTipoIndicacion ?? '',
                    'horas' => $indicacion->hsReglaCantidadIngestaAnteriorCirugia,
                    'ultimaIngesta' => ($indicacion->hsReglaCantidadIngestaAnteriorCirugia !== null && $cuando)
                        ? $cuando->copy()->subHours($indicacion->hsReglaCantidadIngestaAnteriorCirugia)
                        : null,
                ]))
            ->values();
    }

    /**
     * Recordatorio u observaciones de la preparación cargadas por el equipo.
     */
    public function recordatorioPreparacion(): ?string
    {
        return $this->cirugia->preparacionPacientes->first()?->observacionesPreparacionPaciente;
    }

    /**
     * Cuestionario pre-anestésico respondido: pregunta y respuesta, sea de
     * opción cerrada o de texto libre.
     *
     * @return Collection<int, array{pregunta: string, respuesta: string}>
     */
    public function cuestionario(): Collection
    {
        $config = $this->cirugia->examenCirugiaPreAnestesicas->first()
            ?->examenPreAnestesicoConfiges->first();

        return collect($config?->examenPreAnestesicoConfigPreguntas ?? [])
            ->map(function ($respondida) {
                $cerradas = collect($respondida->examenPreAnestesicoConfigPreguntaRespuestas)
                    ->map(fn ($r) => $r->configTipoExamenPreAnestesicoPreguntaRespuesta
                        ?->nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta)
                    ->filter();

                return [
                    'pregunta' => $respondida->configTipoExamenPreAnestesicoPregunta
                        ?->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta ?? '',
                    'respuesta' => $cerradas->isNotEmpty()
                        ? $cerradas->join(', ')
                        : ($respondida->respuestaExamenPreAnestesicoConfigPregunta ?? '—'),
                ];
            })
            ->filter(fn (array $fila) => $fila['pregunta'] !== '')
            ->values();
    }

    /** @return Collection<int, CirugiaPersonal> */
    public function equipo(): Collection
    {
        return $this->cirugia->cirugiaPersonales
            ->whereNull('fechaFinAsignacionCirugiaPersonal');
    }

    // --- Portal del paciente ------------------------------------------------

    /**
     * «Estado de mi proceso»: lo que el paciente puede seguir de su cirugia,
     * dicho para el. Deja afuera lo que es logistica interna (materiales) y lo
     * que no existe en la base (turnos con otros servicios).
     *
     * `seccion` es la pestaña del detalle donde se completa o se consulta, o
     * null si no depende de el. Necesita RELACIONES_EXPEDIENTE (cuestionario).
     *
     * @return Collection<int, array{titulo: string, detalle: string, listo: bool, seccion: ?string}>
     */
    public function procesoPaciente(): Collection
    {
        $estudios = $this->estudiosTotal() === 0
            ? 'No te pidieron estudios'
            : $this->estudiosSubidos().' de '.$this->estudiosTotal().' '
                .str('subido')->plural($this->estudiosTotal());

        return collect([
            [
                'titulo' => 'Autorización de la obra social',
                'detalle' => $this->autorizacion(),
                'listo' => $this->autorizacionAprobada(),
                'seccion' => null,
            ],
            [
                'titulo' => 'Estudios prequirúrgicos',
                'detalle' => $estudios,
                'listo' => $this->estudiosPendientes() === 0,
                'seccion' => 'estudios',
            ],
            [
                'titulo' => 'Cuestionario preanestésico',
                'detalle' => $this->cuestionario()->isNotEmpty() ? 'Respondido' : 'Sin responder',
                'listo' => $this->cuestionario()->isNotEmpty(),
                'seccion' => 'preanestesica',
            ],
            [
                'titulo' => 'Evaluación anestésica',
                'detalle' => $this->evaluacionCompleta() ? 'Completada' : 'La completa tu anestesista',
                'listo' => $this->evaluacionCompleta(),
                'seccion' => null,
            ],
            [
                'titulo' => 'Indicaciones de preparación',
                'detalle' => $this->preparacion()->isNotEmpty() ? 'Cargadas por el equipo' : 'Tu equipo todavía no las cargó',
                'listo' => $this->preparacion()->isNotEmpty(),
                'seccion' => 'preparacion',
            ],
            [
                'titulo' => 'Consentimiento informado',
                'detalle' => $this->consentimientoFirmado() ? 'Firmado' : 'Sin firmar',
                'listo' => $this->consentimientoFirmado(),
                'seccion' => 'consentimiento',
            ],
        ]);
    }

    /**
     * Si corresponde pedirle al paciente que confirme que viene: cirugia
     * futura, dentro de las proximas HORAS_CONFIRMACION_ASISTENCIA, y en un
     * estado en el que la respuesta todavia cambia algo.
     */
    public function puedeConfirmarAsistencia(): bool
    {
        $cuando = $this->cuando();

        return $cuando !== null
            && $cuando->isFuture()
            && $cuando->lte(now()->addHours(self::HORAS_CONFIRMACION_ASISTENCIA))
            && ! in_array($this->estado(), self::ESTADOS_SIN_CONFIRMACION, true);
    }

    /**
     * En que quedo la asistencia, leido del estado vigente: 'confirmada',
     * 'reprogramar' o null. 'A reprogramar' tambien lo pone
     * cirugias:check-reprogramar cuando falta algo, asi que la vista no puede
     * dar por hecho que lo pidio el paciente.
     */
    public function respuestaAsistencia(): ?string
    {
        return match ($this->estado()) {
            'Asistencia de paciente confirmada' => 'confirmada',
            'A reprogramar' => 'reprogramar',
            default => null,
        };
    }
}
