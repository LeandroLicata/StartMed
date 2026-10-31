<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Mail\MensajePacienteAlEquipo;
use App\Models\Cirugia;
use App\Models\Persona;
use App\Models\Personal;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalContactoTest extends TestCase
{
    use RefreshDatabase;

    private Persona $maria;

    private Cirugia $cirugia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->actingAs($this->maria, 'paciente');

        // La cirugía futura de María (mañana a las 09:00).
        $this->cirugia = Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->where('fechaHoraCirugia', '>', today()->endOfDay())
            ->firstOrFail();
    }

    public function test_la_pestana_muestra_whatsapp_telefono_email_y_direccion_cuando_estan_configurados(): void
    {
        config([
            'portal.whatsapp_hospital' => '5492994000000',
            'portal.telefono_hospital' => '0299 400-0000',
            'portal.email_hospital' => 'contacto@hospital.test',
            'portal.direccion_hospital' => 'Av. Argentina 1234',
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/contacto");

        $response->assertOk()
            ->assertSee('Hablá con el hospital')
            ->assertSee('https://wa.me/5492994000000', false)
            ->assertSee('0299 400-0000')
            ->assertSee('contacto@hospital.test')
            ->assertSee('Av. Argentina 1234')
            ->assertDontSee('Consultá en recepción del hospital.');
    }

    public function test_la_pestana_no_muestra_campos_no_configurados_y_muestra_estado_vacio_si_no_hay_ninguno(): void
    {
        config([
            'portal.whatsapp_hospital' => null,
            'portal.telefono_hospital' => null,
            'portal.email_hospital' => null,
            'portal.direccion_hospital' => null,
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/contacto");

        $response->assertOk()
            ->assertDontSee('https://wa.me/', false)
            ->assertDontSee('tel:', false)
            ->assertDontSee('mailto:', false)
            ->assertSee('Consultá en recepción del hospital.');
    }

    public function test_el_link_de_whatsapp_no_contiene_el_dni_del_paciente(): void
    {
        config([
            'portal.whatsapp_hospital' => '5492994000000',
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/contacto");

        $response->assertOk();

        preg_match('/href="(https:\/\/wa\.me\/[^"]+)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'No se encontró el enlace de WhatsApp en la respuesta.');

        $waLink = $matches[1];
        $this->assertStringNotContainsString(
            $this->maria->documento,
            $waLink,
            'El enlace de WhatsApp contiene el DNI del paciente.',
        );
    }

    public function test_enviar_un_mensaje_valido_manda_un_mail_a_cada_gestor_con_mail_valido_y_no_escribe_en_ninguna_tabla(): void
    {
        Mail::fake();

        // Aseguramos al menos un gestor con mail institucional válido.
        $gestor = Personal::whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', 'Gestor de quirófano'))->firstOrFail();
        $gestor->update(['mailInstitucional' => 'gestor@hospital.test']);

        $escrituras = 0;
        DB::listen(function ($query) use (&$escrituras) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
                $escrituras++;
            }
        });

        $response = $this->post("/portal/cirugias/{$this->cirugia->idCirugia}/contacto", [
            'mensaje' => 'Hola, quería consultar sobre los horarios de llegada y preparación.',
        ]);

        $response->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'contacto']));
        $response->assertSessionHas('exito', 'Listo, le mandamos tu mensaje al equipo. Te van a responder por teléfono o email.');

        $this->assertSame(0, $escrituras, 'El envío de contacto escribió en la base de datos y no debía hacerlo.');

        Mail::assertSent(MensajePacienteAlEquipo::class, function (MensajePacienteAlEquipo $mail) {
            return $mail->hasTo('gestor@hospital.test')
                && $mail->hasReplyTo($this->maria->contacto_email_direccion)
                && $mail->mensaje === 'Hola, quería consultar sobre los horarios de llegada y preparación.'
                && $mail->dni === $this->maria->documento
                && $mail->idCirugia === $this->cirugia->idCirugia;
        });
    }

    public function test_mensaje_vacio_o_muy_corto_da_error_de_validacion_y_no_manda_mail(): void
    {
        Mail::fake();

        // Vacío
        $this->from("/portal/cirugias/{$this->cirugia->idCirugia}/contacto")
            ->post("/portal/cirugias/{$this->cirugia->idCirugia}/contacto", ['mensaje' => ''])
            ->assertRedirect("/portal/cirugias/{$this->cirugia->idCirugia}/contacto")
            ->assertSessionHasErrors('mensaje');

        // Menor a 10 caracteres
        $this->from("/portal/cirugias/{$this->cirugia->idCirugia}/contacto")
            ->post("/portal/cirugias/{$this->cirugia->idCirugia}/contacto", ['mensaje' => 'Hola'])
            ->assertRedirect("/portal/cirugias/{$this->cirugia->idCirugia}/contacto")
            ->assertSessionHasErrors('mensaje');

        Mail::assertNothingSent();
    }

    public function test_cirugia_anterior_no_muestra_formulario_y_el_post_no_manda_mail(): void
    {
        Mail::fake();

        // Trasladamos la cirugía al pasado.
        $this->cirugia->update([
            'fechaHoraCirugia' => now()->subDays(2),
        ]);

        // GET: no debe haber formulario ni botón de enviar mensaje.
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/contacto");
        $response->assertOk()
            ->assertDontSee('name="mensaje"', false)
            ->assertDontSee('Enviar mensaje');

        // POST: se rechaza con aviso y no manda mail.
        $postResponse = $this->post("/portal/cirugias/{$this->cirugia->idCirugia}/contacto", [
            'mensaje' => 'Consulta sobre una cirugía que ya pasó.',
        ]);

        $postResponse->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'contacto']));
        $postResponse->assertSessionHas('aviso');

        Mail::assertNothingSent();
    }

    public function test_cirugia_ajena_da_404_en_get_y_post(): void
    {
        Mail::fake();

        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();

        $this->get("/portal/cirugias/{$ajena->idCirugia}/contacto")->assertNotFound();

        $this->post("/portal/cirugias/{$ajena->idCirugia}/contacto", [
            'mensaje' => 'Intento de enviar mensaje a cirugía ajena.',
        ])->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_cuando_el_paciente_no_tiene_email_no_se_define_reply_to(): void
    {
        Mail::fake();

        $this->maria->update(['contacto_email_direccion' => null]);

        $gestor = Personal::whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', 'Gestor de quirófano'))->firstOrFail();
        $gestor->update(['mailInstitucional' => 'gestor@hospital.test']);

        $this->post("/portal/cirugias/{$this->cirugia->idCirugia}/contacto", [
            'mensaje' => 'Consulta de un paciente que no tiene email registrado.',
        ])->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'contacto']));

        Mail::assertSent(MensajePacienteAlEquipo::class, function (MensajePacienteAlEquipo $mail) {
            return empty($mail->replyToEmail);
        });
    }

    public function test_si_no_hay_gestores_con_mail_responde_con_exito_y_registra_warning(): void
    {
        Mail::fake();

        // Vaciamos los mails institucionales de los gestores.
        Personal::whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', 'Gestor de quirófano'))
            ->update(['mailInstitucional' => null]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($mensaje, $contexto) {
                // Debe registrar sin el contenido del mensaje del paciente
                return str_contains($mensaje, 'no hay gestores')
                    && isset($contexto['idCirugia'])
                    && ! isset($contexto['mensaje']);
            });

        $response = $this->post("/portal/cirugias/{$this->cirugia->idCirugia}/contacto", [
            'mensaje' => 'Hola, este mensaje no tiene gestores destinatarios configurados.',
        ]);

        $response->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'contacto']));
        $response->assertSessionHas('exito');

        Mail::assertNothingSent();
    }

    public function test_la_pestana_muestra_el_equipo_asignado_o_estado_vacio_si_falta(): void
    {
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/contacto");
        $response->assertOk()->assertSee('Tu equipo');

        // Si desasignamos cirujano y anestesista:
        $this->cirugia->update([
            'idPersonalCirujano' => null,
            'idPersonalAnestesista' => null,
        ]);

        $responseSinEquipo = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/contacto");
        $responseSinEquipo->assertOk()
            ->assertSee('Tu equipo médico todavía no fue asignado.');
    }
}
