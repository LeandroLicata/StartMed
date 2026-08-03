<?php

namespace Database\Seeders;

use App\Models\Cirugia;
use App\Models\ConfigConsentimiento;
use App\Models\ConfigTipoExamenPreAnestesico;
use App\Models\ConfigTipoExamenPreAnestesicoPregunta;
use App\Models\ConfigTipoExamenPreAnestesicoPreguntaRespuesta;
use App\Models\ConsentimientoPaciente;
use App\Models\Establecimiento;
use App\Models\ExamenCirugiaPreAnestesica;
use App\Models\ExamenPreAnestesicoConfig;
use App\Models\ExamenPreAnestesicoConfigPregunta;
use App\Models\ExamenPreAnestesicoConfigPreguntaRespuesta;
use App\Models\PedidoTipoHemoderivado;
use App\Models\PreparacionPaciente;
use App\Models\PreparacionPacienteTipoPreparacion;
use App\Models\PreparacionPacienteTipoPreparacionTipoIndicacion;
use App\Models\TipoCirugia;
use App\Models\TipoIndicacion;
use App\Models\TipoPreparacion;
use App\Support\Consentimiento;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Completa el expediente de las cirugias que creo DemoSeeder: preparacion del
 * paciente, consentimiento informado y cuestionario pre-anestesico.
 *
 * Va aparte de DemoSeeder para que cada archivo se entienda solo.
 */
class ExpedienteSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $banco = Establecimiento::firstOrCreate(
            ['nombreEstablecimiento' => 'Banco de Sangre Hospital Universitario'],
            [
                'descripcionEstablecimiento' => 'Servicio de hemoterapia',
                'codTelefonoEstablecimiento' => '261',
                'numeroTelefonoEstablecimiento' => '4135000',
                'emailEstablecimiento' => 'hemoterapia@hospital.uncuyo.edu.ar',
            ],
        );

        // Los pedidos de hemoderivados de la demo se cargan sin establecimiento.
        PedidoTipoHemoderivado::whereNull('idEstablecimiento')
            ->update(['idEstablecimiento' => $banco->idEstablecimiento]);

        $config = $this->cuestionario();

        foreach (Cirugia::with('paciente', 'tipoCirugia', 'cirugiaEstados.estadoCirugia')->get() as $cirugia) {
            $this->preparacion($cirugia);
            $this->consentimiento($cirugia);
            $this->examen($cirugia, $config);
        }
    }

    /**
     * Plantilla vigente del cuestionario pre-anestesico, con sus preguntas y
     * las opciones de respuesta de las que son cerradas.
     */
    private function cuestionario(): ConfigTipoExamenPreAnestesico
    {
        $config = ConfigTipoExamenPreAnestesico::firstOrCreate(
            ['fechaFinVigeConfigTipoExamenPreAnestesico' => null],
            ['fechaInicioVigeConfigTipoExamenPreAnestesico' => now()->subYear()],
        );

        $preguntas = [
            ['¿Tenés alergia a algún medicamento?', true, ['No', 'Sí — penicilina', 'Sí — otro']],
            ['¿Tomás medicación habitual?', false, []],
            ['¿Te operaron alguna vez?', true, ['No', 'Sí, sin complicaciones', 'Sí, con complicaciones']],
            ['¿Fumás?', true, ['No', 'Sí, menos de 10 por día', 'Sí, más de 10 por día']],
            ['¿Tenés alguna enfermedad crónica?', false, []],
            ['¿Tuviste complicaciones con anestesias anteriores?', true, ['No', 'Sí']],
        ];

        foreach ($preguntas as [$texto, $cerrada, $opciones]) {
            $pregunta = ConfigTipoExamenPreAnestesicoPregunta::firstOrCreate(
                [
                    'idConfigTipoExamenPreAnestesico' => $config->idConfigTipoExamenPreAnestesico,
                    'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => $texto,
                ],
                ['requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => $cerrada],
            );

            foreach ($opciones as $opcion) {
                ConfigTipoExamenPreAnestesicoPreguntaRespuesta::firstOrCreate([
                    'idConfigTipoExamenPreAnestesicoPregunta' => $pregunta->idConfigTipoExamenPreAnestesicoPregunta,
                    'nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => $opcion,
                ]);
            }
        }

        return $config;
    }

    /**
     * Ayuno e higiene prequirurgica. Las horas van en la tabla de indicaciones,
     * que es donde el esquema las modela (hsReglaCantidadIngestaAnteriorCirugia).
     */
    private function preparacion(Cirugia $cirugia): void
    {
        $preparacion = PreparacionPaciente::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            ['observacionesPreparacionPaciente' => 'Preparación estándar prequirúrgica.'],
        );

        $indicaciones = [
            'Ayuno' => [
                ['Sólidos', 8],
                ['Líquidos claros', 2],
                ['Tabaco', 12],
            ],
            'Higiene prequirúrgica' => [
                ['Ducha con antiséptico', 12],
            ],
        ];

        foreach ($indicaciones as $tipo => $items) {
            $bloque = PreparacionPacienteTipoPreparacion::firstOrCreate([
                'idPreparacionPaciente' => $preparacion->idPreparacionPaciente,
                'idTipoPreparacion' => TipoPreparacion::where('nombreTipoPreparacion', $tipo)->value('idTipoPreparacion'),
            ]);

            foreach ($items as [$indicacion, $horas]) {
                PreparacionPacienteTipoPreparacionTipoIndicacion::firstOrCreate(
                    [
                        'idPreparacionPacienteTipoPreparacion' => $bloque->idPreparacionPacienteTipoPreparacion,
                        'idTipoIndicacion' => TipoIndicacion::where('nombreTipoIndicacion', $indicacion)->value('idTipoIndicacion'),
                    ],
                    ['hsReglaCantidadIngestaAnteriorCirugia' => $horas],
                );
            }
        }
    }

    /**
     * Plantilla de consentimiento por tipo de cirugia y, para las cirugias ya
     * confirmadas, el consentimiento renderizado con los datos del paciente.
     */
    private function consentimiento(Cirugia $cirugia): void
    {
        if (! $cirugia->tipoCirugia) {
            return;
        }

        $plantilla = ConfigConsentimiento::firstOrCreate(
            [
                'idTipoCirugia' => $cirugia->idTipoCirugia,
                'fechaFinConfigConsentimiento' => null,
            ],
            [
                'fechaInicioConfigConsentimiento' => now()->subYear(),
                'textoConfigConsentimiento' => $this->plantilla($cirugia->tipoCirugia),
            ],
        );

        // Solo se renderiza para el paciente cuando la cirugia ya tiene fecha.
        if (! $cirugia->fechaHoraCirugia) {
            return;
        }

        $texto = Consentimiento::paraCirugia($plantilla->textoConfigConsentimiento, $cirugia);

        // Confirmada/Realizada implica que ya se completó el circuito
        // administrativo, consentimiento incluido; el resto queda sin firmar.
        $estadoVigente = $cirugia->cirugiaEstados
            ->firstWhere('fechaDesasignacionCirugiaEstado', null)
            ?->estadoCirugia?->nombreEstadoCirugia;
        $firmado = in_array($estadoVigente, ['Confirmada', 'Realizada'], true);

        ConsentimientoPaciente::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            [
                'idConfigConsentimiento' => $plantilla->idConfigConsentimiento,
                'textoRenderizadoConsentimiento' => $texto,
                // El hash se calcula sobre el texto, antes de firmar.
                'hashConsentimiento' => hash('sha256', $texto),
                'fechaFirmaConsentimiento' => $firmado ? now()->subDays(2) : null,
            ],
        );
    }

    private function plantilla(TipoCirugia $tipo): string
    {
        return <<<TEXTO
        Yo, {{paciente}}, DNI {{dni}}, declaro que el/la profesional {{cirujano}}
        me explicó en lenguaje claro el procedimiento indicado.

        Procedimiento: {{procedimiento}}.

        {$tipo->descripcionTipoCirugia}

        Riesgos posibles: sangrado, infección, lesión de estructuras vecinas y
        reacción adversa a la anestesia.

        He tenido la oportunidad de hacer preguntas y de recibir respuestas que
        comprendí. Presto mi conformidad de manera libre y voluntaria.
        TEXTO;
    }

    /**
     * Cuestionario respondido, para las cirugias cuya evaluacion no esta
     * pendiente. Las respuestas cerradas van en la tabla de respuestas y las
     * abiertas en el campo de texto de la pregunta.
     */
    private function examen(Cirugia $cirugia, ConfigTipoExamenPreAnestesico $config): void
    {
        $respuestasDemo = [
            '¿Tenés alergia a algún medicamento?' => 'No',
            '¿Te operaron alguna vez?' => 'No',
            '¿Fumás?' => 'No',
            '¿Tuviste complicaciones con anestesias anteriores?' => 'No',
        ];

        // El caso Ramírez tiene alergia documentada.
        if (str_contains((string) $cirugia->observacionesPaciente, 'penicilina')) {
            $respuestasDemo['¿Tenés alergia a algún medicamento?'] = 'Sí — penicilina';
            $respuestasDemo['¿Te operaron alguna vez?'] = 'Sí, sin complicaciones';
        }

        $abiertas = [
            '¿Tomás medicación habitual?' => str_contains((string) $cirugia->observacionesPaciente, 'HTA')
                ? 'Enalapril 10mg y metformina 850mg'
                : 'Ninguna',
            '¿Tenés alguna enfermedad crónica?' => str_contains((string) $cirugia->observacionesPaciente, 'diabetes')
                ? 'Hipertensión arterial y diabetes tipo 2'
                : 'Ninguna',
        ];

        $examen = ExamenCirugiaPreAnestesica::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            ['observacionesExamenCirugiaPreAnestesica' => 'Cuestionario respondido por el paciente.'],
        );

        $examenConfig = ExamenPreAnestesicoConfig::firstOrCreate([
            'idExamenCirugiaPreAnestesica' => $examen->idExamenCirugiaPreAnestesica,
            'idConfigTipoExamenPreAnestesico' => $config->idConfigTipoExamenPreAnestesico,
        ]);

        foreach ($config->configTipoExamenPreAnestesicoPreguntas as $pregunta) {
            $texto = $pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta;

            $respondida = ExamenPreAnestesicoConfigPregunta::firstOrCreate(
                [
                    'idExamenPreAnestesicoConfig' => $examenConfig->idExamenPreAnestesicoConfig,
                    'idConfigTipoExamenPreAnestesicoPregunta' => $pregunta->idConfigTipoExamenPreAnestesicoPregunta,
                ],
                ['respuestaExamenPreAnestesicoConfigPregunta' => $abiertas[$texto] ?? null],
            );

            if (! isset($respuestasDemo[$texto])) {
                continue;
            }

            $opcion = ConfigTipoExamenPreAnestesicoPreguntaRespuesta::where('idConfigTipoExamenPreAnestesicoPregunta', $pregunta->idConfigTipoExamenPreAnestesicoPregunta)
                ->where('nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta', $respuestasDemo[$texto])
                ->first();

            if ($opcion) {
                ExamenPreAnestesicoConfigPreguntaRespuesta::firstOrCreate([
                    'idExamenPreAnestesicoConfigPregunta' => $respondida->idExamenPreAnestesicoConfigPregunta,
                    'idConfigTipoExamenPreAnestesicoPreguntaRespuesta' => $opcion->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
                ]);
            }
        }
    }
}
