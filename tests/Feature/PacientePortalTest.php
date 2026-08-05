<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Especificación del portal del paciente. **Todavía no se puede ejecutar**, y
 * es deliberado: describe una funcionalidad a medio construir.
 *
 * Lo que existe es PacientePortalController, la vista `paciente.portal` con sus
 * secciones y PortalPacienteMock. Lo que falta son las rutas —el nombre del
 * controlador nunca apareció en routes/web.php, en ningún commit— y la rama
 * 'Paciente' de Usuario::rutaInicial().
 *
 * No alcanza con cablearlas, por eso queda salteado en vez de arreglado:
 *
 * 1. El portal es una maqueta. PortalPacienteMock guarda el estado en la sesión
 *    y devuelve datos inventados, así que enrutarlo no entrega la funcionalidad.
 * 2. Un paciente no puede autenticarse. `Usuario` cuelga de `Personal` y un
 *    paciente es una `Persona` sin legajo; fijate que el helper de acá abajo se
 *    lo inventa para poder crearle un usuario. Eso esquiva una decisión de
 *    esquema, no la resuelve.
 *
 * Cuando se decida cómo entra un paciente, se sacan los markTestSkipped y esto
 * pasa a ser la lista de lo que hay que hacer funcionar.
 */
class PacientePortalTest extends TestCase
{
    use RefreshDatabase;

    private const PENDIENTE = 'El portal del paciente no tiene rutas todavía: falta decidir cómo se autentica un paciente.';

    private function usuario(string $rol): Usuario
    {
        $tipo = TipoDocumento::create(['nombreTipoDocumento' => 'DNI']);
        $persona = Persona::create(['tipo_documento_id' => $tipo->idTipoDocumento, 'documento' => '28456789', 'apellidos' => 'García', 'nombres' => 'María']);
        $personal = Personal::create(['idPersona' => $persona->idPersona]);
        $personal->roles()->attach(Rol::create(['nombreRol' => $rol]), ['fechaHoraAsignacionRolPersonal' => now()]);

        return Usuario::create(['idPersonal' => $personal->idPersonal, 'nombreUsuario' => 'mgarcia', 'passwordUsuario' => 'paciente1234']);
    }

    public function test_el_paciente_aterriza_en_su_portal(): void
    {
        $this->markTestSkipped(self::PENDIENTE);

        $paciente = $this->usuario('Paciente');

        $this->assertSame('paciente.portal', $paciente->rutaInicial());
        $this->actingAs($paciente)->get('/')->assertRedirect(route('paciente.portal'));
        $this->actingAs($paciente)->get('/mi-salud')->assertOk()->assertSee('García, María')->assertSee('Colecistectomía laparoscópica');
    }

    public function test_otro_rol_no_puede_entrar_al_portal(): void
    {
        $this->markTestSkipped(self::PENDIENTE);

        $this->actingAs($this->usuario('Cirujano'))->get('/mi-salud')->assertForbidden();
    }

    public function test_las_secciones_y_acciones_mock_son_navegables(): void
    {
        $this->markTestSkipped(self::PENDIENTE);

        $paciente = $this->usuario('Paciente');

        foreach (['resumen', 'turnos', 'estudios', 'preanestesica', 'preparacion', 'consentimiento', 'contacto'] as $seccion) {
            $this->actingAs($paciente)->get('/mi-salud/'.$seccion)->assertOk();
        }

        $this->actingAs($paciente)->post('/mi-salud/accion/estudio', [
            'archivo' => UploadedFile::fake()->create('hemograma.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('paciente.portal', ['seccion' => 'estudios']))
            ->assertSessionHas('portal_paciente.estudio', true);
    }
}
