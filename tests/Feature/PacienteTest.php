<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Support\Auditor;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    private function gestor(): Usuario
    {
        return Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
    }

    private function cirujano(): Usuario
    {
        return Usuario::where('nombreUsuario', 'perez')->firstOrFail();
    }

    private function anestesista(): Usuario
    {
        return Usuario::where('nombreUsuario', 'ramos')->firstOrFail();
    }

    private function idDni(): int
    {
        return TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPaciente(array $cambios = []): array
    {
        return array_merge([
            'tipo_documento_id' => $this->idDni(),
            'documento' => '37003281',
            'apellidos' => 'Mendoza',
            'nombres' => 'Lucía',
            'contacto_email_direccion' => 'lucia.mendoza@example.com',
            'telefono_numero' => '2614559988',
            'direccionPersona' => 'Belgrano 450, Mendoza',
            'genero' => 'F',
            'es_cronico' => false,
        ], $cambios);
    }

    public function test_busqueda_por_dni_con_filtros_vacios_encuentra_al_paciente(): void
    {
        Persona::create($this->datosPaciente());

        // Simula exactamente el submit del formulario con Género=Todos y Crónico=Todos.
        $this->actingAs($this->gestor())
            ->get('/pacientes?buscar=37003281&genero=&es_cronico=')
            ->assertOk()
            ->assertSee('Mendoza, Lucía')
            ->assertSee('37003281')
            ->assertDontSee('No se encontraron pacientes que coincidan con la búsqueda.');
    }

    public function test_busqueda_soporta_dni_con_puntos_y_nombre_con_apellido(): void
    {
        Persona::create($this->datosPaciente());

        $this->actingAs($this->gestor())
            ->get(route('pacientes.index', ['buscar' => '37.003.281']))
            ->assertOk()
            ->assertSee('Mendoza, Lucía');

        $this->actingAs($this->gestor())
            ->get(route('pacientes.index', ['buscar' => 'Lucía Mendoza']))
            ->assertOk()
            ->assertSee('Mendoza, Lucía');

        $this->actingAs($this->gestor())
            ->get(route('pacientes.index', ['buscar' => 'Mendoza Lucía']))
            ->assertOk()
            ->assertSee('Mendoza, Lucía');
    }

    public function test_filtros_de_genero_y_cronico_funcionan_solos_y_combinados(): void
    {
        Persona::create($this->datosPaciente([
            'documento' => '39111222',
            'apellidos' => 'Quiroga',
            'nombres' => 'Mateo',
            'contacto_email_direccion' => 'mateo@example.com',
            'genero' => 'M',
            'es_cronico' => true,
        ]));

        Persona::create($this->datosPaciente([
            'documento' => '39333444',
            'apellidos' => 'Paz',
            'nombres' => 'Sofía',
            'contacto_email_direccion' => 'sofia@example.com',
            'genero' => 'F',
            'es_cronico' => false,
        ]));

        $this->actingAs($this->gestor())
            ->get(route('pacientes.index', ['es_cronico' => '1']))
            ->assertOk()
            ->assertSee('Quiroga, Mateo')
            ->assertDontSee('Paz, Sofía');

        $this->actingAs($this->gestor())
            ->get(route('pacientes.index', ['es_cronico' => '0']))
            ->assertOk()
            ->assertSee('Paz, Sofía')
            ->assertDontSee('Quiroga, Mateo');

        $this->actingAs($this->gestor())
            ->get(route('pacientes.index', ['buscar' => '39', 'genero' => 'M', 'es_cronico' => '1']))
            ->assertOk()
            ->assertSee('Quiroga, Mateo')
            ->assertDontSee('Paz, Sofía');
    }

    public function test_gestor_puede_registrar_y_editar_datos_de_paciente_con_auditoria(): void
    {
        $this->actingAs($this->gestor())
            ->get(route('pacientes.create'))
            ->assertOk()
            ->assertSee('Nuevo paciente');

        $respuestaAlta = $this->actingAs($this->gestor())
            ->post(route('pacientes.store'), $this->datosPaciente());

        $paciente = Persona::where('documento', '37003281')->firstOrFail();

        $respuestaAlta->assertRedirect(route('pacientes.show', $paciente))
            ->assertSessionHas('exito');

        $this->assertSame('lucia.mendoza@example.com', $paciente->contacto_email_direccion);
        $this->assertSame('2614559988', $paciente->telefono_numero);
        $this->assertSame('Belgrano 450, Mendoza', $paciente->direccionPersona);

        $altaAudit = Auditoria::orderByDesc('idAuditoria')->firstOrFail();
        $this->assertSame(Auditor::ALTA, $altaAudit->accionAuditoria);
        $this->assertSame('Persona', $altaAudit->tablaAuditoria);

        $this->actingAs($this->gestor())
            ->get(route('pacientes.edit', $paciente))
            ->assertOk()
            ->assertSee('Editar datos de Mendoza, Lucía');

        $this->actingAs($this->gestor())
            ->put(route('pacientes.update', $paciente), $this->datosPaciente([
                'contacto_email_direccion' => 'lucia.nueva@example.com',
                'telefono_numero' => '2615001122',
                'direccionPersona' => 'Aristides Villanueva 800',
            ]))
            ->assertRedirect(route('pacientes.show', $paciente))
            ->assertSessionHas('exito');

        $paciente->refresh();
        $this->assertSame('lucia.nueva@example.com', $paciente->contacto_email_direccion);
        $this->assertSame('2615001122', $paciente->telefono_numero);
        $this->assertSame('Aristides Villanueva 800', $paciente->direccionPersona);

        $edicionAudit = Auditoria::orderByDesc('idAuditoria')->firstOrFail();
        $this->assertSame(Auditor::EDICION, $edicionAudit->accionAuditoria);
        $this->assertSame('lucia.nueva@example.com', $edicionAudit->cambiosAuditoria['contacto_email_direccion']['despues']);
        $this->assertSame('Aristides Villanueva 800', $edicionAudit->cambiosAuditoria['direccionPersona']['despues']);
    }

    public function test_alta_de_paciente_exige_campos_obligatorios(): void
    {
        $this->actingAs($this->gestor())
            ->post(route('pacientes.store'), [
                'tipo_documento_id' => $this->idDni(),
                'documento' => '',
                'apellidos' => '',
                'nombres' => '',
                'contacto_email_direccion' => '',
                'telefono_numero' => '',
                'direccionPersona' => '',
            ])
            ->assertSessionHasErrors([
                'documento',
                'apellidos',
                'nombres',
                'contacto_email_direccion',
                'telefono_numero',
                'direccionPersona',
            ]);
    }

    public function test_cirujano_y_anestesista_solo_tienen_lectura_en_pacientes(): void
    {
        $paciente = Persona::create($this->datosPaciente());

        foreach ([$this->cirujano(), $this->anestesista()] as $profesional) {
            $this->actingAs($profesional)
                ->get(route('pacientes.index'))
                ->assertOk()
                ->assertDontSee('Nuevo paciente');

            $this->actingAs($profesional)
                ->get(route('pacientes.show', $paciente))
                ->assertOk()
                ->assertDontSee('Editar datos');

            $this->actingAs($profesional)->get(route('pacientes.create'))->assertForbidden();
            $this->actingAs($profesional)->post(route('pacientes.store'), $this->datosPaciente(['documento' => '40111222']))->assertForbidden();
            $this->actingAs($profesional)->get(route('pacientes.edit', $paciente))->assertForbidden();
            $this->actingAs($profesional)->put(route('pacientes.update', $paciente), $this->datosPaciente())->assertForbidden();
        }
    }
}
