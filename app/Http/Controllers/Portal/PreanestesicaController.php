<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cirugia;
use App\Models\ConfigTipoExamenPreAnestesico;
use App\Models\ExamenCirugiaPreAnestesica;
use App\Models\ExamenPreAnestesicoConfig;
use App\Models\ExamenPreAnestesicoConfigPregunta;
use App\Models\ExamenPreAnestesicoConfigPreguntaRespuesta;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cuestionario preanestésico desde el portal del paciente.
 *
 * El paciente completa por única vez el cuestionario vigente. Una vez enviado,
 * queda en solo lectura (se lee mediante ResumenCirugia::cuestionario()) y la
 * sección pasa a completa en «Estado de mi proceso».
 *
 * La pertenencia de {cirugia} la verifica el middleware del grupo de rutas
 * (cirugia.del.paciente).
 */
class PreanestesicaController extends Controller
{
    public function responder(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $volver = redirect()->route('portal.cirugias.show', [$cirugia, 'preanestesica']);

        $cirugia->load([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE]);
        $caso = new ResumenCirugia($cirugia);

        if ($caso->esAnterior()) {
            return $volver->with('aviso', 'Es una cirugía anterior: ya no se puede responder el cuestionario.');
        }

        if ($caso->cuestionarioRespondido()) {
            return $volver->with('aviso', 'El cuestionario preanestésico ya fue respondido.');
        }

        $version = ConfigTipoExamenPreAnestesico::vigente();

        if (! $version || $version->configTipoExamenPreAnestesicoPreguntas->isEmpty()) {
            return $volver->with('error', 'No hay un cuestionario preanestésico vigente disponible.');
        }

        $reglas = [];
        $atributos = [];

        foreach ($version->configTipoExamenPreAnestesicoPreguntas as $pregunta) {
            $clave = 'respuestas.'.$pregunta->idConfigTipoExamenPreAnestesicoPregunta;
            $atributos[$clave] = '«'.$pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta.'»';

            if ($pregunta->requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta) {
                $opcionesValidas = $pregunta->configTipoExamenPreAnestesicoPreguntaRespuestas
                    ->pluck('idConfigTipoExamenPreAnestesicoPreguntaRespuesta')
                    ->all();

                $reglas[$clave] = ['required', Rule::in($opcionesValidas)];
            } else {
                $reglas[$clave] = ['required', 'string', 'max:255'];
            }
        }

        $datos = $request->validate($reglas, [], $atributos);

        $guardado = DB::transaction(function () use ($cirugia, $version, $datos) {
            $cirugiaBloqueada = Cirugia::whereKey($cirugia->idCirugia)->lockForUpdate()->first();
            $cirugiaBloqueada->load([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE]);

            $casoBloqueado = new ResumenCirugia($cirugiaBloqueada);
            if ($casoBloqueado->cuestionarioRespondido()) {
                return false;
            }

            $examen = $cirugiaBloqueada->examenCirugiaPreAnestesicas
                ->first(function ($e) {
                    $config = $e->examenPreAnestesicoConfiges->first();

                    return ! $config || $config->examenPreAnestesicoConfigPreguntas->isEmpty();
                })
                ?? $cirugiaBloqueada->examenCirugiaPreAnestesicas->first()
                ?? ExamenCirugiaPreAnestesica::create([
                    'idCirugia' => $cirugiaBloqueada->idCirugia,
                    'observacionesExamenCirugiaPreAnestesica' => null,
                ]);

            $examenConfig = $examen->examenPreAnestesicoConfiges
                ->first(fn ($c) => $c->examenPreAnestesicoConfigPreguntas->isEmpty())
                ?? $examen->examenPreAnestesicoConfiges->first()
                ?? ExamenPreAnestesicoConfig::create([
                    'idExamenCirugiaPreAnestesica' => $examen->idExamenCirugiaPreAnestesica,
                    'idConfigTipoExamenPreAnestesico' => $version->idConfigTipoExamenPreAnestesico,
                ]);

            if ($examenConfig->idConfigTipoExamenPreAnestesico !== $version->idConfigTipoExamenPreAnestesico) {
                $examenConfig->update([
                    'idConfigTipoExamenPreAnestesico' => $version->idConfigTipoExamenPreAnestesico,
                ]);
            }

            foreach ($version->configTipoExamenPreAnestesicoPreguntas as $pregunta) {
                $valor = $datos['respuestas'][$pregunta->idConfigTipoExamenPreAnestesicoPregunta];

                if ($pregunta->requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta) {
                    $respondida = ExamenPreAnestesicoConfigPregunta::create([
                        'idExamenPreAnestesicoConfig' => $examenConfig->idExamenPreAnestesicoConfig,
                        'idConfigTipoExamenPreAnestesicoPregunta' => $pregunta->idConfigTipoExamenPreAnestesicoPregunta,
                        'respuestaExamenPreAnestesicoConfigPregunta' => null,
                    ]);

                    ExamenPreAnestesicoConfigPreguntaRespuesta::create([
                        'idExamenPreAnestesicoConfigPregunta' => $respondida->idExamenPreAnestesicoConfigPregunta,
                        'idConfigTipoExamenPreAnestesicoPreguntaRespuesta' => (int) $valor,
                    ]);
                } else {
                    ExamenPreAnestesicoConfigPregunta::create([
                        'idExamenPreAnestesicoConfig' => $examenConfig->idExamenPreAnestesicoConfig,
                        'idConfigTipoExamenPreAnestesicoPregunta' => $pregunta->idConfigTipoExamenPreAnestesicoPregunta,
                        'respuestaExamenPreAnestesicoConfigPregunta' => trim((string) $valor),
                    ]);
                }
            }

            return true;
        });

        if (! $guardado) {
            return $volver->with('aviso', 'El cuestionario preanestésico ya fue respondido.');
        }

        return $volver->with('exito', 'Listo, recibimos tus respuestas.');
    }
}
