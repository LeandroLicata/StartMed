<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Database\Seeders\HistorialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseSeeder deja los catálogos y el usuario admin. Los datos de
        // demo no los corre solo, porque en testing no estamos en local.
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);
    }

    private function usuario(string $nombre): Usuario
    {
        return Usuario::where('nombreUsuario', $nombre)->firstOrFail();
    }

    public function test_el_cirujano_ve_solo_sus_cirugias(): void
    {
        $this->actingAs($this->usuario('perez'))
            ->get('/cirujano')
            ->assertOk()
            // Casos del Dr. Pérez.
            ->assertSee('García, María')
            ->assertSee('Ramírez, Luis')
            // Caso de la Dra. López, no debería aparecer.
            ->assertDontSee('Fernández, Carla');
    }

    public function test_el_anestesista_ve_sus_evaluaciones_y_las_alergias(): void
    {
        $this->actingAs($this->usuario('ramos'))
            ->get('/anestesista')
            ->assertOk()
            ->assertSee('ASA III')
            ->assertSee('Alergia documentada');
    }

    public function test_direccion_muestra_la_serie_historica(): void
    {
        $this->seed(HistorialSeeder::class);

        $this->actingAs($this->usuario('admin'))
            ->get('/direccion')
            ->assertOk()
            ->assertSee('Tasa de suspensión')
            ->assertSee('Volumen mensual');
    }

    public function test_el_expediente_muestra_materiales_hemoderivados_y_consentimiento(): void
    {
        $ramirez = Cirugia::whereHas('paciente', fn ($q) => $q->where('apellidos', 'Ramírez'))->firstOrFail();
        $gestor = $this->usuario('gonzalez');

        // Resumen (tab por defecto): checklist y consentimiento.
        $this->actingAs($gestor)
            ->get("/cirugias/{$ramirez->idCirugia}")
            ->assertOk()
            ->assertSee('Prótesis total de rodilla')
            ->assertSee('NO administrar ampicilina', false)
            ->assertSee('Consentimiento informado');

        // Cada modulo vive en su propia solapa.
        $this->actingAs($gestor)
            ->get("/cirugias/{$ramirez->idCirugia}?tab=materiales")
            ->assertOk()
            ->assertSee('USD 7.100,00');

        $this->actingAs($gestor)
            ->get("/cirugias/{$ramirez->idCirugia}?tab=hemoderivados")
            ->assertOk()
            ->assertSee('Glóbulos rojos desplasmatizados');
    }

    public function test_el_portal_del_paciente_calcula_las_horas_de_ayuno(): void
    {
        $garcia = Cirugia::whereHas('paciente', fn ($q) => $q->where('apellidos', 'García'))->firstOrFail();

        $respuesta = $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$garcia->idCirugia}/portal-paciente")
            ->assertOk()
            ->assertSee('Preparación para la cirugía')
            ->assertSee('8 hs antes');

        // El horario límite se calcula restando las horas a la cirugía.
        $limite = $garcia->fechaHoraCirugia->copy()->subHours(8)->format('H:i');
        $respuesta->assertSee($limite.' hs');
    }

    public function test_cada_panel_esta_cerrado_a_los_otros_roles(): void
    {
        $this->actingAs($this->usuario('perez'))->get('/anestesista')->assertForbidden();
        $this->actingAs($this->usuario('ramos'))->get('/cirujano')->assertForbidden();
        $this->actingAs($this->usuario('perez'))->get('/direccion')->assertForbidden();
    }

    public function test_el_administrador_entra_a_todos_los_paneles(): void
    {
        foreach (['/dashboard', '/cirujano', '/anestesista', '/direccion'] as $ruta) {
            $this->actingAs($this->usuario('admin'))->get($ruta)->assertOk();
        }
    }

    public function test_los_paneles_no_disparan_consultas_por_cada_cirugia(): void
    {
        $usuario = $this->usuario('gonzalez');

        $contar = function (string $ruta) use ($usuario): int {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->actingAs($usuario)->get($ruta)->assertOk();
            $n = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $n;
        };

        $contar('/dashboard');           // descarta el arranque
        $antes = $contar('/dashboard');

        $this->seed(HistorialSeeder::class);

        $this->assertGreaterThan(200, Cirugia::count());
        $this->assertSame($antes, $contar('/dashboard'));
    }
}
