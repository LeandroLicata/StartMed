<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Cirugia;
use App\Models\Persona;
use App\Models\PreparacionPaciente;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPreparacionTest extends TestCase
{
    use RefreshDatabase;

    private Persona $maria;

    private Cirugia $cirugia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);

        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->actingAs($this->maria, 'paciente');

        // La cirugía futura de María (mañana a las 09:00).
        $this->cirugia = Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->where('fechaHoraCirugia', '>', today()->endOfDay())
            ->firstOrFail();
    }

    public function test_muestra_bloques_e_indicaciones(): void
    {
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion");

        $response->assertOk()
            ->assertSee('Indicaciones de preparación')
            ->assertSee('Ayuno')
            ->assertSee('Sólidos')
            ->assertSee('Líquidos claros')
            ->assertSee('Tabaco')
            ->assertSee('Higiene prequirúrgica')
            ->assertSee('Ducha con antiséptico')
            ->assertSee('8 horas antes de la cirugía');
    }

    public function test_calcula_bien_la_ultima_ingesta_incluyendo_el_cruce_de_medianoche(): void
    {
        // Fijamos la cirugía un domingo a las 07:00 hs.
        // Ayuno de 8 horas: 07:00 menos 8 horas = 23:00 hs del sábado (cruce de medianoche).
        // Ayuno de 2 horas: 07:00 menos 2 horas = 05:00 hs del mismo domingo.
        $this->cirugia->update([
            'fechaHoraCirugia' => Carbon::parse('2026-11-15 07:00:00'),
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion");

        $response->assertOk()
            ->assertSee('Última ingesta')
            // Cruce de medianoche: 15/11 07:00 - 8 hs = 14/11 23:00 hs
            ->assertSee('Sábado 14 de noviembre de 2026, 23:00 hs')
            // Mismo día: 15/11 07:00 - 2 hs = 15/11 05:00 hs
            ->assertSee('Domingo 15 de noviembre de 2026, 05:00 hs');
    }

    public function test_muestra_el_recordatorio_solo_si_hay(): void
    {
        $prep = PreparacionPaciente::where('idCirugia', $this->cirugia->idCirugia)->firstOrFail();

        // Con recordatorio
        $prep->update(['observacionesPreparacionPaciente' => 'Tomar la pastilla habitual con un sorbo pequeño de agua.']);

        $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion")
            ->assertOk()
            ->assertSee('Recordatorio')
            ->assertSee('Tomar la pastilla habitual con un sorbo pequeño de agua.');

        // Sin recordatorio
        $prep->update(['observacionesPreparacionPaciente' => null]);

        $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion")
            ->assertOk()
            ->assertDontSee('Recordatorio');
    }

    public function test_muestra_el_recordatorio_aun_sin_indicaciones(): void
    {
        $prep = PreparacionPaciente::where('idCirugia', $this->cirugia->idCirugia)->firstOrFail();
        // Borramos todas las indicaciones pero mantenemos el recordatorio
        $prep->preparacionPacienteTipoPreparaciones()->each(function ($b) {
            $b->preparacionPacienteTipoPreparacionTipoIndicaciones()->delete();
            $b->delete();
        });
        $prep->update(['observacionesPreparacionPaciente' => 'Suspender anticoagulantes según indicación del hematólogo.']);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion");

        $response->assertOk()
            ->assertSee('Recordatorio')
            ->assertSee('Suspender anticoagulantes según indicación del hematólogo.')
            ->assertDontSee('Tu equipo todavía no cargó las indicaciones')
            ->assertDontSee('No se cargaron indicaciones de preparación para esta cirugía.');
    }

    private function eliminarPreparacion(Cirugia $cirugia): void
    {
        $prep = PreparacionPaciente::where('idCirugia', $cirugia->idCirugia)->first();
        if ($prep) {
            $prep->preparacionPacienteTipoPreparaciones()->each(function ($b) {
                $b->preparacionPacienteTipoPreparacionTipoIndicaciones()->delete();
                $b->delete();
            });
            $prep->delete();
        }
    }

    public function test_muestra_estado_vacio_en_cirugia_futura(): void
    {
        // Borramos la preparación de esta cirugía para simular que el equipo aún no la cargó
        $this->eliminarPreparacion($this->cirugia);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion");

        $response->assertOk()
            ->assertSee('Tu equipo todavía no cargó las indicaciones')
            ->assertDontSee('No se cargaron indicaciones de preparación para esta cirugía.')
            ->assertDontSee('Ayuno')
            ->assertDontSee('Recordatorio')
            ->assertDontSee('Última ingesta');
    }

    public function test_muestra_estado_vacio_en_cirugia_anterior(): void
    {
        $this->eliminarPreparacion($this->cirugia);
        $this->cirugia->update([
            'fechaHoraCirugia' => Carbon::now()->subMonths(2),
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion");

        $response->assertOk()
            ->assertSee('No se cargaron indicaciones de preparación para esta cirugía.')
            ->assertDontSee('todavía')
            ->assertDontSee('Tu equipo todavía no cargó las indicaciones')
            ->assertDontSee('Ayuno')
            ->assertDontSee('Recordatorio');
    }

    public function test_un_paciente_no_puede_ver_la_preparacion_de_una_cirugia_ajena(): void
    {
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();

        // Debe dar 404, no 403 ni 200, conforme al contrato del portal
        $this->get("/portal/cirugias/{$ajena->idCirugia}/preparacion")
            ->assertNotFound();
    }

    public function test_no_muestra_la_etiqueta_critico(): void
    {
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion");

        $response->assertOk()
            ->assertDontSee('Crítico')
            ->assertDontSee('crítico');
    }

    public function test_cirugia_anterior_se_ve_igual_sin_avisos_imperativos(): void
    {
        // Convertimos la cirugía en anterior
        $this->cirugia->update([
            'fechaHoraCirugia' => Carbon::now()->subMonths(2),
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/preparacion");

        $response->assertOk()
            ->assertSee('Ayuno')
            ->assertSee('Sólidos')
            ->assertDontSee('tenés que')
            ->assertDontSee('Tenés que');
    }

    public function test_proceso_paciente_no_incluye_preparacion_como_pendiente(): void
    {
        // Sin preparación: no debe aparecer en el proceso del paciente ni figurar como pendiente
        $this->eliminarPreparacion($this->cirugia);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}");

        $response->assertOk()
            ->assertDontSee('Indicaciones de preparación');

        // Y la pestaña propia de preparación sigue existiendo en el menú de navegación
        $response->assertSee(route('portal.cirugias.show', [$this->cirugia, 'preparacion']));
    }
}
