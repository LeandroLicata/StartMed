<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Mail\AvisoEmailCambiado;
use App\Mail\ConfirmarCambioEmail;
use App\Models\Persona;
use App\Support\EnlaceCambioEmail;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cambio de email desde el perfil: pedir con sesion -> link de un solo uso al
 * email nuevo -> boton «Confirmar», con o sin sesion. Lo que se cuida: que el
 * email no cambie hasta confirmar, que el link sirva una vez y no lo pise uno
 * viejo, y que el dueño del email anterior se entere.
 */
class PortalCambioEmailTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL_ACTUAL = 'maria.garcia@example.com';

    private const EMAIL_NUEVO = 'maria.nueva@example.com';

    private Persona $maria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->maria->update(['contacto_email_direccion' => self::EMAIL_ACTUAL]);
    }

    private function pedir(string $email = self::EMAIL_NUEVO)
    {
        return $this->actingAs($this->maria, 'paciente')
            ->post(route('portal.perfil.email'), ['email_nuevo' => $email]);
    }

    /** El link del ultimo mail de confirmacion enviado a ese email. */
    private function enlaceEnviadoA(string $email): string
    {
        $enlace = null;

        Mail::assertSent(ConfirmarCambioEmail::class, function (ConfirmarCambioEmail $mail) use ($email, &$enlace) {
            if ($mail->hasTo($email)) {
                $enlace = $mail->enlace;
            }

            return $mail->hasTo($email);
        });

        return $enlace;
    }

    private function emailDeMaria(): ?string
    {
        return $this->maria->fresh()->contacto_email_direccion;
    }

    // --- Pedir -------------------------------------------------------------

    public function test_pedir_manda_el_link_al_email_nuevo_y_todavia_no_lo_cambia(): void
    {
        Mail::fake();

        $this->pedir()
            ->assertRedirect(route('portal.perfil'))
            ->assertSessionHas('info');

        Mail::assertSent(ConfirmarCambioEmail::class, fn ($mail) => $mail->hasTo(self::EMAIL_NUEVO));
        Mail::assertNotSent(ConfirmarCambioEmail::class, fn ($mail) => $mail->hasTo(self::EMAIL_ACTUAL));
        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());
    }

    public function test_el_link_no_lleva_el_email_nuevo(): void
    {
        Mail::fake();

        $this->pedir();

        $this->assertStringNotContainsString('maria.nueva', urldecode($this->enlaceEnviadoA(self::EMAIL_NUEVO)));
    }

    public function test_pedir_sin_sesion_no_se_puede(): void
    {
        Mail::fake();

        $this->post(route('portal.perfil.email'), ['email_nuevo' => self::EMAIL_NUEVO])
            ->assertRedirect(route('portal.ingresar'));

        Mail::assertNothingSent();
    }

    public function test_un_email_invalido_no_manda_nada(): void
    {
        Mail::fake();

        $this->pedir('no-es-un-email')->assertSessionHasErrors('email_nuevo');

        Mail::assertNothingSent();
    }

    public function test_pedir_el_mismo_email_con_otras_mayusculas_se_rechaza(): void
    {
        Mail::fake();

        $this->pedir(strtoupper(self::EMAIL_ACTUAL))
            ->assertSessionHasErrors(['email_nuevo' => 'Ese ya es tu email actual.']);

        Mail::assertNothingSent();
    }

    // --- Abrir y confirmar -------------------------------------------------

    public function test_abrir_el_link_muestra_el_boton_y_no_cambia_nada(): void
    {
        Mail::fake();
        $this->pedir();
        $enlace = $this->enlaceEnviadoA(self::EMAIL_NUEVO);

        // Un cliente de correo que analiza el link hace este GET, varias veces.
        $this->get($enlace)->assertOk()->assertSee('Confirmá tu nuevo email')->assertSee(self::EMAIL_NUEVO);
        $this->get($enlace)->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());
        Mail::assertNotSent(AvisoEmailCambiado::class);
    }

    public function test_confirmar_cambia_el_email_y_avisa_al_anterior(): void
    {
        Mail::fake();
        $this->pedir();
        $enlace = $this->enlaceEnviadoA(self::EMAIL_NUEVO);

        $this->post($enlace)
            ->assertRedirect(route('portal.perfil'))
            ->assertSessionHas('exito');

        $this->assertSame(self::EMAIL_NUEVO, $this->emailDeMaria());
        Mail::assertSent(AvisoEmailCambiado::class, fn ($mail) => $mail->hasTo(self::EMAIL_ACTUAL));
    }

    public function test_el_aviso_al_email_anterior_no_dice_cual_es_el_nuevo(): void
    {
        $html = (new AvisoEmailCambiado('María'))->render();

        $this->assertStringNotContainsString(self::EMAIL_NUEVO, $html);
        $this->assertStringNotContainsString('href', $html);
    }

    public function test_se_puede_confirmar_sin_sesion_desde_otro_dispositivo(): void
    {
        Mail::fake();

        // Sin actingAs: el link se abre donde el paciente no entro al portal.
        $enlace = EnlaceCambioEmail::emitir($this->maria, self::EMAIL_NUEVO);

        $this->get($enlace)->assertOk();
        $this->post($enlace)
            ->assertRedirect(route('portal.ingresar'))
            ->assertSessionHas('exito');

        $this->assertSame(self::EMAIL_NUEVO, $this->emailDeMaria());
        $this->assertGuest('paciente');
    }

    public function test_el_link_sirve_una_sola_vez(): void
    {
        Mail::fake();
        $enlace = EnlaceCambioEmail::emitir($this->maria, self::EMAIL_NUEVO);

        $this->post($enlace);
        $this->maria->update(['contacto_email_direccion' => 'otro.cambio@example.com']);

        $this->get($enlace)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        $this->post($enlace)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');

        $this->assertSame('otro.cambio@example.com', $this->emailDeMaria());
    }

    public function test_un_link_viejo_no_pisa_el_email_pedido_despues(): void
    {
        Mail::fake();
        $viejo = EnlaceCambioEmail::emitir($this->maria, 'primero@example.com');
        $nuevo = EnlaceCambioEmail::emitir($this->maria, 'segundo@example.com');

        $this->post($viejo)->assertSessionHas('error');
        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());

        $this->post($nuevo)->assertSessionHas('exito');
        $this->assertSame('segundo@example.com', $this->emailDeMaria());
    }

    public function test_el_link_vencido_no_cambia_nada(): void
    {
        $enlace = EnlaceCambioEmail::emitir($this->maria, self::EMAIL_NUEVO);

        $this->travel(EnlaceCambioEmail::VIGENCIA_MINUTOS + 1)->minutes();

        $this->post($enlace)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());
    }

    public function test_el_link_con_la_firma_alterada_no_cambia_nada(): void
    {
        $enlace = EnlaceCambioEmail::emitir($this->maria, self::EMAIL_NUEVO);
        $alterado = preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('0', 64), $enlace);

        $this->post($alterado)->assertSessionHas('error');
        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());
    }

    public function test_el_nonce_de_otra_persona_no_sirve(): void
    {
        $otra = Persona::where('idPersona', '!=', $this->maria->idPersona)->firstOrFail();
        $enlaceDeOtra = EnlaceCambioEmail::emitir($otra, 'de.otra@example.com');

        // Mismo nonce, otra persona en la URL: la firma no coincide.
        $cambiado = str_replace("/{$otra->idPersona}/", "/{$this->maria->idPersona}/", $enlaceDeOtra);

        $this->post($cambiado)->assertSessionHas('error');
        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());
    }

    public function test_una_persona_dada_de_baja_no_cambia_el_email(): void
    {
        $enlace = EnlaceCambioEmail::emitir($this->maria, self::EMAIL_NUEVO);
        $this->maria->update(['fechaHoraBajaPersona' => now()]);

        $this->get($enlace)->assertSessionHas('error');
        $this->post($enlace)->assertSessionHas('error');
        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());
    }

    public function test_el_flujo_completo_desde_el_formulario_del_perfil(): void
    {
        Mail::fake();

        $this->actingAs($this->maria, 'paciente')
            ->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee(route('portal.perfil.email'), false);

        $this->pedir();
        $enlace = $this->enlaceEnviadoA(self::EMAIL_NUEVO);

        $this->get($enlace)->assertOk();
        $this->post($enlace)->assertRedirect(route('portal.perfil'));

        $this->get(route('portal.perfil'))->assertSee(self::EMAIL_NUEVO);
    }

    // --- Mailer que deja el link legible en el servidor --------------------

    public function test_con_un_mailer_que_expone_los_mails_no_se_emite_ni_se_manda(): void
    {
        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);
        $this->withoutMiddleware(PreventRequestForgery::class);
        Mail::fake();
        Log::spy();

        $this->pedir()
            ->assertRedirect(route('portal.perfil'))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
        $this->assertSame(self::EMAIL_ACTUAL, $this->emailDeMaria());
    }
}
