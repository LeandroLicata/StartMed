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

        if ((new ResumenCirugia($cirugia))->esAnterior()) {
            return $volver->with('aviso', 'Es una cirugía anterior: ya no se puede responder el cuestionario.');
        }

        if ($cirugia->examenCirugiaPreAnestesicas()->exists()) {
            return $volver->with('aviso', 'El cuestionario preanestésico ya fue respondido.');
        }

        $version = ConfigTipoExamenPreAnestesico::query()
            ->whereNull('fechaFinVigeConfigTipoExamenPreAnestesico')
            ->with(['configTipoExamenPreAnestesicoPreguntas.configTipoExamenPreAnestesicoPreguntaRespuestas'])
            ->first();

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
            Cirugia::whereKey($cirugia->idCirugia)->lockForUpdate()->first();

            if ($cirugia->examenCirugiaPreAnestesicas()->exists()) {
                return false;
            }

            $examen = ExamenCirugiaPreAnestesica::create([
                'idCirugia' => $cirugia->idCirugia,
                'observacionesExamenCirugiaPreAnestesica' => 'Cuestionario respondido por el paciente.',
            ]);

            $examenConfig = ExamenPreAnestesicoConfig::create([
                'idExamenCirugiaPreAnestesica' => $examen->idExamenCirugiaPreAnestesica,
                'idConfigTipoExamenPreAnestesico' => $version->idConfigTipoExamenPreAnestesico,
            ]);

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
