<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Portal\AccesoController;
use App\Mail\EnlaceAccesoPortal;
use App\Models\Auditoria;
use App\Models\Cirugia;
use App\Models\Persona;
use App\Models\Plan;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Support\Auditor;
use App\Support\FiltroBaja;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PersonasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    private function admin(): Usuario
    {
        return Usuario::where('nombreUsuario', 'admin')->firstOrFail();
    }

    private function rol(string $nombre): int
    {
        return Rol::where('nombreRol', $nombre)->value('idRol');
    }

    private function idDni(): int
    {
        return TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPersona(array $cambios = []): array
    {
        return array_merge([
            'tipo_documento_id' => $this->idDni(),
            'documento' => '38444555',
            'apellidos' => 'Ferreyra',
            'nombres' => 'Valentina',
            'contacto_email_direccion' => 'valentina.ferreyra@example.com',
            'telefono_numero' => '2614223344',
            'direccionPersona' => 'Las Heras 900',
        ], $cambios);
    }

    public function test_el_listado_filtra_por_texto_tipo_y_bajas(): void
    {
        $persona = Persona::create($this->datosPersona());

        $this->actingAs($this->admin())
            ->get(route('admin.personas.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('Ferreyra, Valentina');

        $this->actingAs($this->admin())
            ->get(route('admin.personas.index', ['q' => 'valentina.ferreyra@example.com']))
            ->assertOk()
            ->assertSee('Ferreyra, Valentina');

        $this->actingAs($this->admin())
            ->get(route('admin.personas.index', ['tipo' => 'paciente', 'q' => 'Ferreyra']))
            ->assertOk()
            ->assertSee('Ferreyra, Valentina')
            ->assertSee('Crear usuario');

        $this->actingAs($this->admin())
            ->get(route('admin.personas.index', ['tipo' => 'usuario', 'q' => 'Ferreyra']))
            ->assertOk()
            ->assertDontSee('Ferreyra, Valentina');

        // Baja y reactivación
        $this->actingAs($this->admin())
            ->delete(route('admin.personas.destroy', $persona))
            ->assertRedirect(route('admin.personas.index'));

        $this->assertNotNull($persona->fresh()->fechaHoraBajaPersona);
        $this->assertSame(Auditor::BAJA, Auditoria::orderByDesc('idAuditoria')->firstOrFail()->accionAuditoria);

        $this->actingAs($this->admin())
            ->get(route('admin.personas.index', ['estado' => FiltroBaja::BAJAS, 'q' => 'Ferreyra']))
            ->assertOk()
            ->assertSee('Ferreyra, Valentina');

        $this->actingAs($this->admin())
            ->post(route('admin.personas.restore', $persona))
            ->assertRedirect(route('admin.personas.index', ['estado' => FiltroBaja::BAJAS]));

        $this->assertNull($persona->fresh()->fechaHoraBajaPersona);
        $this->assertSame(Auditor::REACTIVACION, Auditoria::orderByDesc('idAuditoria')->firstOrFail()->accionAuditoria);
    }

    public function test_alta_y_edicion_de_persona_exigen_campos_obligatorios(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.personas.store'), [
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

        $this->actingAs($this->admin())
            ->post(route('admin.personas.store'), array_merge($this->datosPersona(), [
                'accion' => 'crear_usuario',
            ]))
            ->assertRedirect(route('admin.usuarios.create', [
                'persona' => Persona::where('documento', '38444555')->value('idPersona'),
            ]));
    }

    public function test_promover_persona_existente_a_usuario_no_duplica_persona_y_permite_ambos_accesos(): void
    {
        Mail::fake();

        $maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $cirugiasAntes = Cirugia::where('idPersonaPaciente', $maria->idPersona)->count();
        $this->assertGreaterThan(0, $cirugiasAntes);

        // El formulario precarga los datos de la persona existente.
        $this->actingAs($this->admin())
            ->get(route('admin.usuarios.create', ['persona' => $maria->idPersona]))
            ->assertOk()
            ->assertSee($maria->documento)
            ->assertSee('No se duplicará su ficha');

        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), [
                'persona_id' => $maria->idPersona,
                'tipo_documento_id' => $maria->tipo_documento_id,
                'documento' => $maria->documento,
                'apellidos' => $maria->apellidos,
                'nombres' => $maria->nombres,
                'contacto_email_direccion' => 'maria.personal@example.com',
                'telefono_numero' => '2614887766',
                'direccionPersona' => 'Av. España 1200',
                'legajoPersonal' => '0999',
                'mailInstitucional' => 'm.garcia@hospital.test',
                'nombreUsuario' => 'mgarcia',
                'password' => 'clave12345',
                'password_confirmation' => 'clave12345',
                'roles' => [$this->rol('Gestor de quirófano')],
            ])
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHas('exito');

        // No se duplicó Persona y conserva sus cirugías.
        $this->assertSame(1, Persona::where('documento', $maria->documento)->count());
        $this->assertSame($cirugiasAntes, Cirugia::where('idPersonaPaciente', $maria->idPersona)->count());

        $usuario = Usuario::where('nombreUsuario', 'mgarcia')->firstOrFail();
        $this->assertSame($maria->idPersona, $usuario->personal->idPersona);
        $this->assertEquals(['Gestor de quirófano'], $usuario->roles()->all());

        // Puede iniciar sesión como staff.
        $this->post('/logout');
        $this->post('/login', ['nombreUsuario' => 'mgarcia', 'password' => 'clave12345'])
            ->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($usuario);

        // Y también puede pedir enlace para entrar al portal de pacientes.
        $this->post(route('portal.acceso.solicitar'), ['documento' => $maria->documento])
            ->assertRedirect(route('portal.ingresar'));

        Mail::assertSent(EnlaceAccesoPortal::class, fn (EnlaceAccesoPortal $mail) => $mail->hasTo('maria.personal@example.com'));
    }

    public function test_usuario_creado_desde_cero_con_rol_paciente_puede_entrar_al_portal_con_su_dni(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.usuarios.store'), [
                'tipo_documento_id' => $this->idDni(),
                'documento' => '41222333',
                'apellidos' => 'Navarro',
                'nombres' => 'Tomás',
                'contacto_email_direccion' => 'tomas.navarro@example.com',
                'telefono_numero' => '2614998877',
                'direccionPersona' => 'San Juan 320',
                'mailInstitucional' => 't.navarro@hospital.test',
                'nombreUsuario' => 'tnavarro',
                'password' => 'clave12345',
                'password_confirmation' => 'clave12345',
                'roles' => [$this->rol('Paciente')],
            ])
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHasNoErrors();

        $this->post('/logout');

        $this->post(route('portal.acceso.solicitar'), ['documento' => '41222333'])
            ->assertRedirect(route('portal.ingresar'));

        Mail::assertSent(EnlaceAccesoPortal::class, fn (EnlaceAccesoPortal $mail) => $mail->hasTo('tomas.navarro@example.com'));
    }

    public function test_un_administrador_no_puede_dar_de_baja_su_propia_persona(): void
    {
        $admin = $this->admin();
        $persona = $admin->personal->persona;

        $this->actingAs($admin)
            ->delete(route('admin.personas.destroy', $persona))
            ->assertSessionHas('error');

        $this->assertNull($persona->fresh()->fechaHoraBajaPersona);
    }

    public function test_no_administradores_no_acceden_a_admin_personas(): void
    {
        $persona = Persona::create($this->datosPersona());

        foreach (['gonzalez', 'perez'] as $nombreUsuario) {
            $usuario = Usuario::where('nombreUsuario', $nombreUsuario)->firstOrFail();

            $this->actingAs($usuario)->get(route('admin.personas.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('admin.personas.create'))->assertForbidden();
            $this->actingAs($usuario)->post(route('admin.personas.store'), $this->datosPersona(['documento' => '45999000']))->assertForbidden();
            $this->actingAs($usuario)->get(route('admin.personas.edit', $persona))->assertForbidden();
            $this->actingAs($usuario)->put(route('admin.personas.update', $persona), $this->datosPersona())->assertForbidden();
            $this->actingAs($usuario)->delete(route('admin.personas.destroy', $persona))->assertForbidden();
        }
    }

    public function test_administrador_puede_gestionar_obras_sociales_desde_admin_personas(): void
    {
        $persona = Persona::create($this->datosPersona());
        $planId = (int) Plan::disponiblesParaPaciente()->keys()->first();

        $this->actingAs($this->admin())
            ->get(route('admin.personas.edit', $persona))
            ->assertOk()
            ->assertSee('Obras sociales')
            ->assertSee('Agregar obra social');

        $this->actingAs($this->admin())
            ->post(route('personas.coberturas.store', $persona), [
                'idPlan' => $planId,
                'nroBeneficiario' => '888999',
            ])
            ->assertRedirect()
            ->assertSessionHas('exito');

        $cobertura = $persona->planObraSociales()->where('idPlan', $planId)->sole();

        $this->actingAs($this->admin())
            ->patch(route('personas.coberturas.baja', [$persona, $cobertura->idPlanObraSocial]))
            ->assertRedirect();

        $this->assertNotNull($cobertura->fresh()->fechaFinPlanObraSocial);
    }

    public function test_el_listado_de_personas_usa_paginacion_en_castellano(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.personas.index'))
            ->assertOk()
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next');
    }
}
