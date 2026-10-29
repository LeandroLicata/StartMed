<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Cirugia;
use App\Models\ConfigTipoExamenPreAnestesico;
use App\Models\ConfigTipoExamenPreAnestesicoPregunta;
use App\Models\ConfigTipoExamenPreAnestesicoPreguntaRespuesta;
use App\Models\ExamenCirugiaPreAnestesica;
use App\Models\Persona;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pestaña Preanestésica del portal del paciente:
 * - El paciente completa y envía el cuestionario preanestésico vigente.
 * - Una vez enviado, queda en solo lectura y no se puede reenviar.
 * - Una cirugía ajena no se ve ni se puede responder (404).
 * - Una cirugía anterior es de solo lectura.
 * - Todas las preguntas son obligatorias y una opción ajena a la pregunta se rechaza.
 * - "Estado de mi proceso" pasa a completado tras el envío.
 */
class PortalPreanestesicaTest extends TestCase
{
    use RefreshDatabase;

    private Persona $maria;

    private Cirugia $cirugia;

    private ConfigTipoExamenPreAnestesico $version;

    private ConfigTipoExamenPreAnestesicoPregunta $preguntaCerrada;

    private ConfigTipoExamenPreAnestesicoPreguntaRespuesta $opcionNo;

    private ConfigTipoExamenPreAnestesicoPreguntaRespuesta $opcionSi;

    private ConfigTipoExamenPreAnestesicoPregunta $preguntaAbierta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->actingAs($this->maria, 'paciente');

        // Cirugía futura de María
        $this->cirugia = Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->where('fechaHoraCirugia', '>', today()->endOfDay())
            ->firstOrFail();

        // Limpiar exámenes previos de la cirugía de prueba si existieran
        ExamenCirugiaPreAnestesica::where('idCirugia', $this->cirugia->idCirugia)->delete();

        // Configurar un cuestionario vigente controlado para el test
        ConfigTipoExamenPreAnestesico::query()->update(['fechaFinVigeConfigTipoExamenPreAnestesico' => now()]);

        $this->version = ConfigTipoExamenPreAnestesico::create([
            'fechaInicioVigeConfigTipoExamenPreAnestesico' => now()->subMonth(),
            'fechaFinVigeConfigTipoExamenPreAnestesico' => null,
        ]);

        $this->preguntaCerrada = ConfigTipoExamenPreAnestesicoPregunta::create([
            'idConfigTipoExamenPreAnestesico' => $this->version->idConfigTipoExamenPreAnestesico,
            'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => '¿Tenés alergia a algún medicamento?',
            'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => true,
        ]);

        $this->opcionNo = ConfigTipoExamenPreAnestesicoPreguntaRespuesta::create([
            'idConfigTipoExamenPreAnestesicoPregunta' => $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta,
            'nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => 'No',
        ]);

        $this->opcionSi = ConfigTipoExamenPreAnestesicoPreguntaRespuesta::create([
            'idConfigTipoExamenPreAnestesicoPregunta' => $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta,
            'nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => 'Sí — penicilina',
        ]);

        $this->preguntaAbierta = ConfigTipoExamenPreAnestesicoPregunta::create([
            'idConfigTipoExamenPreAnestesico' => $this->version->idConfigTipoExamenPreAnestesico,
            'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => '¿Tomás medicación habitual?',
            'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => false,
        ]);
    }

    private function pestana(?Cirugia $cirugia = null)
    {
        $cirugia ??= $this->cirugia;

        return $this->get("/portal/cirugias/{$cirugia->idCirugia}/preanestesica");
    }

    private function responder(array $respuestas, ?Cirugia $cirugia = null)
    {
        $cirugia ??= $this->cirugia;

        return $this->post("/portal/cirugias/{$cirugia->idCirugia}/preanestesica", [
            'respuestas' => $respuestas,
        ]);
    }

    public function test_el_paciente_puede_ver_y_responder_el_cuestionario_vigente_y_se_guarda(): void
    {
        // 1. Ver la pestaña con el formulario
        $this->pestana()
            ->assertOk()
            ->assertSee('Cuestionario preanestésico')
            ->assertSee('¿Tenés alergia a algún medicamento?')
            ->assertSee('No')
            ->assertSee('Sí — penicilina')
            ->assertSee('¿Tomás medicación habitual?')
            ->assertSee('Enviar respuestas');

        // Estado de mi proceso antes de responder
        $this->get("/portal/cirugias/{$this->cirugia->idCirugia}")
            ->assertOk()
            ->assertSee('Sin responder');

        // 2. Responder y enviar
        $respuestas = [
            $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta => $this->opcionSi->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
            $this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta => 'Enalapril 10mg por la mañana',
        ];

        $respuesta = $this->responder($respuestas);

        $respuesta->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'preanestesica']));
        $respuesta->assertSessionHas('exito', 'Listo, recibimos tus respuestas.');

        // 3. Verificar en la base de datos
        $this->assertDatabaseHas('ExamenCirugiaPreAnestesica', [
            'idCirugia' => $this->cirugia->idCirugia,
            'observacionesExamenCirugiaPreAnestesica' => 'Cuestionario respondido por el paciente.',
        ]);

        $this->assertDatabaseHas('ExamenPreAnestesicoConfig', [
            'idConfigTipoExamenPreAnestesico' => $this->version->idConfigTipoExamenPreAnestesico,
        ]);

        $this->assertDatabaseHas('ExamenPreAnestesicoConfigPregunta', [
            'idConfigTipoExamenPreAnestesicoPregunta' => $this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta,
            'respuestaExamenPreAnestesicoConfigPregunta' => 'Enalapril 10mg por la mañana',
        ]);

        $this->assertDatabaseHas('ExamenPreAnestesicoConfigPreguntaRespuesta', [
            'idConfigTipoExamenPreAnestesicoPreguntaRespuesta' => $this->opcionSi->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
        ]);

        // 4. La pestaña ahora queda en solo lectura
        $this->pestana()
            ->assertOk()
            ->assertSee('Respondido')
            ->assertSee('Ya respondiste este cuestionario')
            ->assertSee('¿Tenés alergia a algún medicamento?')
            ->assertSee('Sí — penicilina')
            ->assertSee('¿Tomás medicación habitual?')
            ->assertSee('Enalapril 10mg por la mañana')
            ->assertDontSee('Enviar respuestas');

        // 5. «Estado de mi proceso» pasa a completo (Respondido)
        $this->get("/portal/cirugias/{$this->cirugia->idCirugia}")
            ->assertOk()
            ->assertSee('Respondido')
            ->assertDontSee('Sin responder');
    }

    public function test_no_se_puede_reenviar_un_cuestionario_ya_respondido(): void
    {
        $respuestas = [
            $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta => $this->opcionNo->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
            $this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta => 'Ninguna',
        ];

        // Primer envío exitoso
        $this->responder($respuestas)->assertRedirect();
        $this->assertSame(1, ExamenCirugiaPreAnestesica::where('idCirugia', $this->cirugia->idCirugia)->count());

        // Segundo envío intentado (por ejemplo, doble clic o dos pestañas)
        $segundo = $this->responder($respuestas);
        $segundo->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'preanestesica']));
        $segundo->assertSessionHas('aviso', 'El cuestionario preanestésico ya fue respondido.');

        // Sigue habiendo un único registro
        $this->assertSame(1, ExamenCirugiaPreAnestesica::where('idCirugia', $this->cirugia->idCirugia)->count());
    }

    public function test_un_paciente_no_puede_ver_ni_escribir_respuestas_de_una_cirugia_ajena(): void
    {
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();

        // Ni ver...
        $this->pestana($ajena)->assertNotFound();

        // ...ni responder
        $respuestas = [
            $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta => $this->opcionNo->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
            $this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta => 'Ninguna',
        ];

        $this->responder($respuestas, $ajena)->assertNotFound();

        // Nada se guarda
        $this->assertDatabaseMissing('ExamenCirugiaPreAnestesica', [
            'idCirugia' => $ajena->idCirugia,
        ]);
    }

    public function test_una_cirugia_anterior_es_solo_lectura(): void
    {
        $this->cirugia->update(['fechaHoraCirugia' => today()->subDays(3)]);

        // 1. Cirugía anterior sin cuestionario: muestra aviso y no el formulario
        $this->pestana()
            ->assertOk()
            ->assertSee('Esta cirugía ya pasó y no se completó el cuestionario preanestésico.')
            ->assertDontSee('Enviar respuestas');

        // 2. Intentar responder vía POST es rechazado
        $respuestas = [
            $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta => $this->opcionNo->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
            $this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta => 'Ninguna',
        ];

        $this->responder($respuestas)
            ->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'preanestesica']))
            ->assertSessionHas('aviso', 'Es una cirugía anterior: ya no se puede responder el cuestionario.');

        $this->assertDatabaseMissing('ExamenCirugiaPreAnestesica', [
            'idCirugia' => $this->cirugia->idCirugia,
        ]);
    }

    public function test_se_rechaza_el_envio_si_falta_responder_una_pregunta_obligatoria(): void
    {
        // Falta responder la pregunta cerrada
        $incompleto1 = [
            $this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta => 'Ninguna',
        ];

        $this->responder($incompleto1)
            ->assertSessionHasErrors([
                'respuestas.'.$this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta,
            ]);

        // Falta responder la pregunta abierta
        $incompleto2 = [
            $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta => $this->opcionNo->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
        ];

        $this->responder($incompleto2)
            ->assertSessionHasErrors([
                'respuestas.'.$this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta,
            ]);

        $this->assertDatabaseMissing('ExamenCirugiaPreAnestesica', [
            'idCirugia' => $this->cirugia->idCirugia,
        ]);
    }

    public function test_opcion_que_no_pertenece_a_la_pregunta_se_rechaza(): void
    {
        // Creamos otra pregunta con su opción para cruzar los valores
        $otraPregunta = ConfigTipoExamenPreAnestesicoPregunta::create([
            'idConfigTipoExamenPreAnestesico' => $this->version->idConfigTipoExamenPreAnestesico,
            'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => '¿Fumás?',
            'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => true,
        ]);

        $opcionAjena = ConfigTipoExamenPreAnestesicoPreguntaRespuesta::create([
            'idConfigTipoExamenPreAnestesicoPregunta' => $otraPregunta->idConfigTipoExamenPreAnestesicoPregunta,
            'nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => 'Sí, 10 por día',
        ]);

        // Enviar la opción de «¿Fumás?» como respuesta de «¿Tenés alergia...?»
        $respuestasInvalidas = [
            $this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta => $opcionAjena->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
            $otraPregunta->idConfigTipoExamenPreAnestesicoPregunta => $opcionAjena->idConfigTipoExamenPreAnestesicoPreguntaRespuesta,
            $this->preguntaAbierta->idConfigTipoExamenPreAnestesicoPregunta => 'Ninguna',
        ];

        $this->responder($respuestasInvalidas)
            ->assertSessionHasErrors([
                'respuestas.'.$this->preguntaCerrada->idConfigTipoExamenPreAnestesicoPregunta,
            ]);

        $this->assertDatabaseMissing('ExamenCirugiaPreAnestesica', [
            'idCirugia' => $this->cirugia->idCirugia,
        ]);
    }

    public function test_estado_vacio_si_no_hay_cuestionario_vigente(): void
    {
        // Cerramos la versión vigente
        $this->version->update(['fechaFinVigeConfigTipoExamenPreAnestesico' => now()]);

        // La vista muestra el estado vacío
        $this->pestana()
            ->assertOk()
            ->assertSee('No hay un cuestionario preanestésico disponible para responder en este momento.')
            ->assertDontSee('Enviar respuestas');

        // Un intento de POST devuelve error sin romper
        $this->responder(['respuestas' => []])
            ->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'preanestesica']))
            ->assertSessionHas('error', 'No hay un cuestionario preanestésico vigente disponible.');
    }
}
