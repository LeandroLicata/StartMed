<?php

namespace Tests\Feature;

use App\Models\AutCirugia;
use App\Models\Cirugia;
use App\Models\HisopadoSarm;
use App\Models\ObraSocial;
use App\Models\PedidoHemoderivado;
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

    private function pacienteNuevo(string $documento = '40111222', string $apellidos = 'Molina', string $nombres = 'Carla'): Persona
    {
        return Persona::create([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => $documento,
            'apellidos' => $apellidos,
            'nombres' => $nombres,
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

    /*
     * El buscador de pacientes es un autocompletado: la pantalla no trae los
     * resultados renderizados, los pide por JSON mientras se tipea y los pinta
     * el JS. Por eso estas dos pruebas van contra la respuesta JSON y no
     * contra el HTML, y por eso miran los nombres de los campos: son el
     * contrato con el dropdown, y romperlo lo deja mudo sin que falle nada.
     */
    public function test_buscar_por_dni_exacto_encuentra_un_solo_resultado(): void
    {
        $gestor = $this->conDatosDemo();

        $this->actingAs($gestor)
            ->getJson('/cirugias/nueva?q=28456789')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'documento' => '28456789',
                'nombre_completo' => 'García, María',
            ]);
    }

    public function test_buscar_por_apellido_puede_devolver_varias_coincidencias(): void
    {
        $gestor = $this->conDatosDemo();
        $this->pacienteNuevo('40111230', 'Pérez', 'Marta');
        $this->pacienteNuevo('40111231', 'Pérez', 'Julián');

        $this->actingAs($gestor)
            ->getJson('/cirugias/nueva?q=Pérez')
            ->assertOk()
            ->assertJsonFragment(['nombre_completo' => 'Pérez, Marta'])
            ->assertJsonFragment(['nombre_completo' => 'Pérez, Julián']);
    }

    /**
     * La pantalla sí tiene que traer el buscador, aunque no los resultados: si
     * el JS no encuentra su input, el alta queda sin forma de elegir paciente.
     */
    public function test_la_pantalla_de_alta_trae_el_buscador(): void
    {
        $this->actingAs($this->conDatosDemo())
            ->get('/cirugias/nueva')
            ->assertOk()
            ->assertSee('id="input-buscar-paciente"', false)
            ->assertSee('id="dropdown-pacientes"', false);
    }

    public function test_buscar_sin_resultado_ofrece_dar_de_alta(): void
    {
        $gestor = $this->conDatosDemo();

        $this->actingAs($gestor)
            ->get('/cirugias/nueva?q=40111222')
            ->assertOk()
            ->assertSee('No se encontró')
            ->assertSee('Dar de alta al paciente');
    }

    public function test_dar_de_alta_a_un_paciente_nuevo_lo_deja_seleccionado(): void
    {
        $gestor = $this->conDatosDemo();

        $respuesta = $this->actingAs($gestor)->post('/cirugias/nueva/paciente', [
            'documento' => '40111222',
            'apellidos' => 'Molina',
            'nombres' => 'Carla',
        ]);

        $persona = Persona::where('documento', '40111222')->firstOrFail();
        $respuesta->assertRedirect(route('cirugias.crear', ['persona' => $persona->idPersona]));

        $this->actingAs($gestor)
            ->get(route('cirugias.crear', ['persona' => $persona->idPersona]))
            ->assertOk()
            ->assertSee('Molina, Carla')
            ->assertSee('Datos de la cirugía');
    }

    public function test_la_fecha_llega_precargada_desde_la_agenda(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();
        $fecha = now()->addDays(5)->toDateString();

        $this->actingAs($gestor)
            ->get(route('cirugias.crear', ['persona' => $paciente->idPersona, 'fecha' => $fecha]))
            ->assertOk()
            ->assertSee('value="'.$fecha.'T08:00"', false);
    }

    public function test_no_se_puede_operar_sobre_una_persona_dada_de_baja(): void
    {
        $gestor = $this->conDatosDemo();
        $persona = $this->pacienteNuevo();
        $persona->update(['fechaHoraBajaPersona' => now()]);

        $this->actingAs($gestor)
            ->get(route('cirugias.crear', ['persona' => $persona->idPersona]))
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

    public function test_requiere_hemoderivados_crea_solo_la_cabecera_del_pedido(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();

        $this->actingAs($gestor)
            ->post('/cirugias', $this->datosMinimos($paciente, ['requiereHemoderivados' => '1']))
            ->assertRedirect();

        $cirugia = Cirugia::where('idPersonaPaciente', $paciente->idPersona)->firstOrFail();
        $pedido = PedidoHemoderivado::where('idCirugia', $cirugia->idCirugia)->first();

        $this->assertNotNull($pedido);
        $this->assertSame(0, $pedido->pedidoTipoHemoderivados()->count());
    }

    public function test_sin_tildar_hemoderivados_no_crea_pedido(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($paciente))->assertRedirect();

        $cirugia = Cirugia::where('idPersonaPaciente', $paciente->idPersona)->firstOrFail();
        $this->assertNull(PedidoHemoderivado::where('idCirugia', $cirugia->idCirugia)->first());
    }

    public function test_requiere_hisopado_samr_crea_la_solicitud_pendiente(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();

        $this->actingAs($gestor)
            ->post('/cirugias', $this->datosMinimos($paciente, ['requiereHisopadoSarm' => '1']))
            ->assertRedirect();

        $cirugia = Cirugia::where('idPersonaPaciente', $paciente->idPersona)->firstOrFail();
        $hisopado = HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->first();

        $this->assertNotNull($hisopado);
        $estado = $hisopado->hisopadoSarmEstados()->whereNull('fechaFinAsignacionHisopadoSarmEstado')->first();
        $this->assertSame('Pendiente', $estado->estadoHisopadoSarm->nombreEstadoHisopadoSarm);
    }

    public function test_sin_tildar_hisopado_samr_no_crea_solicitud(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($paciente))->assertRedirect();

        $cirugia = Cirugia::where('idPersonaPaciente', $paciente->idPersona)->firstOrFail();
        $this->assertNull(HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->first());
    }

    public function test_comprobar_disponibilidad_no_crea_ninguna_cirugia_y_conserva_lo_cargado(): void
    {
        $gestor = $this->conDatosDemo();
        $paciente = $this->pacienteNuevo();

        $respuesta = $this->actingAs($gestor)->post('/cirugias/nueva/comprobar', $this->datosMinimos($paciente));

        $respuesta->assertRedirect(route('cirugias.crear', ['persona' => $paciente->idPersona]));
        $this->assertNull(Cirugia::where('idPersonaPaciente', $paciente->idPersona)->first());

        $this->actingAs($gestor)
            ->get($respuesta->headers->get('Location'))
            ->assertSee('Resultado de la comprobación')
            ->assertSee('Disponible');
    }

    public function test_no_se_puede_asignar_el_mismo_quirofano_en_horario_superpuesto(): void
    {
        $gestor = $this->conDatosDemo();
        $primero = $this->pacienteNuevo('40111225');
        $segundo = $this->pacienteNuevo('40111226');
        $base = now()->addDays(12)->setTime(10, 0);

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($primero, [
            'fechaHoraCirugia' => $base->format('Y-m-d\TH:i'),
            'fechaHoraFinCirugia' => $base->copy()->addHours(2)->format('Y-m-d\TH:i'),
        ]))->assertRedirect();

        // Empieza 1 hs despues de que arranco la primera (dentro de su franja de 2 hs).
        $this->actingAs($gestor)
            ->post('/cirugias', $this->datosMinimos($segundo, [
                'fechaHoraCirugia' => $base->copy()->addHour()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('idQuirofano');

        $this->assertNull(Cirugia::where('idPersonaPaciente', $segundo->idPersona)->first());
    }

    public function test_una_cirugia_justo_despues_de_que_termine_la_anterior_no_es_conflicto(): void
    {
        $gestor = $this->conDatosDemo();
        $primero = $this->pacienteNuevo('40111227');
        $segundo = $this->pacienteNuevo('40111228');
        $base = now()->addDays(13)->setTime(10, 0);

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($primero, [
            'fechaHoraCirugia' => $base->format('Y-m-d\TH:i'),
            'fechaHoraFinCirugia' => $base->copy()->addHours(2)->format('Y-m-d\TH:i'),
        ]))->assertRedirect();

        $this->actingAs($gestor)
            ->post('/cirugias', $this->datosMinimos($segundo, [
                'fechaHoraCirugia' => $base->copy()->addHours(2)->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect();

        $this->assertNotNull(Cirugia::where('idPersonaPaciente', $segundo->idPersona)->first());
    }

    public function test_el_mismo_cirujano_no_puede_estar_en_dos_cirugias_superpuestas(): void
    {
        $gestor = $this->conDatosDemo();
        $cirujano = Personal::whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', 'Cirujano'))->first();
        $primero = $this->pacienteNuevo('40111229');
        $segundo = $this->pacienteNuevo('40111233');
        $base = now()->addDays(14)->setTime(9, 0);

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($primero, [
            'idPersonalCirujano' => $cirujano->idPersonal,
            'idQuirofano' => Quirofano::where('nroQuirofano', 1)->value('idQuirofano'),
            'fechaHoraCirugia' => $base->format('Y-m-d\TH:i'),
            'fechaHoraFinCirugia' => $base->copy()->addHours(2)->format('Y-m-d\TH:i'),
        ]))->assertRedirect();

        // Otro quirofano, mismo cirujano, horario superpuesto.
        $this->actingAs($gestor)
            ->post('/cirugias', $this->datosMinimos($segundo, [
                'idPersonalCirujano' => $cirujano->idPersonal,
                'idQuirofano' => Quirofano::where('nroQuirofano', 2)->value('idQuirofano'),
                'fechaHoraCirugia' => $base->copy()->addMinutes(30)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('idPersonalCirujano');

        $this->assertNull(Cirugia::where('idPersonaPaciente', $segundo->idPersona)->first());
    }

    public function test_el_mismo_anestesista_no_puede_estar_en_dos_cirugias_superpuestas(): void
    {
        $gestor = $this->conDatosDemo();
        $anestesista = Personal::whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', 'Anestesista'))->first();
        $primero = $this->pacienteNuevo('40111234');
        $segundo = $this->pacienteNuevo('40111235');
        $base = now()->addDays(15)->setTime(9, 0);

        $this->actingAs($gestor)->post('/cirugias', $this->datosMinimos($primero, [
            'idPersonalAnestesista' => $anestesista->idPersonal,
            'idQuirofano' => Quirofano::where('nroQuirofano', 1)->value('idQuirofano'),
            'fechaHoraCirugia' => $base->format('Y-m-d\TH:i'),
            'fechaHoraFinCirugia' => $base->copy()->addHours(2)->format('Y-m-d\TH:i'),
        ]))->assertRedirect();

        $this->actingAs($gestor)
            ->post('/cirugias', $this->datosMinimos($segundo, [
                'idPersonalAnestesista' => $anestesista->idPersonal,
                'idQuirofano' => Quirofano::where('nroQuirofano', 2)->value('idQuirofano'),
                'fechaHoraCirugia' => $base->copy()->addMinutes(30)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('idPersonalAnestesista');

        $this->assertNull(Cirugia::where('idPersonaPaciente', $segundo->idPersona)->first());
    }
}
