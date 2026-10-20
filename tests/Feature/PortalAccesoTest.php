<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Mail\EnlaceAccesoPortal;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Support\EnlaceAccesoPaciente;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El acceso al portal: DNI -> mail con link firmado de un solo uso -> boton
 * «Entrar». Lo que se cuida aca es que el link sirva una sola vez, que no lo
 * gaste quien solo lo abre, y que nada de la respuesta delate que DNI existen.
 */
class PortalAccesoTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'maria.garcia@example.com';

    private Persona $maria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        // Explicito: el test no depende de lo que haya sembrado el DemoSeeder.
        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->maria->update(['contacto_email_direccion' => self::EMAIL]);
    }

    private function pedir(string $documento)
    {
        return $this->post(route('portal.acceso.solicitar'), ['documento' => $documento]);
    }

    /** Un link recien emitido, sin pasar por el mail. */
    private function enlace(?Persona $persona = null): string
    {
        return EnlaceAccesoPaciente::emitir($persona ?? $this->maria);
    }

    /** El POST del boton «Entrar» es a la misma URL firmada del link. */
    private function entrar(string $enlace)
    {
        return $this->post($enlace);
    }

    private function crearPersona(string $documento, string $tipo = 'DNI', ?string $email = 'otro@example.com'): Persona
    {
        return Persona::create([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', $tipo)->value('idTipoDocumento'),
            'documento' => $documento,
            'apellidos' => 'Prueba',
            'nombres' => 'Paciente',
            'contacto_email_direccion' => $email,
        ]);
    }

    // --- Pedir el link -----------------------------------------------------

    public function test_pedir_el_link_con_un_dni_existente_manda_el_mail_con_un_enlace_firmado(): void
    {
        Mail::fake();

        $this->pedir(AccesoController::DOCUMENTO_DEMO)->assertRedirect(route('portal.ingresar'));

        Mail::assertSent(EnlaceAccesoPortal::class, function (EnlaceAccesoPortal $mail) {
            $this->assertTrue($mail->hasTo(self::EMAIL));
            $this->assertStringContainsString('/portal/acceso/'.$this->maria->idPersona.'/', $mail->enlace);
            $this->assertStringContainsString('signature=', $mail->enlace);
            $this->assertStringContainsString('expires=', $mail->enlace);

            return true;
        });
        // Sin cola: en el deploy no hay worker que lo saque.
        Mail::assertNotQueued(EnlaceAccesoPortal::class);
    }

    public function test_el_dni_se_acepta_con_puntos_y_espacios(): void
    {
        Mail::fake();

        $this->pedir(' 28.456.789 ')->assertRedirect(route('portal.ingresar'));

        Mail::assertSent(EnlaceAccesoPortal::class, 1);
    }

    public function test_la_respuesta_es_identica_exista_o_no_el_dni(): void
    {
        Mail::fake();

        // Son seis pedidos seguidos desde la misma IP; el limite por IP tiene su propio test.
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->crearPersona('11111111', email: null);                    // existe, sin email
        $this->crearPersona('22222222', email: 'a@example.com');         // duplicado...
        $this->crearPersona('22222222', email: 'b@example.com');         // ...del mismo DNI
        $baja = $this->crearPersona('33333333');                         // dada de baja
        $baja->update(['fechaHoraBajaPersona' => now()]);

        // Existe y tiene email, pero ya gasto sus tres pedidos de la ventana.
        $this->crearPersona('44444444', email: 'limitado@example.com');
        foreach (range(1, 3) as $_) {
            RateLimiter::hit('portal-acceso:dni:44444444', 900);
        }

        $casos = [
            'existe con email' => AccesoController::DOCUMENTO_DEMO,
            'no existe' => '99999999',
            'existe sin email' => '11111111',
            'duplicado' => '22222222',
            'dada de baja' => '33333333',
            'limitado' => '44444444',
        ];

        $respuestas = [];

        foreach ($casos as $caso => $documento) {
            $respuesta = $this->pedir($documento);
            $respuestas[$caso] = [
                $respuesta->getStatusCode(),
                $respuesta->headers->get('Location'),
                session('info'),
                session('error'),
            ];
        }

        $esperada = reset($respuestas);

        $this->assertSame(302, $esperada[0]);
        $this->assertNotNull($esperada[2]);
        // La orientacion para quien no recibio el mail va en el texto de todos los casos.
        $this->assertStringContainsString('consultá en recepción', $esperada[2]);

        foreach ($respuestas as $caso => $respuesta) {
            $this->assertSame($esperada, $respuesta, "La respuesta de «{$caso}» se distingue de la de un DNI existente");
        }

        // Y de los seis, solo el primero mando mail: el limitado tampoco.
        Mail::assertSent(EnlaceAccesoPortal::class, 1);
        Mail::assertNotSent(EnlaceAccesoPortal::class, fn ($mail) => $mail->hasTo('limitado@example.com'));
    }

    public function test_dos_personas_activas_con_el_mismo_dni_no_reciben_nada_y_queda_un_warning(): void
    {
        Mail::fake();
        Log::spy();

        $this->crearPersona('22222222', email: 'a@example.com');
        $this->crearPersona('22222222', email: 'b@example.com');

        $this->pedir('22222222')->assertRedirect(route('portal.ingresar'));

        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_un_documento_de_otro_tipo_no_cuenta_como_dni(): void
    {
        Mail::fake();

        // Mismo numero que María pero es un pasaporte: no la vuelve ambigua ni recibe el link.
        $this->crearPersona(AccesoController::DOCUMENTO_DEMO, 'Pasaporte', 'pasaporte@example.com');

        $this->pedir(AccesoController::DOCUMENTO_DEMO);

        Mail::assertSent(EnlaceAccesoPortal::class, fn ($mail) => $mail->hasTo(self::EMAIL));
        Mail::assertNotSent(EnlaceAccesoPortal::class, fn ($mail) => $mail->hasTo('pasaporte@example.com'));
    }

    public function test_un_dni_mal_formado_vuelve_al_formulario_con_el_error(): void
    {
        Mail::fake();

        $this->from(route('portal.ingresar'))
            ->post(route('portal.acceso.solicitar'), ['documento' => 'abc'])
            ->assertRedirect(route('portal.ingresar'))
            ->assertSessionHasErrors('documento');

        $this->from(route('portal.ingresar'))
            ->post(route('portal.acceso.solicitar'), [])
            ->assertSessionHasErrors('documento');

        Mail::assertNothingSent();
    }

    // --- Limites -----------------------------------------------------------

    public function test_el_limite_por_dni_corta_el_mail_pero_no_cambia_la_respuesta(): void
    {
        Mail::fake();

        $respuestas = [];

        foreach (range(1, 4) as $_) {
            $respuesta = $this->pedir(AccesoController::DOCUMENTO_DEMO);
            $respuestas[] = [$respuesta->getStatusCode(), $respuesta->headers->get('Location'), session('info')];
        }

        // Tres pedidos por DNI en la ventana: el cuarto responde igual y no manda nada.
        Mail::assertSent(EnlaceAccesoPortal::class, 3);
        $this->assertCount(1, array_unique($respuestas, SORT_REGULAR));
    }

    public function test_el_limite_por_dni_se_cuenta_tambien_si_el_dni_no_existe(): void
    {
        Mail::fake();

        foreach (range(1, 4) as $_) {
            $this->pedir('99999999')->assertRedirect(route('portal.ingresar'));
        }

        // Misma clave y mismo tope que para un DNI real: si solo contara los
        // existentes, el limite mismo delataria cuales son.
        $this->assertSame(3, RateLimiter::attempts('portal-acceso:dni:99999999'));
        Mail::assertNothingSent();
    }

    public function test_el_post_tiene_un_limite_por_ip(): void
    {
        Mail::fake();

        foreach (range(1, 5) as $n) {
            $this->pedir("1000000{$n}")->assertRedirect(route('portal.ingresar'));
        }

        $this->pedir('10000006')->assertStatus(429);
    }

    // --- El link -----------------------------------------------------------

    public function test_el_link_valido_muestra_el_boton_pero_solo_el_boton_autentica(): void
    {
        $enlace = $this->enlace();

        $this->get($enlace)
            ->assertOk()
            ->assertSee('Entrar')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        // Abrir el link no inicia sesion: eso es el POST del boton.
        $this->assertGuest('paciente');

        $this->entrar($enlace)->assertRedirect(route('portal.inicio'));

        $this->assertAuthenticatedAs($this->maria, 'paciente');
        $this->get(route('portal.inicio'))->assertOk()->assertSee('García, María');
    }

    public function test_abrir_el_link_varias_veces_no_lo_gasta(): void
    {
        $enlace = $this->enlace();

        // Lo que hace un cliente de correo que analiza los links.
        $this->get($enlace)->assertOk();
        $this->get($enlace)->assertOk();

        $this->entrar($enlace)->assertRedirect(route('portal.inicio'));
        $this->assertAuthenticatedAs($this->maria, 'paciente');
    }

    public function test_el_link_sirve_una_sola_vez(): void
    {
        $enlace = $this->enlace();

        $this->entrar($enlace)->assertRedirect(route('portal.inicio'));
        $this->post(route('portal.salir'));
        $this->assertGuest('paciente');

        $this->entrar($enlace)
            ->assertRedirect(route('portal.ingresar'))
            ->assertSessionHas('error');
        $this->assertGuest('paciente');

        $this->get($enlace)
            ->assertRedirect(route('portal.ingresar'))
            ->assertSessionHas('error');
    }

    public function test_pedir_otro_link_deja_sin_efecto_el_anterior(): void
    {
        $viejo = $this->enlace();
        $nuevo = $this->enlace();

        $this->entrar($viejo)->assertRedirect(route('portal.ingresar'));
        $this->assertGuest('paciente');

        // Y el viejo, al fallar, no se llevo el nonce del nuevo.
        $this->entrar($nuevo)->assertRedirect(route('portal.inicio'));
        $this->assertAuthenticatedAs($this->maria, 'paciente');
    }

    public function test_el_link_vencido_no_entra_y_manda_de_vuelta_al_dni(): void
    {
        $enlace = $this->enlace();

        $this->travel(EnlaceAccesoPaciente::VIGENCIA_MINUTOS + 1)->minutes();

        $this->get($enlace)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        $this->entrar($enlace)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        $this->assertGuest('paciente');
    }

    public function test_el_link_con_la_firma_alterada_no_entra(): void
    {
        $enlace = $this->enlace();

        $otraPersona = preg_replace('#/acceso/\d+/#', '/acceso/'.($this->maria->idPersona + 1).'/', $enlace);
        $sinFirma = strtok($enlace, '?');
        $firmaMala = preg_replace('/signature=[^&]+/', 'signature=inventada', $enlace);

        foreach ([$otraPersona, $sinFirma, $firmaMala] as $adulterado) {
            $this->get($adulterado)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
            $this->entrar($adulterado)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        }

        $this->assertGuest('paciente');

        // El link original, intacto, sigue sirviendo: lo adulterado no gasto el nonce.
        $this->entrar($enlace)->assertRedirect(route('portal.inicio'));
    }

    public function test_el_link_de_una_persona_dada_de_baja_no_entra(): void
    {
        $enlace = $this->enlace();

        $this->maria->update(['fechaHoraBajaPersona' => now()]);

        $this->entrar($enlace)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        $this->assertGuest('paciente');
    }

    public function test_el_flujo_completo_pedir_abrir_el_mail_y_entrar(): void
    {
        Mail::fake();

        $this->pedir(AccesoController::DOCUMENTO_DEMO);

        $enlace = null;
        Mail::assertSent(EnlaceAccesoPortal::class, function (EnlaceAccesoPortal $mail) use (&$enlace) {
            $enlace = $mail->enlace;

            return true;
        });

        $this->get($enlace)->assertOk();
        $this->entrar($enlace)->assertRedirect(route('portal.inicio'));
        $this->assertAuthenticatedAs($this->maria, 'paciente');
    }

    // --- El lock del nonce -------------------------------------------------

    private function claveLock(Persona $persona): string
    {
        return "portal:acceso:{$persona->idPersona}:lock";
    }

    public function test_el_store_de_cache_soporta_locks(): void
    {
        $lock = Cache::lock('portal:prueba-lock', 5);

        $this->assertTrue($lock->get());
        $this->assertFalse(Cache::lock('portal:prueba-lock', 5)->get());

        $lock->release();
        $this->assertTrue(Cache::lock('portal:prueba-lock', 5)->get());
    }

    public function test_si_no_se_consigue_el_lock_el_link_se_trata_como_invalido_y_no_toca_el_nonce(): void
    {
        $viejo = $this->enlace();
        $nuevo = $this->enlace();

        // Otro pedido de la misma persona esta a mitad de verificar/emitir.
        $otro = Cache::lock($this->claveLock($this->maria), 10);
        $this->assertTrue($otro->get());

        // El link viejo no puede llevarse el nonce del nuevo...
        $this->entrar($viejo)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        // ...y ni siquiera el nuevo entra mientras no se pueda verificar con seguridad.
        $this->entrar($nuevo)->assertRedirect(route('portal.ingresar'))->assertSessionHas('error');
        $this->assertGuest('paciente');

        // Soltado el lock, el nonce del nuevo seguia intacto.
        $otro->release();
        $this->entrar($nuevo)->assertRedirect(route('portal.inicio'));
        $this->assertAuthenticatedAs($this->maria, 'paciente');
    }

    public function test_emitir_y_consumir_usan_el_mismo_lock(): void
    {
        $enlace = $this->enlace();

        $otro = Cache::lock($this->claveLock($this->maria), 10);
        $this->assertTrue($otro->get());

        // Emitir espera el mismo lock que consumir: si fuera otra clave, no se
        // excluirian y el lock no protegeria nada.
        try {
            $this->enlace();
            $this->fail('emitir() debio esperar el lock que consumir() tambien usa');
        } catch (LockTimeoutException) {
            $this->addToAssertionCount(1);
        }

        $otro->release();
        $this->entrar($enlace)->assertRedirect(route('portal.inicio'));
    }

    public function test_si_falla_el_lock_al_emitir_no_sale_mail_y_la_respuesta_es_la_de_siempre(): void
    {
        Mail::fake();
        Log::spy();

        $esperada = $this->pedir('99999999');
        $esperada = [$esperada->getStatusCode(), $esperada->headers->get('Location'), session('info')];

        $otro = Cache::lock($this->claveLock($this->maria), 10);
        $this->assertTrue($otro->get());

        $respuesta = $this->pedir(AccesoController::DOCUMENTO_DEMO);

        $this->assertSame($esperada, [$respuesta->getStatusCode(), $respuesta->headers->get('Location'), session('info')]);
        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')->once();

        $otro->release();
    }

    // --- Mailer que deja el link legible en el servidor --------------------

    /** Fuera de `testing` Laravel exige el token CSRF, que estos tests no estan probando. */
    private function simularEntorno(string $entorno, string $mailer): void
    {
        $this->app['env'] = $entorno;
        config(['mail.default' => $mailer]);
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public static function mailers(): array
    {
        return [
            'produccion con log' => ['production', 'log', true],
            'produccion con array' => ['production', 'array', true],
            'staging con log' => ['staging', 'log', true],
            'failover que incluye log' => ['production', 'failover', true],
            'roundrobin sin log ni array' => ['production', 'roundrobin', false],
            'produccion con smtp' => ['production', 'smtp', false],
            'local con log sigue enviando' => ['local', 'log', false],
            'testing con log sigue enviando' => ['testing', 'log', false],
        ];
    }

    #[DataProvider('mailers')]
    public function test_detecta_si_el_mailer_deja_el_enlace_legible(string $entorno, string $mailer, bool $expone): void
    {
        $this->app['env'] = $entorno;
        config(['mail.default' => $mailer]);

        $this->assertSame($expone, EnlaceAccesoPaciente::mailerExponeElEnlace());
    }

    public function test_un_failover_o_roundrobin_se_revisa_mailer_por_mailer(): void
    {
        $this->app['env'] = 'production';
        config([
            'mail.default' => 'compuesto',
            'mail.mailers.compuesto' => ['transport' => 'failover', 'mailers' => ['smtp', 'anidado']],
            'mail.mailers.anidado' => ['transport' => 'roundrobin', 'mailers' => ['ses', 'log']],
        ]);

        $this->assertTrue(EnlaceAccesoPaciente::mailerExponeElEnlace());

        config(['mail.mailers.anidado.mailers' => ['ses', 'postmark']]);
        $this->assertFalse(EnlaceAccesoPaciente::mailerExponeElEnlace());

        // Un ciclo entre mailers no cuelga la verificacion.
        config(['mail.mailers.anidado.mailers' => ['compuesto']]);
        $this->assertFalse(EnlaceAccesoPaciente::mailerExponeElEnlace());
    }

    #[DataProvider('mailersQueExponen')]
    public function test_con_un_mailer_que_deja_el_enlace_legible_no_se_emite_ni_se_manda_nada(string $entorno, string $mailer): void
    {
        $this->simularEntorno($entorno, $mailer);
        Mail::fake();
        Log::spy();

        $normal = $this->pedir('99999999');
        $respuesta = $this->pedir(AccesoController::DOCUMENTO_DEMO);

        // La respuesta al paciente es la de siempre.
        $this->assertSame($normal->headers->get('Location'), $respuesta->headers->get('Location'));
        $this->assertSame(302, $respuesta->getStatusCode());
        $this->assertStringContainsString('consultá en recepción', session('info'));

        // No hay mail ni nonce: no existe nada que alguien pueda leer en un log.
        Mail::assertNothingSent();
        $this->assertFalse(Cache::has("portal:acceso:{$this->maria->idPersona}"));

        // Y el error no lleva el link, la firma, el token ni el DNI.
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $mensaje, array $contexto = []) {
            $todo = $mensaje.json_encode($contexto);

            foreach (['/portal/acceso', 'signature', 'expires', 'nonce', AccesoController::DOCUMENTO_DEMO] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $todo);
            }

            return true;
        });
    }

    public static function mailersQueExponen(): array
    {
        return [
            'produccion con log' => ['production', 'log'],
            'produccion con array' => ['production', 'array'],
            'staging con log' => ['staging', 'log'],
            'produccion con failover que incluye log' => ['production', 'failover'],
        ];
    }

    #[DataProvider('mailersQueEnvian')]
    public function test_con_un_mailer_real_o_en_local_el_enlace_sigue_saliendo(string $entorno, string $mailer): void
    {
        $this->simularEntorno($entorno, $mailer);
        Mail::fake();

        $this->pedir(AccesoController::DOCUMENTO_DEMO);

        Mail::assertSent(EnlaceAccesoPortal::class, fn ($mail) => $mail->hasTo(self::EMAIL));
    }

    public static function mailersQueEnvian(): array
    {
        return [
            'produccion con smtp' => ['production', 'smtp'],
            'local con log' => ['local', 'log'],
        ];
    }

    // --- Aislamiento entre guards ------------------------------------------

    public function test_entrar_por_el_link_no_abre_el_staff_ni_cierra_su_sesion(): void
    {
        $staff = Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
        $this->actingAs($staff);

        $this->entrar($this->enlace())->assertRedirect(route('portal.inicio'));

        // Cada guard tiene su sesion: el paciente no se vuelve staff ni el staff deja de serlo.
        $this->assertAuthenticatedAs($this->maria, 'paciente');
        $this->assertAuthenticatedAs($staff, 'web');
    }

    public function test_un_paciente_por_el_link_no_entra_a_las_pantallas_del_staff(): void
    {
        $this->entrar($this->enlace());

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/cirujano')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_una_sesion_de_staff_no_abre_el_portal(): void
    {
        $this->actingAs(Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail());

        $this->get(route('portal.inicio'))->assertRedirect(route('portal.ingresar'));
    }

    public function test_salir_cierra_solo_al_paciente(): void
    {
        $staff = Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
        $this->actingAs($staff);
        $this->entrar($this->enlace());

        $this->post(route('portal.salir'))->assertRedirect(route('portal.ingresar'));

        $this->assertGuest('paciente');
        $this->assertAuthenticatedAs($staff, 'web');
    }

    // --- Pantallas ---------------------------------------------------------

    public function test_la_pantalla_de_ingreso_pide_el_dni(): void
    {
        $this->get(route('portal.ingresar'))
            ->assertOk()
            ->assertSee('name="documento"', false)
            ->assertSee(route('portal.acceso.solicitar'), false);
    }

    public function test_la_nav_del_portal_tiene_las_tres_secciones_con_etiquetas_cortas(): void
    {
        $this->actingAs($this->maria, 'paciente');

        $this->get(route('portal.inicio'))
            ->assertOk()
            ->assertSee(route('portal.inicio'), false)
            ->assertSee(route('portal.historial'), false)
            ->assertSee(route('portal.perfil'), false)
            ->assertSeeInOrder(['Próximas', 'Historial', 'Perfil']);
    }
}
