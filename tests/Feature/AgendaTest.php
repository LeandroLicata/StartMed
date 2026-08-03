<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private function conDatosDemo(): Usuario
    {
        $this->seed(CatalogosSeeder::class);
        $this->seed(DemoSeeder::class);

        return Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
    }

    public function test_un_invitado_no_puede_acceder_a_la_agenda(): void
    {
        $this->get('/agenda')->assertRedirect(route('login'));
    }

    public function test_un_rol_distinto_de_gestor_no_puede_acceder(): void
    {
        $this->conDatosDemo();
        $cirujano = Usuario::where('nombreUsuario', 'perez')->firstOrFail();

        $this->actingAs($cirujano)->get('/agenda')->assertForbidden();
    }

    public function test_la_agenda_arranca_en_la_vista_mes(): void
    {
        $usuario = $this->conDatosDemo();

        $this->actingAs($usuario)
            ->get('/agenda')
            ->assertOk()
            ->assertSee('Mes')
            ->assertSee('Semana')
            ->assertSee(Carbon::today()->translatedFormat('F'));
    }

    public function test_la_vista_mes_navega_a_otro_mes_por_query_param(): void
    {
        $usuario = $this->conDatosDemo();

        $this->actingAs($usuario)
            ->get('/agenda?mes=2026-03')
            ->assertOk()
            ->assertSee('marzo')
            ->assertSee(route('agenda', ['mes' => '2026-02']))
            ->assertSee(route('agenda', ['mes' => '2026-04']));
    }

    public function test_la_vista_semana_muestra_las_cirugias_de_hoy(): void
    {
        $usuario = $this->conDatosDemo();

        $this->actingAs($usuario)
            ->get('/agenda?vista=semana')
            ->assertOk()
            ->assertSee('García, María');
    }

    public function test_la_vista_semana_navega_a_otra_semana_por_query_param(): void
    {
        $usuario = $this->conDatosDemo();
        $semanaSiguiente = Carbon::today()->startOfWeek()->addWeek()->toDateString();

        $this->actingAs($usuario)
            ->get('/agenda?vista=semana')
            ->assertOk()
            ->assertSee(route('agenda', ['vista' => 'semana', 'semana' => $semanaSiguiente]));
    }

    public function test_el_dia_muestra_solo_sus_cirugias_ordenadas_por_horario(): void
    {
        $usuario = $this->conDatosDemo();
        $hoy = Carbon::today()->toDateString();

        $respuesta = $this->actingAs($usuario)
            ->get("/agenda/{$hoy}")
            ->assertOk()
            ->assertSee('García, María')
            ->assertSee('López, Ramiro')
            ->assertSee('Fernández, Carla')
            ->assertSee('Rodríguez, Ana');

        $contenido = $respuesta->getContent();

        // Garcia es a las 07:30 y Rodriguez a las 14:00: tiene que aparecer antes en el HTML.
        $this->assertLessThan(
            strpos($contenido, 'Rodríguez, Ana'),
            strpos($contenido, 'García, María'),
        );
    }

    public function test_el_dia_se_puede_filtrar_igual_que_cirugias_de_la_semana(): void
    {
        $usuario = $this->conDatosDemo();
        $hoy = Carbon::today()->toDateString();

        $this->actingAs($usuario)
            ->get("/agenda/{$hoy}?estado=En+riesgo")
            ->assertOk()
            ->assertSee('López, Ramiro')
            ->assertDontSee('García, María');
    }

    public function test_el_boton_de_nueva_cirugia_precarga_la_fecha_del_dia(): void
    {
        $usuario = $this->conDatosDemo();
        $fecha = Carbon::today()->addDays(3)->toDateString();

        $this->actingAs($usuario)
            ->get("/agenda/{$fecha}")
            ->assertOk()
            ->assertSee(route('cirugias.crear', ['fecha' => $fecha]));
    }
}
