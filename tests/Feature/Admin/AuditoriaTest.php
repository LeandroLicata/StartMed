<?php

namespace Tests\Feature\Admin;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\TipoEstudio;
use App\Models\Usuario;
use App\Support\Auditor;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaTest extends TestCase
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

    private function ultimo(): Auditoria
    {
        return Auditoria::orderByDesc('idAuditoria')->firstOrFail();
    }

    public function test_el_alta_de_un_catalogo_queda_registrada(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'tipo-estudio'), ['nombreTipoEstudio' => 'Espirometría']);

        $registro = $this->ultimo();

        $this->assertSame(Auditor::ALTA, $registro->accionAuditoria);
        $this->assertSame($this->admin()->idUsuario, $registro->idUsuario);
        $this->assertSame('TipoEstudio', $registro->tablaAuditoria);
        $this->assertSame('Tipo de estudio «Espirometría»', $registro->descripcionAuditoria);
        $this->assertNull($registro->cambiosAuditoria);
    }

    public function test_la_edicion_guarda_el_valor_anterior_y_el_nuevo(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Hemograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', ['tipo-estudio', $estudio->idTipoEstudio]), [
                'nombreTipoEstudio' => 'Hemograma completo',
            ]);

        $registro = $this->ultimo();

        $this->assertSame(Auditor::EDICION, $registro->accionAuditoria);
        $this->assertSame(
            ['nombreTipoEstudio' => ['antes' => 'Hemograma', 'despues' => 'Hemograma completo']],
            $registro->cambiosAuditoria,
        );
    }

    public function test_guardar_sin_cambiar_nada_no_ensucia_el_historial(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Hemograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', ['tipo-estudio', $estudio->idTipoEstudio]), [
                'nombreTipoEstudio' => 'Hemograma',
            ])
            ->assertSessionHas('exito');

        $this->assertSame(0, Auditoria::count());
    }

    public function test_la_baja_y_la_reactivacion_quedan_registradas(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Coagulograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.catalogos.destroy', ['tipo-estudio', $estudio->idTipoEstudio]));
        $this->assertSame(Auditor::BAJA, $this->ultimo()->accionAuditoria);

        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.restore', ['tipo-estudio', $estudio->idTipoEstudio]));
        $this->assertSame(Auditor::REACTIVACION, $this->ultimo()->accionAuditoria);
    }

    public function test_una_accion_rechazada_no_se_registra(): void
    {
        $rol = Rol::where('nombreRol', 'Administrador')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', ['rol', $rol->idRol]), ['nombreRol' => 'Admin'])
            ->assertSessionHas('error');

        $this->assertSame(0, Auditoria::count());
    }

    public function test_el_alta_de_usuario_queda_registrada(): void
    {
        $this->crearUsuario();

        $registro = $this->ultimo();

        $this->assertSame(Auditor::ALTA, $registro->accionAuditoria);
        $this->assertSame('Usuario', $registro->tablaAuditoria);
        $this->assertSame('Usuario «jsosa»', $registro->descripcionAuditoria);
    }

    public function test_la_edicion_de_usuario_junta_las_tres_tablas_y_los_roles(): void
    {
        $this->crearUsuario();
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.usuarios.update', $usuario), $this->datosUsuario([
                'apellidos' => 'Sosa Vera',            // Persona
                'legajoPersonal' => '0099',            // Personal
                'nombreUsuario' => 'jsosavera',        // Usuario
                'roles' => [$this->idRol('Anestesista')],
            ]));

        $cambios = $this->ultimo()->cambiosAuditoria;

        $this->assertSame(['antes' => 'Sosa', 'despues' => 'Sosa Vera'], $cambios['apellidos']);
        $this->assertSame(['antes' => '0042', 'despues' => '0099'], $cambios['legajoPersonal']);
        $this->assertSame(['antes' => 'jsosa', 'despues' => 'jsosavera'], $cambios['nombreUsuario']);
        $this->assertSame(['antes' => 'Cirujano', 'despues' => 'Anestesista'], $cambios['roles']);
    }

    /**
     * Un registro de auditoría no es lugar para credenciales, ni hasheadas.
     */
    public function test_la_contrasena_nunca_queda_registrada(): void
    {
        $this->crearUsuario();
        $usuario = Usuario::where('nombreUsuario', 'jsosa')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.usuarios.clave', $usuario), [
                'password' => 'nueva-clave-1',
                'password_confirmation' => 'nueva-clave-1',
            ]);

        $registro = $this->ultimo();

        $this->assertSame(Auditor::CLAVE, $registro->accionAuditoria);
        $this->assertNull($registro->cambiosAuditoria);

        // Ni el texto plano ni el hash aparecen en ninguna fila.
        $todo = Auditoria::get()->toJson();
        $this->assertStringNotContainsString('nueva-clave-1', $todo);
        $this->assertStringNotContainsString('$2y$', $todo);
    }

    public function test_la_pantalla_lista_los_movimientos_y_el_detalle(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Hemograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', ['tipo-estudio', $estudio->idTipoEstudio]), [
                'nombreTipoEstudio' => 'Hemograma completo',
            ]);

        $this->actingAs($this->admin())
            ->get(route('admin.auditoria'))
            ->assertOk()
            ->assertSee('Tipo de estudio «Hemograma completo»')
            ->assertSee('Hemograma')          // valor anterior
            ->assertSee('admin');             // autor
    }

    public function test_la_pantalla_filtra_por_autor_accion_y_tabla(): void
    {
        $this->crearUsuario();
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Coagulograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.catalogos.destroy', ['tipo-estudio', $estudio->idTipoEstudio]));

        // Hay un alta de usuario y una baja de catálogo: cada filtro deja una.
        $this->actingAs($this->admin())
            ->get(route('admin.auditoria', ['accion' => Auditor::BAJA]))
            ->assertOk()
            ->assertSee('Coagulograma')
            ->assertDontSee('jsosa');

        $this->actingAs($this->admin())
            ->get(route('admin.auditoria', ['tabla' => 'Usuario']))
            ->assertOk()
            ->assertSee('jsosa')
            ->assertDontSee('Coagulograma');

        $this->actingAs($this->admin())
            ->get(route('admin.auditoria', ['autor' => $this->admin()->idUsuario]))
            ->assertOk()
            ->assertSee('jsosa');
    }

    /**
     * Los desplegables hablan como la gente: la persona detrás del alias, y el
     * nombre del catálogo en vez del nombre de la tabla de la base.
     */
    public function test_los_desplegables_muestran_nombres_legibles(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Hemograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', ['tipo-estudio', $estudio->idTipoEstudio]), [
                'nombreTipoEstudio' => 'Hemograma completo',
            ]);

        $this->actingAs($this->admin())
            ->get(route('admin.auditoria'))
            ->assertOk()
            ->assertSee('Admin, Sistema (admin)')   // autor, no solo el alias
            ->assertSee('Tipos de estudio')          // catálogo, no «TipoEstudio»
            ->assertDontSee('>TipoEstudio<', false);
    }

    public function test_el_desplegable_de_tablas_nombra_tambien_a_los_usuarios(): void
    {
        $this->crearUsuario();

        $this->actingAs($this->admin())
            ->get(route('admin.auditoria'))
            ->assertOk()
            ->assertSee('Usuarios')
            ->assertDontSee('>Usuario<', false);
    }

    public function test_la_auditoria_esta_cerrada_a_los_demas_roles(): void
    {
        $this->seed(DemoSeeder::class);

        $this->actingAs(Usuario::where('nombreUsuario', 'perez')->firstOrFail())
            ->get(route('admin.auditoria'))
            ->assertForbidden();
    }

    private function idRol(string $nombre): int
    {
        return Rol::where('nombreRol', $nombre)->value('idRol');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosUsuario(array $cambios = []): array
    {
        return array_merge([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => '28123456',
            'apellidos' => 'Sosa',
            'nombres' => 'Julieta',
            'legajoPersonal' => '0042',
            'nombreUsuario' => 'jsosa',
            'password' => 'clave12345',
            'password_confirmation' => 'clave12345',
            'roles' => [$this->idRol('Cirujano')],
        ], $cambios);
    }

    private function crearUsuario(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), $this->datosUsuario())
            ->assertSessionHasNoErrors();
    }
}
