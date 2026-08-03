<?php

namespace App\Support;

use App\Models\Cirugia;
use App\Models\CirugiaPersonal;
use App\Models\ConsentimientoPaciente;
use App\Models\PedidoTipoHemoderivado;
use App\Models\Persona;
use App\Models\Plan;
use App\Models\ProfilaxisAtbCirugiaProfilaxis;
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
    ];

    /** Relaciones adicionales que solo hacen falta en el expediente completo. */
    public const RELACIONES_EXPEDIENTE = [
        'cirugiaPersonales.personal.persona',
        'cirugiaPersonales.rol',
        'pedidoHemoderivados.pedidoTipoHemoderivados.tipoHemoderivado',
        'pedidoHemoderivados.pedidoTipoHemoderivados.establecimiento',
        'profilaxisAtbCirugias.profilaxisAtbCirugiaProfilaxis.profilaxis',
        'profilaxisAtbCirugias.profilaxisAtbCirugiaProfilaxis.profilaxisRol',
        'consentimientoPacientes.configConsentimiento',
        'preparacionPacientes.preparacionPacienteTipoPreparaciones.tipoPreparacion',
        'preparacionPacientes.preparacionPacienteTipoPreparaciones.preparacionPacienteTipoPreparacionTipoIndicaciones.tipoIndicacion',
        'examenCirugiaPreAnestesicas.examenPreAnestesicoConfiges.examenPreAnestesicoConfigPreguntas.configTipoExamenPreAnestesicoPregunta',
        'examenCirugiaPreAnestesicas.examenPreAnestesicoConfiges.examenPreAnestesicoConfigPreguntas.examenPreAnestesicoConfigPreguntaRespuestas.configTipoExamenPreAnestesicoPreguntaRespuesta',
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

    public function nroAutorizacion(): ?string
    {
        return $this->cirugia->autCirugias->first()?->nroAprobacionAutCirugia;
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
        return $this->cirugia->profilaxisAtbCirugias->first()?->alertaProfilaxisAtbCirugia;
    }

    /** @return Collection<int, ProfilaxisAtbCirugiaProfilaxis> */
    public function profilaxis(): Collection
    {
        return $this->cirugia->profilaxisAtbCirugias->first()?->profilaxisAtbCirugiaProfilaxis ?? collect();
    }

    /** @return Collection<int, PedidoTipoHemoderivado> */
    public function hemoderivados(): Collection
    {
        return $this->cirugia->pedidoHemoderivados->first()?->pedidoTipoHemoderivados ?? collect();
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
     * Indicaciones de preparación con sus horas, aplanadas para mostrarlas.
     *
     * @return Collection<int, array{bloque: string, indicacion: string, horas: ?int}>
     */
    public function preparacion(): Collection
    {
        return collect($this->cirugia->preparacionPacientes->first()?->preparacionPacienteTipoPreparaciones ?? [])
            ->flatMap(fn ($bloque) => collect($bloque->preparacionPacienteTipoPreparacionTipoIndicaciones)
                ->map(fn ($indicacion) => [
                    'bloque' => $bloque->tipoPreparacion?->nombreTipoPreparacion ?? '',
                    'indicacion' => $indicacion->tipoIndicacion?->nombreTipoIndicacion ?? '',
                    'horas' => $indicacion->hsReglaCantidadIngestaAnteriorCirugia,
                ]))
            ->values();
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
}
