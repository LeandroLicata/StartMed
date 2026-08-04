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

class PacientePortalTest extends TestCase
{
    use RefreshDatabase;

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
        $paciente = $this->usuario('Paciente');

        $this->assertSame('paciente.portal', $paciente->rutaInicial());
        $this->actingAs($paciente)->get('/')->assertRedirect(route('paciente.portal'));
        $this->actingAs($paciente)->get('/mi-salud')->assertOk()->assertSee('García, María')->assertSee('Colecistectomía laparoscópica');
    }

    public function test_otro_rol_no_puede_entrar_al_portal(): void
    {
        $this->actingAs($this->usuario('Cirujano'))->get('/mi-salud')->assertForbidden();
    }

    public function test_las_secciones_y_acciones_mock_son_navegables(): void
    {
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
