<?php

namespace Tests\Feature\Auth;

use App\Models\Persona;
use App\Models\Personal;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(array $atributos = []): Usuario
    {
        $tipoDocumento = TipoDocumento::create(['nombreTipoDocumento' => 'DNI']);

        $persona = Persona::create([
            'tipo_documento_id' => $tipoDocumento->idTipoDocumento,
            'documento' => '30000000',
            'apellidos' => 'Perez',
            'nombres' => 'Ana',
        ]);

        $personal = Personal::create(['idPersona' => $persona->idPersona]);

        return Usuario::create(array_merge([
            'idPersonal' => $personal->idPersonal,
            'nombreUsuario' => 'aperez',
            'passwordUsuario' => 'secreto123',
        ], $atributos));
    }

    public function test_el_formulario_de_login_se_muestra(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Ingresar');
    }

    public function test_un_usuario_puede_iniciar_sesion(): void
    {
        $usuario = $this->crearUsuario();

        $this->post('/login', [
            'nombreUsuario' => 'aperez',
            'password' => 'secreto123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_la_contrasena_se_guarda_hasheada(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertNotSame('secreto123', $usuario->passwordUsuario);
        $this->assertTrue(password_verify('secreto123', $usuario->passwordUsuario));
    }

    public function test_no_se_puede_iniciar_sesion_con_contrasena_incorrecta(): void
    {
        $this->crearUsuario();

        $this->post('/login', [
            'nombreUsuario' => 'aperez',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('nombreUsuario');

        $this->assertGuest();
    }

    public function test_un_usuario_dado_de_baja_no_puede_iniciar_sesion(): void
    {
        $this->crearUsuario(['fechaBajaUsuario' => now()]);

        $this->post('/login', [
            'nombreUsuario' => 'aperez',
            'password' => 'secreto123',
        ])->assertSessionHasErrors('nombreUsuario');

        $this->assertGuest();
    }

    public function test_el_dashboard_requiere_autenticacion(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_un_usuario_autenticado_ve_el_dashboard(): void
    {
        $this->actingAs($this->crearUsuario())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('aperez');
    }

    public function test_un_usuario_puede_cerrar_sesion(): void
    {
        $this->actingAs($this->crearUsuario())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
