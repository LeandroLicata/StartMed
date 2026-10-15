<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CirugiaQuirofano;
use App\Models\EstadoCirugia;
use App\Support\ResumenCirugia;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * cirugias:check-reprogramar corre cada hora desde el scheduler. Buscaba un
 * estado que no existia en el catalogo ("Listo para la cirugia") y fallaba en
 * cada corrida sin que nadie lo viera: ninguna cirugia pasaba sola a
 * "A reprogramar". Estos tests lo corren de verdad contra la demo.
 */
class CheckCirugiasAReprogramarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
        // Firma el consentimiento de las confirmadas: sin eso ninguna esta lista.
        $this->seed(ExpedienteSeeder::class);

        // Todas fuera de la ventana de 24 horas; cada test mete las que usa.
        Cirugia::query()->update(['fechaHoraCirugia' => now()->addDays(10)]);
    }

    /** @return Collection<int, ResumenCirugia> */
    private function casos(): Collection
    {
        return Cirugia::with(ResumenCirugia::RELACIONES)->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));
    }

    private function enVentana(Cirugia $cirugia): void
    {
        $cirugia->update(['fechaHoraCirugia' => now()->addHours(3)]);
    }

    private function estadoDe(Cirugia $cirugia): string
    {
        return (new ResumenCirugia($cirugia->fresh()->load(ResumenCirugia::RELACIONES)))->estado();
    }

    public function test_una_cirugia_con_pendientes_criticos_pasa_a_reprogramar_y_libera_el_quirofano(): void
    {
        $caso = $this->casos()->first(fn (ResumenCirugia $r) => $r->pendientes()
            ->reject(fn ($p) => $p === 'Consentimiento sin firmar')->isNotEmpty()
            && ! in_array($r->estado(), ['A reprogramar', 'Reprogramada', 'Suspendida', 'Realizada']));
        $this->assertNotNull($caso, 'La demo deberia traer una cirugia con pendientes.');
        $this->enVentana($caso->cirugia);

        $this->artisan('cirugias:check-reprogramar')->assertSuccessful();

        $this->assertSame('A reprogramar', $this->estadoDe($caso->cirugia));
        $this->assertSame(0, CirugiaQuirofano::where('idCirugia', $caso->cirugia->idCirugia)
            ->whereNull('fechaHoraDesasignacion')->count());
        // El estado anterior se cierra, no se pisa: queda uno solo vigente.
        $this->assertSame(1, $caso->cirugia->cirugiaEstados()
            ->whereNull('fechaDesasignacionCirugiaEstado')->count());
    }

    public function test_una_cirugia_lista_pasa_a_confirmada(): void
    {
        $caso = $this->casos()->first(fn (ResumenCirugia $r) => $r->pendientes()->isEmpty());
        $this->assertNotNull($caso, 'La demo deberia traer una cirugia lista.');
        $caso->cirugia->cirugiaEstados()->update(['fechaDesasignacionCirugiaEstado' => now()]);
        $caso->cirugia->cirugiaEstados()->create([
            'idEstadoCirugia' => EstadoCirugia::where('nombreEstadoCirugia', 'Programada')->value('idEstadoCirugia'),
            'fechaAsignacionCirugiaEstado' => now()->subDay(),
        ]);
        $this->enVentana($caso->cirugia);

        $this->artisan('cirugias:check-reprogramar')->assertSuccessful();

        $this->assertSame('Confirmada', $this->estadoDe($caso->cirugia));
    }

    public function test_no_toca_una_cirugia_suspendida_ni_las_que_estan_fuera_de_la_ventana(): void
    {
        $casos = $this->casos();
        $suspendida = $casos->first();
        $suspendida->cirugia->cirugiaEstados()->update(['fechaDesasignacionCirugiaEstado' => now()]);
        $suspendida->cirugia->cirugiaEstados()->create([
            'idEstadoCirugia' => EstadoCirugia::where('nombreEstadoCirugia', 'Suspendida')->value('idEstadoCirugia'),
            'fechaAsignacionCirugiaEstado' => now()->subDay(),
        ]);
        $this->enVentana($suspendida->cirugia);

        $antes = $casos->skip(1)->mapWithKeys(fn (ResumenCirugia $r) => [$r->cirugia->idCirugia => $r->estado()]);

        $this->artisan('cirugias:check-reprogramar')->assertSuccessful();

        $this->assertSame('Suspendida', $this->estadoDe($suspendida->cirugia));
        foreach ($antes as $id => $estado) {
            $this->assertSame($estado, $this->estadoDe(Cirugia::find($id)));
        }
    }
}
