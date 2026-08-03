<?php

namespace App\Services;

use App\Models\AutCirugia;
use App\Models\AutoCirugiaEstado;
use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\CirugiaQuirofano;
use App\Models\EstadoAutCirugia;
use App\Models\EstadoCirugia;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReprogramarCirugiaService
{
    public function reprogramar(Cirugia $original, array $datos)
    {
        return DB::transaction(function () use ($original, $datos) {
            $ahora = Carbon::now();
            $inicio = Carbon::parse($datos['fechaHoraCirugia']);
            $fin = ! empty($datos['fechaHoraFinCirugia']) ? Carbon::parse($datos['fechaHoraFinCirugia']) : null;

            // 1. Crear nueva cirugía
            $nueva = $original->replicate();
            $nueva->fechaHoraCirugia = $inicio;
            $nueva->fechaHoraFinCirugia = $fin;
            $nueva->save();

            // 2. Estado original -> "Reprogramada"
            $estadoReprogramada = EstadoCirugia::where('nombreEstadoCirugia', 'Reprogramada')->firstOrFail()->idEstadoCirugia;

            // Cerrar estado anterior
            $ultimoEstadoViejo = $original->cirugiaEstados()->whereNull('fechaDesasignacionCirugiaEstado')->first();
            if ($ultimoEstadoViejo) {
                $ultimoEstadoViejo->update(['fechaDesasignacionCirugiaEstado' => $ahora]);
            }

            CirugiaEstado::create([
                'idCirugia' => $original->idCirugia,
                'idEstadoCirugia' => $estadoReprogramada,
                'fechaAsignacionCirugiaEstado' => $ahora,
            ]);

            // Liberar quirófano original
            $quirofanoOriginalActivo = $original->cirugiaQuirofanos()->whereNull('fechaHoraDesasignacion')->first();
            if ($quirofanoOriginalActivo) {
                $quirofanoOriginalActivo->update(['fechaHoraDesasignacion' => $ahora]);
            }

            // 3. Asignar nuevo Quirófano
            CirugiaQuirofano::create([
                'idCirugia' => $nueva->idCirugia,
                'idQuirofano' => $datos['idQuirofano'],
                'fechaHoraAsignacion' => $inicio,
            ]);

            // 4. Copiar estado actual a la nueva
            if ($ultimoEstadoViejo && $ultimoEstadoViejo->idEstadoCirugia !== $estadoReprogramada) {
                CirugiaEstado::create([
                    'idCirugia' => $nueva->idCirugia,
                    'idEstadoCirugia' => $ultimoEstadoViejo->idEstadoCirugia,
                    'fechaAsignacionCirugiaEstado' => $ahora,
                ]);
            } else {
                // Fallback
                CirugiaEstado::create([
                    'idCirugia' => $nueva->idCirugia,
                    'idEstadoCirugia' => EstadoCirugia::where('nombreEstadoCirugia', 'En espera')->value('idEstadoCirugia'),
                    'fechaAsignacionCirugiaEstado' => $ahora,
                ]);
            }

            // 5. Autorización (Obra Social)
            $this->procesarAutorizacion($original, $nueva, $datos, $inicio);

            // 6. Clonar el resto de relaciones
            $this->clonarRelaciones($original, $nueva);

            return $nueva;
        });
    }

    private function procesarAutorizacion(Cirugia $original, Cirugia $nueva, array $datos, Carbon $inicio)
    {
        $cobertura = $datos['cobertura'];

        if ($cobertura === 'misma') {
            // Copiar tal cual la autorización original
            $autOriginal = $original->autCirugias()->first();
            if ($autOriginal) {
                $nuevaAut = $autOriginal->replicate();
                $nuevaAut->idCirugia = $nueva->idCirugia;
                // Recalcular fecha limite en base a la nueva fecha de cirugia
                $diasVigencia = $nuevaAut->plan?->obrasocial?->diasVigenciaOrden;
                $nuevaAut->fechaLimiteEnvioAutorizacion = $diasVigencia ? $inicio->copy()->subDays($diasVigencia) : null;
                $nuevaAut->save();

                // Copiar estados de autorizacion
                foreach ($autOriginal->autoCirugiaEstados as $estadoAut) {
                    $nuevoEstadoAut = $estadoAut->replicate();
                    $nuevoEstadoAut->idAutCirugia = $nuevaAut->idAutCirugia;
                    $nuevoEstadoAut->save();
                }
            }
        } else {
            // Cambio de OS o Particular
            $plan = $this->resolverPlan($datos);

            $autCirugia = AutCirugia::create([
                'idCirugia' => $nueva->idCirugia,
                'idPlan' => $plan->idPlan,
                'fechaLimiteEnvioAutorizacion' => $plan->obrasocial?->diasVigenciaOrden
                    ? $inicio->copy()->subDays($plan->obrasocial->diasVigenciaOrden)
                    : null,
            ]);

            if (! $plan->es_sin_cobertura) {
                AutoCirugiaEstado::create([
                    'idAutCirugia' => $autCirugia->idAutCirugia,
                    'idEstadoAutCirugia' => EstadoAutCirugia::where('nombreEstadoAutCirugia', 'Pendiente de envío')->value('idEstadoAutCirugia'),
                    'fechaInicioAutoCirugiaEstado' => Carbon::now(),
                ]);
            }
        }
    }

    private function resolverPlan(array $datos): Plan
    {
        return match ($datos['cobertura']) {
            'particular' => Plan::where('es_sin_cobertura', true)->firstOrFail(),
            'existente' => PlanObraSocial::findOrFail($datos['idPlanObraSocial'])->plan,
            'nueva' => tap(Plan::findOrFail($datos['idPlan']), function (Plan $plan) use ($datos) {
                PlanObraSocial::create([
                    'idPersona' => $datos['idPersona'],
                    'idPlan' => $plan->idPlan,
                    'nroBeneficiaroPlanObraSocial' => $datos['nroBeneficiario'] ?? null,
                    'fechaInicioPlanObraSocial' => Carbon::now(),
                ]);
            }),
        };
    }

    private function clonarRelaciones(Cirugia $original, Cirugia $nueva)
    {
        // Personal
        foreach ($original->cirugiaPersonales()->whereNull('fechaFinAsignacionCirugiaPersonal')->get() as $personal) {
            $n = $personal->replicate();
            $n->idCirugia = $nueva->idCirugia;
            $n->save();
        }

        // Estudios (CirugiaTipoEstudio) - clonando el registro apunta al mismo archivo físico
        foreach ($original->cirugiaTipoEstudios as $estudio) {
            $n = $estudio->replicate();
            $n->idCirugia = $nueva->idCirugia;
            $n->save();
        }

        // Evaluacion Anestesica (es más profunda)
        foreach ($original->evaluacionAnestesicas as $evaluacion) {
            $nEval = $evaluacion->replicate();
            $nEval->idCirugia = $nueva->idCirugia;
            $nEval->save();

            foreach ($evaluacion->evaluacionAnestesicaEstados as $est) {
                $n = $est->replicate();
                $n->idEvaluacionAnestesica = $nEval->idEvaluacionAnestesica;
                $n->save();
            }
            foreach ($evaluacion->evaluacionTipoAsas as $asa) {
                $n = $asa->replicate();
                $n->idEvaluacionAnestesica = $nEval->idEvaluacionAnestesica;
                $n->save();
            }
            foreach ($evaluacion->evaluacionTipoAnestesias as $tipo) {
                $n = $tipo->replicate();
                $n->idEvaluacionAnestesica = $nEval->idEvaluacionAnestesica;
                $n->save();
            }
        }

        // Consentimientos
        foreach ($original->consentimientoPacientes as $cons) {
            $n = $cons->replicate();
            $n->idCirugia = $nueva->idCirugia;
            $n->save();
        }

        // Examen Cirugia Pre Anestesicas
        foreach ($original->examenCirugiaPreAnestesicas as $exam) {
            $nExam = $exam->replicate();
            $nExam->idCirugia = $nueva->idCirugia;
            $nExam->save();

            foreach ($exam->examenPreAnestesicoConfiges as $conf) {
                $nConf = $conf->replicate();
                $nConf->idExamenCirugiaPreAnestesica = $nExam->idExamenCirugiaPreAnestesica;
                $nConf->save();

                foreach ($conf->examenPreAnestesicoConfigPreguntas as $preg) {
                    $nPreg = $preg->replicate();
                    $nPreg->idExamenPreAnestesicoConfig = $nConf->idExamenPreAnestesicoConfig;
                    $nPreg->save();

                    foreach ($preg->examenPreAnestesicoConfigPreguntaRespuestas as $resp) {
                        $nResp = $resp->replicate();
                        $nResp->idExamenPreAnestesicoConfigPregunta = $nPreg->idExamenPreAnestesicoConfigPregunta;
                        $nResp->save();
                    }
                }
            }
        }

        // Pedido Hemoderivados
        foreach ($original->pedidoHemoderivados as $ped) {
            $nPed = $ped->replicate();
            $nPed->idCirugia = $nueva->idCirugia;
            $nPed->save();

            foreach ($ped->pedidoTipoHemoderivados as $tipo) {
                $n = $tipo->replicate();
                $n->idPedidoHemoderivado = $nPed->idPedidoHemoderivado;
                $n->save();
            }
        }

        // Pedido Materiales
        foreach ($original->pedidoMateriales as $ped) {
            $nPed = $ped->replicate();
            $nPed->idCirugia = $nueva->idCirugia;
            $nPed->save();

            foreach ($ped->pedidoMaterialEstados as $est) {
                $n = $est->replicate();
                $n->idPedidoMaterial = $nPed->idPedidoMaterial;
                $n->save();
            }
        }

        // Preparacion Pacientes
        foreach ($original->preparacionPacientes as $prep) {
            $nPrep = $prep->replicate();
            $nPrep->idCirugia = $nueva->idCirugia;
            $nPrep->save();

            foreach ($prep->preparacionPacienteTipoPreparaciones as $tipo) {
                $nTipo = $tipo->replicate();
                $nTipo->idPreparacionPaciente = $nPrep->idPreparacionPaciente;
                $nTipo->save();

                foreach ($tipo->preparacionPacienteTipoPreparacionTipoIndicaciones as $ind) {
                    $nInd = $ind->replicate();
                    $nInd->idPreparacionPacienteTipoPreparacion = $nTipo->idPreparacionPacienteTipoPreparacion;
                    $nInd->save();
                }
            }
        }

        // Profilaxis
        foreach ($original->profilaxisAtbCirugias as $prof) {
            $nProf = $prof->replicate();
            $nProf->idCirugia = $nueva->idCirugia;
            $nProf->save();

            foreach ($prof->profilaxisAtbCirugiaProfilaxis as $p) {
                $n = $p->replicate();
                $n->idProfilaxisAtbCirugia = $nProf->idProfilaxisAtbCirugia;
                $n->save();
            }
        }
    }
}
