<?php

namespace Tests\Feature;

use App\Models\AutCirugia;
use App\Models\Cirugia;
use App\Models\ObraSocial;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\PlanObraSocial;
use App\Models\Quirofano;
use App\Models\TipoCirugia;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrearCirugiaTest extends TestCase
{
    use RefreshDatabase;

    private function conDatosDemo(): Usuario
    {
        $this->seed(CatalogosSeeder::class);
        $this->seed(DemoSeeder::class);

        return Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
    }

    private function pacienteNuevo(string $documento = '40111222'): Persona
    {
        return Persona::create([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => $documento,
            'apellidos' => 'Molina',
            'nombres' => 'Carla',
        ]);
    }

    /** @return array<string, mixed> */
    private function datosMinimos(Persona $paciente, array $extra = []): array
    {
        return array_merge([
            'idPersona' => $paciente->idPersona,
            'idTipoCirugia' => TipoCirugia::where('nombreTipoCirugia', 'Apendicectomía')->value('idTipoCirugia'),
            'idQuirofano' => Quirofano::where('nroQuirofano', 4)->value('idQuirofano'),
            'fechaHoraCirugia' => now()->addDays(10)->setTime(9, 0)->format('Y-m-d\TH:i'),
            'cobertura' => 'particular',
        ], $extra);
    }

    public function test_un_invitado_no_puede_acceder_al_alta_de_cirugia(): void
    {
        $this->get('/cirugias/nueva')->assertRedirect(route('login'));
    }

    public function test_un_rol_distinto_de_gestor_no_puede_acceder(): void
    {
        $usuario = $this->conDatosDemo();
        $cirujano = Usuario::where('nombreUsuario', 'perez')->firstOrFail();

        $this->actingAs($cirujano)->get('/cirugias/nueva')->assertForbidden();
    }

    public function test_buscar_por_dni_encuentra_al_paciente_existente(): void
    {
        $gestor = $this->conDatosDemo();

        $this->actingAs($gestor)
            ->get('/cirugias/nueva?documento=28456789')
            ->assertOk()
            ->assertSee('García, María');
    }

    public function test_buscar_por_dni_sin_resultado_ofrece_dar_de_alta(): void
    {
        $gestor = $this->conDatosDemo();

        $this->actingAs($gestor)
            ->get('/cirugias/nueva?documento=40111222')
            ->assertOk()
            ->assertSee('No se encontró')
            ->assertSee('Dar de alta al paciente');
    }

    public function test_dar_de_alta_a_un_paciente_nuevo_lo_crea_y_avanza_al_formulario(): void
    {
        $gestor = $this->conDatosDemo();

        $respuesta = $this->actingAs($gestor)->post('/cirugias/nueva/paciente', [
            'documento' => '40111222',
            'apellidos' => 'Molina',
            'nombres' => 'Carla',
        ]);

        $persona = Persona::where('documento', '40111222')->firstOrFail();
        $respuesta->assertRedirect(route('cirugias.crear.formulario', $persona));
    }

    public function test_no_se_puede_operar_sobre_una_persona_dada_de_baja(): void
    {
        $gestor = $this->conDatosDemo();
        $persona = $this->pacienteNuevo();
        $persona->update(['fechaHoraBajaPersona' => now()]);

        $this->actingAs($gestor)
            ->get(route('cirugias.crear.formulario', $persona))
            ->assertForbidden();
    }

    public function test_crear_cirugia_particular_no_requiere_autorizacion(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();

        $this->actingAs($gestor)
            ->post('/cirugias', $this->datosMinimos($paciente))
            ->assertRedirect();

        $cirugia = Cirugia::where('idPersonaPaciente', $paciente->idPersona)->firstOrFail();
        $autCirugia = AutCirugia::where('idCirugia', $cirugia->idCirugia)->firstOrFail();

        $this->assertTrue($autCirugia->plan->es_sin_cobertura);
        $this->assertSame(0, $autCirugia->autoCirugiaEstados()->count());
        $this->assertSame(
            'En espera de confirmación',
            $cirugia->cirugiaEstados()->whereNull('fechaDesasignacionCirugiaEstado')->first()->estadoCirugia->nombreEstadoCirugia,
        );
    }

    public function test_crear_cirugia_con_obra_social_nueva_registra_la_cobertura_del_paciente(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();
        $plan = ObraSocial::where('nombreObraSocial', 'OSDE')->firstOrFail()->planes()->firstOrFail();

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($paciente, [
            'cobertura' => 'nueva',
            'idPlan' => $plan->idPlan,
            'nroBeneficiario' => '123456',
        ]))->assertRedirect();

        $cirugia = Cirugia::where('idPersonaPaciente', $paciente->idPersona)->firstOrFail();
        $autCirugia = AutCirugia::where('idCirugia', $cirugia->idCirugia)->firstOrFail();

        $this->assertSame($plan->idPlan, $autCirugia->idPlan);
        $this->assertSame('Pendiente de envío', $autCirugia->autoCirugiaEstados()->firstOrFail()->estadoAutCirugia->nombreEstadoAutCirugia);
        $this->assertDatabaseHas('PlanObraSocial', [
            'idPersona' => $paciente->idPersona,
            'idPlan' => $plan->idPlan,
            'nroBeneficiaroPlanObraSocial' => '123456',
        ]);
    }

    public function test_crear_cirugia_con_cobertura_existente_reutiliza_el_plan(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();
        $plan = ObraSocial::where('nombreObraSocial', 'Swiss Medical')->firstOrFail()->planes()->firstOrFail();

        $cobertura = PlanObraSocial::create([
            'idPersona' => $paciente->idPersona,
            'idPlan' => $plan->idPlan,
            'fechaInicioPlanObraSocial' => now()->subYear(),
        ]);

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($paciente, [
            'cobertura' => 'existente',
            'idPlanObraSocial' => $cobertura->idPlanObraSocial,
        ]))->assertRedirect();

        // No se crea una segunda cobertura para el mismo paciente/plan.
        $this->assertSame(1, PlanObraSocial::where('idPersona', $paciente->idPersona)->count());
    }

    public function test_el_estado_inicial_depende_del_equipo_asignado(): void
    {
        $gestor = $this->conDatosDemo();
        $cirujano = Personal::whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', 'Cirujano'))->first();
        $anestesista = Personal::whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', 'Anestesista'))->first();

        $sinEquipo = $this->pacienteNuevo('40111223');
        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($sinEquipo))->assertRedirect();

        $conEquipoCompleto = $this->pacienteNuevo('40111224');
        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($conEquipoCompleto, [
            'idPersonalCirujano' => $cirujano->idPersonal,
            'idPersonalAnestesista' => $anestesista->idPersonal,
            'fechaHoraCirugia' => now()->addDays(11)->setTime(9, 0)->format('Y-m-d\TH:i'),
        ]))->assertRedirect();

        $estado = fn (Persona $p) => Cirugia::where('idPersonaPaciente', $p->idPersona)->firstOrFail()
            ->cirugiaEstados()->whereNull('fechaDesasignacionCirugiaEstado')->first()->estadoCirugia->nombreEstadoCirugia;

        $this->assertSame('En espera de confirmación', $estado($sinEquipo));
        $this->assertSame('En espera', $estado($conEquipoCompleto));
    }

    public function test_no_se_puede_asignar_el_mismo_quirofano_dos_veces_en_el_mismo_horario(): void
    {
        $gestor = $this->conDatosDemo();
        $primero = $this->pacienteNuevo('40111225');
        $segundo = $this->pacienteNuevo('40111226');

        $datosComunes = $this->datosMinimos($primero, [
            'fechaHoraCirugia' => now()->addDays(12)->setTime(10, 0)->format('Y-m-d\TH:i'),
        ]);

        $this->actingAs($gestor)->post('/cirugias', $datosComunes)->assertRedirect();

        $this->actingAs($gestor)
            ->post('/cirugias', array_merge($datosComunes, ['idPersona' => $segundo->idPersona]))
            ->assertSessionHasErrors('idQuirofano');

        $this->assertNull(Cirugia::where('idPersonaPaciente', $segundo->idPersona)->first());
    }
}
