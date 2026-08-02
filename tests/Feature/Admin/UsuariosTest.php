<?php

namespace Tests\Feature\Admin;

use App\Models\Persona;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\RolPersonal;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Support\FiltroBaja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function admin(): Usuario
    {
        return Usuario::where('nombreUsuario', 'admin')->firstOrFail();
    }

    private function rol(string $nombre): int
    {
        return Rol::where('nombreRol', $nombre)->value('idRol');
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => '28123456',
            'apellidos' => 'Sosa',
            'nombres' => 'Julieta',
            'legajoPersonal' => '0042',
            'mailInstitucional' => 'jsosa@hospital.test',
            'nombreUsuario' => 'jsosa',
            'password' => 'clave12345',
            'password_confirmation' => 'clave12345',
            'roles' => [$this->rol('Cirujano')],
        ], $cambios);
    }

    public function test_el_formulario_de_alta_lista_los_roles_y_los_tipos_de_documento(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.usuarios.create'))
            ->assertOk()
            ->assertSee('Nuevo usuario')
            ->assertSee('Cirujano')
            ->assertSee('Anestesista')
            ->assertSee('DNI');
    }

    public function test_el_formulario_de_edicion_carga_al_usuario_y_sus_roles(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.usuarios.edit', $usuario))
            ->assertOk()
            ->assertSee('jsosa')
            ->assertSee('Sosa')
            ->assertSee('Cambiar contraseña');
    }

    public function test_el_alta_crea_toda_la_cadena_y_el_usuario_puede_entrar(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), $this->datos())
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHas('exito');

        $this->assertDatabaseHas('Persona', ['documento' => '28123456', 'apellidos' => 'Sosa']);

        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->assertSame('0042', $usuario->personal->legajoPersonal);
        $this->assertSame('Sosa', $usuario->personal->persona->apellidos);
        $this->assertEquals(['Cirujano'], $usuario->roles()->all());

        // La contraseña se guarda hasheada por el cast de Usuario.
        $this->assertNotSame('clave12345', $usuario->passwordUsuario);
        $this->assertTrue(password_verify('clave12345', $usuario->passwordUsuario));

        // Y sirve para iniciar sesión de verdad. Hay que soltar la sesión del
        // admin primero: el middleware `guest` rebota a quien ya está logueado.
        $this->post('/logout');

        $this->post('/login', ['nombreUsuario' => 'jsosa', 'password' => 'clave12345'])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_usuario_nuevo_aterriza_en_el_panel_de_su_rol(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());

        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->assertSame('cirujano', $usuario->rutaInicial());
        $this->actingAs($usuario)->get('/')->assertRedirect(route('cirujano'));
        $this->actingAs($usuario)->get(route('admin.inicio'))->assertForbidden();
    }

    public function test_el_administrador_aterriza_en_su_panel(): void
    {
        $this->assertSame('admin.inicio', $this->admin()->rutaInicial());

        $this->actingAs($this->admin())->get('/')->assertRedirect(route('admin.inicio'));
    }

    public function test_el_nombre_de_usuario_no_se_repite(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), $this->datos(['nombreUsuario' => 'admin']))
            ->assertSessionHasErrors('nombreUsuario');
    }

    public function test_el_documento_no_se_repite_dentro_del_mismo_tipo(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());

        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), $this->datos(['nombreUsuario' => 'otro']))
            ->assertSessionHasErrors('documento');
    }

    public function test_la_contrasena_se_confirma_y_tiene_minimo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), $this->datos(['password_confirmation' => 'otra-cosa']))
            ->assertSessionHasErrors('password');

        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), $this->datos([
                'password' => 'corta',
                'password_confirmation' => 'corta',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_editar_actualiza_persona_legajo_y_usuario(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.usuarios.update', $usuario), $this->datos([
                'apellidos' => 'Sosa Vera',
                'legajoPersonal' => '0099',
                'nombreUsuario' => 'jsosavera',
                'password' => null,
                'password_confirmation' => null,
            ]))
            ->assertSessionHasNoErrors();

        $usuario->refresh();

        $this->assertSame('jsosavera', $usuario->nombreUsuario);
        $this->assertSame('0099', $usuario->personal->legajoPersonal);
        $this->assertSame('Sosa Vera', $usuario->personal->persona->apellidos);
    }

    public function test_quitar_un_rol_cierra_la_asignacion_en_vez_de_borrarla(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())->put(
            route('admin.usuarios.update', $usuario),
            $this->datos(['roles' => [$this->rol('Anestesista')], 'password' => null, 'password_confirmation' => null]),
        );

        $usuario->refresh();

        $this->assertEquals(['Anestesista'], $usuario->roles()->all());

        // La asignación vieja sigue en la tabla, cerrada con su fecha de baja.
        $cerrada = RolPersonal::where('idPersonal', $usuario->idPersonal)
            ->where('idRol', $this->rol('Cirujano'))
            ->firstOrFail();

        $this->assertNotNull($cerrada->fechaHoraBajaAsignacionRolPersonal);
    }

    public function test_volver_a_dar_un_rol_abre_una_asignacion_nueva(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $sinRoles = $this->datos(['roles' => [], 'password' => null, 'password_confirmation' => null]);
        $conCirujano = $this->datos(['password' => null, 'password_confirmation' => null]);

        $this->actingAs($this->admin())->put(route('admin.usuarios.update', $usuario), $sinRoles);
        $this->actingAs($this->admin())->put(route('admin.usuarios.update', $usuario), $conCirujano);

        $asignaciones = RolPersonal::where('idPersonal', $usuario->idPersonal)
            ->where('idRol', $this->rol('Cirujano'))
            ->get();

        $this->assertCount(2, $asignaciones);
        $this->assertCount(1, $asignaciones->whereNull('fechaHoraBajaAsignacionRolPersonal'));
    }

    public function test_un_usuario_sin_roles_queda_sin_panel(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), $this->datos(['roles' => []]));

        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->assertNull($usuario->rutaInicial());
        $this->actingAs($usuario)->get('/')->assertOk()->assertSee('Todavía no tenés un rol asignado');
    }

    public function test_el_administrador_puede_cambiar_la_contrasena(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.usuarios.clave', $usuario), [
                'password' => 'nueva-clave-1',
                'password_confirmation' => 'nueva-clave-1',
            ])
            ->assertSessionHas('exito');

        $this->assertTrue(password_verify('nueva-clave-1', $usuario->fresh()->passwordUsuario));
    }

    public function test_dar_de_baja_impide_iniciar_sesion_y_se_puede_reactivar(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())->delete(route('admin.usuarios.destroy', $usuario));

        $this->assertNotNull($usuario->fresh()->fechaBajaUsuario);

        $this->post('/logout');

        $this->post('/login', ['nombreUsuario' => 'jsosa', 'password' => 'clave12345'])
            ->assertSessionHasErrors('nombreUsuario');
        $this->assertGuest();

        $this->actingAs($this->admin())->post(route('admin.usuarios.restore', $usuario));

        $this->assertNull($usuario->fresh()->fechaBajaUsuario);
    }

    public function test_el_filtro_de_estado_separa_activos_bajas_y_todos(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('jsosa');

        $usuario->update(['fechaBajaUsuario' => now()]);

        // Por defecto, solo los activos: queda el admin.
        $activos = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertDontSee('jsosa');
        $this->assertSame(1, $this->filas($activos));

        // Solo las bajas: queda jsosa. Esto antes no se podía pedir.
        $bajas = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['estado' => FiltroBaja::BAJAS]))
            ->assertOk()
            ->assertSee('jsosa');
        $this->assertSame(1, $this->filas($bajas));

        $todos = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['estado' => FiltroBaja::TODOS]))
            ->assertOk()
            ->assertSee('jsosa');
        $this->assertSame(2, $this->filas($todos));
    }

    /**
     * Se cuentan las filas en vez de usar assertDontSee sobre el otro usuario:
     * el encabezado del layout muestra siempre a quien está logueado, así que
     * su nombre aparece en la página aunque no esté en la tabla.
     */
    private function filas(TestResponse $respuesta): int
    {
        return substr_count($respuesta->getContent(), 'class="align-middle');
    }

    public function test_el_buscador_encuentra_por_apellido_usuario_y_legajo(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());

        // Los tres campos por los que alguien se acordaría de buscar.
        foreach (['Sosa', 'jsosa', '0042'] as $termino) {
            $respuesta = $this->actingAs($this->admin())
                ->get(route('admin.usuarios.index', ['q' => $termino]))
                ->assertOk()
                ->assertSee('jsosa');

            $this->assertSame(1, $this->filas($respuesta), "buscar «{$termino}» debería traer solo a jsosa");
        }
    }

    public function test_el_buscador_avisa_cuando_no_encuentra_nada(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['q' => 'nadie-con-este-nombre']))
            ->assertOk()
            ->assertSee('Ningún usuario coincide con la búsqueda.');
    }

    public function test_se_filtra_por_rol_vigente(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());

        $cirujanos = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['rol' => $this->rol('Cirujano')]))
            ->assertOk()
            ->assertSee('jsosa');

        $this->assertSame(1, $this->filas($cirujanos));

        $administradores = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['rol' => $this->rol('Administrador')]))
            ->assertOk()
            ->assertDontSee('Sosa, Julieta');

        $this->assertSame(1, $this->filas($administradores));
    }

    /**
     * Un rol que se cerró ya no cuenta: el filtro mira las asignaciones
     * vigentes, no todo el historial de RolPersonal.
     */
    public function test_el_filtro_por_rol_ignora_las_asignaciones_cerradas(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $usuario->personal->sincronizarRoles([$this->rol('Anestesista')]);

        // La fila de RolPersonal con Cirujano sigue existiendo, pero cerrada.
        $cirujanos = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['rol' => $this->rol('Cirujano')]))
            ->assertOk();

        $this->assertSame(0, $this->filas($cirujanos));

        $anestesistas = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['rol' => $this->rol('Anestesista')]))
            ->assertOk()
            ->assertSee('jsosa');

        $this->assertSame(1, $this->filas($anestesistas));
    }

    /**
     * Los tres filtros son campos del mismo formulario, así que se combinan
     * sin pisarse y la búsqueda no se pierde al cambiar de estado.
     */
    public function test_los_tres_filtros_se_combinan(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), $this->datos());
        Usuario::where('nombreUsuario', 'jsosa')->update(['fechaBajaUsuario' => now()]);

        // Buscando «Sosa» entre los activos no aparece…
        $activos = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['q' => 'Sosa']))
            ->assertOk();
        $this->assertSame(0, $this->filas($activos));

        // …y con la misma búsqueda sobre las bajas, sí.
        $bajas = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', ['q' => 'Sosa', 'estado' => FiltroBaja::BAJAS]))
            ->assertOk()
            ->assertSee('jsosa');
        $this->assertSame(1, $this->filas($bajas));

        // Y el formulario devuelve los tres valores elegidos.
        $bajas->assertSee('value="Sosa"', false)
            ->assertSee('<option value="bajas" selected', false);

        // Búsqueda + rol + estado a la vez.
        $todo = $this->actingAs($this->admin())
            ->get(route('admin.usuarios.index', [
                'q' => 'Sosa',
                'rol' => $this->rol('Cirujano'),
                'estado' => FiltroBaja::BAJAS,
            ]))
            ->assertOk()
            ->assertSee('jsosa');
        $this->assertSame(1, $this->filas($todo));
    }

    public function test_un_administrador_no_puede_quitarse_su_propio_rol(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.usuarios.update', $admin), [
                'tipo_documento_id' => $admin->personal->persona->tipo_documento_id,
                'documento' => $admin->personal->persona->documento,
                'apellidos' => 'Admin',
                'nombres' => 'Sistema',
                'nombreUsuario' => 'admin',
                'roles' => [$this->rol('Cirujano')],
            ])
            ->assertSessionHas('error');

        $this->assertEquals(['Administrador'], $admin->fresh()->roles()->all());
    }

    public function test_un_administrador_no_puede_darse_de_baja_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertNull($admin->fresh()->fechaBajaUsuario);
    }

    public function test_la_seccion_esta_cerrada_a_los_demas_roles(): void
    {
        // Un usuario con legajo pero sin el rol Administrador.
        $persona = Persona::create([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => '31999888',
            'apellidos' => 'Vega',
            'nombres' => 'Nicolás',
        ]);
        $personal = Personal::create(['idPersona' => $persona->idPersona]);
        $personal->sincronizarRoles([$this->rol('Enfermero')]);

        $intruso = Usuario::create([
            'idPersonal' => $personal->idPersonal,
            'nombreUsuario' => 'nvega',
            'passwordUsuario' => 'clave12345',
        ]);

        $this->actingAs($intruso)->get(route('admin.usuarios.index'))->assertForbidden();
        $this->actingAs($intruso)
            ->post(route('admin.usuarios.store'), $this->datos())
            ->assertForbidden();

        $this->assertDatabaseMissing('Usuario', ['nombreUsuario' => 'jsosa']);
    }
}
