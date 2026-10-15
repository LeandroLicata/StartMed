<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Http\Controllers\Portal\CirugiaController;
use App\Models\Cirugia;
use App\Models\EstadoCirugia;
use App\Models\Persona;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Próximas cirugías, historial y el detalle de una cirugía en el portal del
 * paciente. La pertenencia en sí la cubre PortalContratoTest.
 */
class PortalCirugiasTest extends TestCase
{
    use RefreshDatabase;

    private Persona $maria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->actingAs($this->maria, 'paciente');
    }

    private function cirugiaDeMaria(): Cirugia
    {
        return Cirugia::where('idPersonaPaciente', $this->maria->idPersona)->firstOrFail();
    }

    /** Una cirugía pasada de María, suspendida: el historial no filtra por estado. */
    private function cirugiaPasadaSuspendida(): Cirugia
    {
        $pasada = $this->cirugiaDeMaria()->replicate();
        $pasada->fechaHoraCirugia = now()->subMonths(2);
        $pasada->save();

        $pasada->cirugiaEstados()->create([
            'idEstadoCirugia' => EstadoCirugia::where('nombreEstadoCirugia', 'Suspendida')->value('idEstadoCirugia'),
            'fechaAsignacionCirugiaEstado' => now()->subMonths(2),
        ]);

        return $pasada;
    }

    public function test_proximas_muestra_solo_las_cirugias_futuras_del_paciente(): void
    {
        $propia = $this->cirugiaDeMaria();
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();
        $pasada = $this->cirugiaPasadaSuspendida();

        $this->get('/portal')
            ->assertOk()
            ->assertSee(route('portal.cirugias.show', $propia))
            ->assertDontSee(route('portal.cirugias.show', $ajena))
            ->assertDontSee(route('portal.cirugias.show', $pasada));
    }

    public function test_el_historial_muestra_las_pasadas_en_cualquier_estado(): void
    {
        $pasada = $this->cirugiaPasadaSuspendida();

        $this->get('/portal/historial')
            ->assertOk()
            ->assertSee(route('portal.cirugias.show', $pasada))
            ->assertSee('Suspendida')
            ->assertDontSee(route('portal.cirugias.show', $this->cirugiaDeMaria()));
    }

    public function test_sin_cirugias_cada_lista_lo_dice(): void
    {
        $this->get('/portal/historial')->assertOk()->assertSee('Todavía no tenés cirugías anteriores.');

        Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->update(['fechaHoraCirugia' => now()->subYear()]);

        $this->get('/portal')->assertOk()->assertSee('No tenés cirugías programadas.');
    }

    public function test_el_detalle_abre_cada_pestana(): void
    {
        $cirugia = $this->cirugiaDeMaria();

        foreach (array_keys(CirugiaController::SECCIONES) as $seccion) {
            $this->get("/portal/cirugias/{$cirugia->idCirugia}/{$seccion}")->assertOk();
        }

        $this->get("/portal/cirugias/{$cirugia->idCirugia}")->assertOk()->assertSee('Tu cirugía');
        $this->get("/portal/cirugias/{$cirugia->idCirugia}/inventada")->assertNotFound();
    }

    public function test_una_cirugia_pasada_se_abre_en_solo_lectura(): void
    {
        $aviso = 'ya no se puede modificar';

        $this->get('/portal/cirugias/'.$this->cirugiaPasadaSuspendida()->idCirugia)
            ->assertOk()
            ->assertSee($aviso);

        $this->get('/portal/cirugias/'.$this->cirugiaDeMaria()->idCirugia)
            ->assertOk()
            ->assertDontSee($aviso);
    }

    /**
     * Guarda contra N+1, igual que DashboardTest: la cantidad de consultas
     * depende de las relaciones cargadas, no de cuantas cirugias tenga.
     */
    public function test_el_numero_de_consultas_no_crece_con_las_cirugias(): void
    {
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/portal')->assertOk();
            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $consultas;
        };

        $contar();
        $conUna = $contar();

        foreach (range(1, 3) as $i) {
            $copia = $this->cirugiaDeMaria()->replicate();
            $copia->fechaHoraCirugia = now()->addDays(10 + $i);
            $copia->save();
        }

        $this->assertSame($conUna, $contar(), 'Próximas cirugías hace más consultas al haber más cirugías: hay un N+1.');
    }
}
