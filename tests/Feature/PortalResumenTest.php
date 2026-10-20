<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Cirugia;
use App\Models\Persona;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encabezado y pestaña Resumen del detalle de una cirugía en el portal: todo
 * tiene que salir de la base o de config/portal.php, nada inventado.
 */
class PortalResumenTest extends TestCase
{
    use RefreshDatabase;

    private Cirugia $cirugia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        $maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->actingAs($maria, 'paciente');

        // La de mañana: Hernioplastia, OSDE 410, un estudio subido y otro no.
        $this->cirugia = Cirugia::where('idPersonaPaciente', $maria->idPersona)
            ->where('fechaHoraCirugia', '>', today()->endOfDay())
            ->firstOrFail();
        $this->cirugia->update(['fechaHoraCirugia' => today()->addDays(5)->setTime(9, 0)]);
    }

    private function resumen()
    {
        return $this->get("/portal/cirugias/{$this->cirugia->idCirugia}")->assertOk();
    }

    public function test_los_datos_salen_de_la_cirugia(): void
    {
        $this->resumen()
            ->assertSee('Hernioplastia inguinal')
            ->assertSee('López, Silvia')
            ->assertSee('Ramos, Hernán')
            ->assertSee('Quirófano central')
            ->assertSee('OSDE 410')
            ->assertSee('Enviada');
    }

    public function test_la_hora_de_llegada_es_media_hora_antes(): void
    {
        $this->resumen()->assertSeeInOrder(['Llegá a las', '08:30 hs']);
    }

    public function test_la_direccion_sale_de_la_configuracion_y_sin_ella_no_se_muestra(): void
    {
        config(['portal.direccion_hospital' => 'Paso de los Andes 3051, Mendoza']);
        $this->resumen()->assertSeeInOrder(['Dirección', 'Paso de los Andes 3051, Mendoza']);

        config(['portal.direccion_hospital' => null]);
        $this->resumen()->assertDontSee('Dirección');
    }

    public function test_no_queda_nada_de_la_captura_del_mock(): void
    {
        $this->resumen()
            ->assertDontSee('Av. Rivadavia')
            ->assertDontSee('Turno cardiólogo')
            ->assertDontSee('Turno anestesista')
            ->assertDontSee('Esta sección todavía está en construcción');
    }

    public function test_el_estado_del_proceso_refleja_lo_que_falta_y_lleva_a_su_pestana(): void
    {
        $this->resumen()
            ->assertSee('1 de 2 subidos')
            ->assertSee('Sin firmar')
            ->assertSee(route('portal.cirugias.show', [$this->cirugia, 'estudios']))
            ->assertSee(route('portal.cirugias.show', [$this->cirugia, 'consentimiento']));
    }
}
