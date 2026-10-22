<?php

namespace Tests\Feature;

use App\Http\Middleware\SesionPorArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El portal y el staff tienen cada uno su cookie de sesion (SesionPorArea).
 *
 * Compartiendo una, salir del portal regeneraba el token CSRF y daba 419 en el
 * login del staff abierto en otra pestaña, y salir del staff cerraba tambien la
 * sesion del paciente.
 *
 * Lo que se verifica aca es el mecanismo: que cada area entregue y lea su propia
 * cookie, con un id de sesion distinto. No se puede probar "sigue logueado
 * despues del logout del otro" con requests de test: el store de sesion se
 * reusa entre requests y acumula los datos de todas, asi que un test asi
 * pasaria aunque las sesiones fueran la misma.
 */
class SesionPorAreaTest extends TestCase
{
    use RefreshDatabase;

    private function cookieStaff(): string
    {
        return config('session.cookie');
    }

    private function cookiePortal(): string
    {
        return config('session.cookie').SesionPorArea::SUFIJO_PORTAL;
    }

    public function test_el_portal_y_el_staff_entregan_cookies_de_sesion_distintas(): void
    {
        $this->get('/login')
            ->assertCookie($this->cookieStaff())
            ->assertCookieMissing($this->cookiePortal());

        $this->get('/portal/ingresar')
            ->assertCookie($this->cookiePortal())
            ->assertCookieMissing($this->cookieStaff());
    }

    public function test_cada_area_lee_su_propia_cookie_y_no_la_del_otro(): void
    {
        $staff = $this->get('/login')->getCookie($this->cookieStaff())->getValue();
        $portal = $this->get('/portal/ingresar')->getCookie($this->cookiePortal())->getValue();

        $this->assertNotSame($staff, $portal);

        // Con las dos cookies en el navegador, cada area sigue en su sesion.
        $this->withCookie($this->cookieStaff(), $staff)
            ->withCookie($this->cookiePortal(), $portal);

        $this->assertSame($portal, $this->get('/portal/ingresar')->getCookie($this->cookiePortal())->getValue());
        $this->assertSame($staff, $this->get('/login')->getCookie($this->cookieStaff())->getValue());
    }

    // --- Token vencido (419) ------------------------------------------------
    //
    // La verificación CSRF no corre en los tests, así que el 419 se provoca
    // con una ruta de prueba. Lo que se verifica es la respuesta.

    private function rutaQueVence(string $uri): void
    {
        Route::post($uri, fn () => abort(419))->middleware('web');
    }

    public function test_un_token_vencido_vuelve_a_la_pantalla_con_un_aviso(): void
    {
        $this->rutaQueVence('/_prueba/419');

        $this->from('/login')
            ->post('/_prueba/419', ['nombreUsuario' => 'gonzalez', 'password' => 'demo1234'])
            ->assertRedirect('/login')
            ->assertSessionHas('aviso')
            // Lo escrito vuelve, salvo la contraseña.
            ->assertSessionHasInput('nombreUsuario', 'gonzalez')
            ->assertSessionMissing('_old_input.password');

        $this->get('/login')->assertSee('venció por seguridad');
    }

    public function test_sin_pagina_anterior_vuelve_a_la_puerta_de_su_area(): void
    {
        $this->rutaQueVence('/portal/_prueba/419');
        $this->rutaQueVence('/_prueba/419');

        $this->post('/portal/_prueba/419')->assertRedirect(route('portal.ingresar'));
        $this->post('/_prueba/419')->assertRedirect(route('login'));
    }

    public function test_un_pedido_json_sigue_recibiendo_419(): void
    {
        $this->rutaQueVence('/_prueba/419');

        $this->postJson('/_prueba/419')->assertStatus(419);
    }

    public function test_el_area_se_decide_por_la_url(): void
    {
        $area = fn (string $url) => SesionPorArea::cookiePara(Request::create($url));

        $this->assertSame($this->cookiePortal(), $area('/portal'));
        $this->assertSame($this->cookiePortal(), $area('/portal/cirugias/7/estudios'));
        // El link de acceso por email abre la sesion del portal: tiene que ser su cookie.
        $this->assertSame($this->cookiePortal(), $area('/portal/acceso/1/abc'));

        // La vista previa que mira el staff es del staff, aunque diga "portal".
        $this->assertSame($this->cookieStaff(), $area('/cirugias/7/portal-paciente'));
        $this->assertSame($this->cookieStaff(), $area('/portalito'));
        $this->assertSame($this->cookieStaff(), $area('/dashboard'));
    }
}
