<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Database\Seeders\HistorialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /**
     * Clona una cirugía futura, una copia por día, cada una con su paciente,
     * para ver cómo se comporta un panel con la agenda cargada.
     */
    private function clonarProximas(Cirugia $original, int $cuantas): void
    {
        foreach (range(1, $cuantas) as $n) {
            $paciente = $original->paciente->replicate();
            $paciente->apellidos = 'Paginado'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $paciente->documento = (string) (40_000_000 + $n);
            $paciente->save();

            $copia = $original->replicate();
            $copia->idPersonaPaciente = $paciente->idPersona;
            $copia->fechaHoraCirugia = $original->fechaHoraCirugia->copy()->addDays($n);
            $copia->fechaHoraFinCirugia = $original->fechaHoraFinCirugia?->copy()->addDays($n);
            $copia->save();
        }
    }

    /**
     * Cirugías futuras de un profesional, que es lo que listan los paneles.
     */
    private function proximasDe(string $columna, int $idPersonal): int
    {
        return Cirugia::where($columna, $idPersonal)
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->count();
    }

    /**
     * La agenda de un cirujano no tiene techo. El panel la pagina, y cada
     * listado usa su propio nombre de página para no arrastrar a los otros.
     */
    public function test_el_panel_del_cirujano_pagina_su_agenda(): void
    {
        $perez = $this->usuario('perez');
        $idPersonal = $perez->personal->idPersonal;

        // Futura: el panel lista de hoy en adelante, así que clonar una vieja
        // dejaría la mitad de las copias afuera.
        $suya = Cirugia::where('idPersonalCirujano', $idPersonal)
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->orderBy('fechaHoraCirugia')
            ->firstOrFail();

        $this->clonarProximas($suya, 15);

        // La última copia es la más lejana en el tiempo, así que cae al final
        // del listado. Cuántas páginas haya depende de lo que ya sembró la
        // demo, y eso no debería atarse acá.
        $ultima = (int) ceil($this->proximasDe('idPersonalCirujano', $idPersonal) / 10);

        $this->actingAs($perez)
            ->get('/cirujano')
            ->assertOk()
            ->assertDontSee('Paginado15')
            ->assertSee('proximas='.$ultima);

        $this->actingAs($perez)
            ->get('/cirujano?proximas='.$ultima)
            ->assertOk()
            ->assertSee('Paginado15');
    }

    public function test_la_bandeja_del_anestesista_pagina(): void
    {
        $ramos = $this->usuario('ramos');
        $idPersonal = $ramos->personal->idPersonal;

        $suya = Cirugia::where('idPersonalAnestesista', $idPersonal)
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->orderBy('fechaHoraCirugia')
            ->firstOrFail();

        $this->clonarProximas($suya, 15);

        $ultima = (int) ceil($this->proximasDe('idPersonalAnestesista', $idPersonal) / 10);

        $this->actingAs($ramos)
            ->get('/anestesista')
            ->assertOk()
            ->assertDontSee('Paginado15')
            ->assertSee('page='.$ultima);

        $this->actingAs($ramos)
            ->get('/anestesista?page='.$ultima)
            ->assertOk()
            ->assertSee('Paginado15');
    }

    /**
     * "Cirugías de la semana" arranca en la semana actual, pero el gestor
     * puede estirar el rango: lo que entre ahí se pagina.
     */
    public function test_el_tablero_pagina_las_cirugias_del_periodo(): void
    {
        $proxima = Cirugia::whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->orderBy('fechaHoraCirugia')
            ->firstOrFail();

        $this->clonarProximas($proxima, 20);

        $rango = [
            'desde' => Carbon::today()->toDateString(),
            'hasta' => Carbon::today()->addDays(40)->toDateString(),
        ];

        $html = $this->actingAs($this->usuario('gonzalez'))
            ->get('/dashboard?'.http_build_query($rango))
            ->assertOk()
            // La copia más lejana queda para otra página.
            ->assertDontSee('Paginado20')
            ->assertSee('page=2')
            ->getContent();

        // Una fila por cirugía en la tabla, y nunca más de una página.
        $this->assertSame(15, substr_count($html, 'aria-label="Ver cirugía de'));
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
