<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\EstadoCirugia;
use App\Services\CambiarEstadoCirugiaService;
use App\Support\ResumenCirugia;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El cambio de estado compartido: lo usan la cancelacion del staff y la
 * confirmacion de asistencia del paciente.
 */
class CambiarEstadoCirugiaServiceTest extends TestCase
{
    use RefreshDatabase;

    private CambiarEstadoCirugiaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->servicio = app(CambiarEstadoCirugiaService::class);
    }

    private function vigentes(Cirugia $cirugia)
    {
        return CirugiaEstado::where('idCirugia', $cirugia->idCirugia)
            ->whereNull('fechaDesasignacionCirugiaEstado')
            ->get();
    }

    public function test_cierra_el_vigente_y_abre_uno_nuevo(): void
    {
        $cirugia = Cirugia::firstOrFail();
        $anterior = $this->vigentes($cirugia)->sole();
        $filas = CirugiaEstado::where('idCirugia', $cirugia->idCirugia)->count();

        $this->servicio->cambiar($cirugia, 'Asistencia de paciente confirmada');

        $this->assertNotNull($anterior->fresh()->fechaDesasignacionCirugiaEstado, 'El estado anterior quedó abierto.');
        $this->assertSame(
            'Asistencia de paciente confirmada',
            $this->vigentes($cirugia)->sole()->estadoCirugia->nombreEstadoCirugia,
        );
        // Historial: no se pisa nada, se agrega una fila.
        $this->assertSame($filas + 1, CirugiaEstado::where('idCirugia', $cirugia->idCirugia)->count());
    }

    /** Datos viejos o cargados a mano pueden traer mas de uno abierto: quedan todos cerrados. */
    public function test_cierra_todos_los_vigentes_aunque_haya_mas_de_uno(): void
    {
        $cirugia = Cirugia::firstOrFail();
        CirugiaEstado::create([
            'idCirugia' => $cirugia->idCirugia,
            'idEstadoCirugia' => EstadoCirugia::where('nombreEstadoCirugia', 'En riesgo')->value('idEstadoCirugia'),
            'fechaAsignacionCirugiaEstado' => now(),
        ]);

        $this->servicio->cambiar($cirugia, 'A reprogramar');

        $this->assertSame('A reprogramar', $this->vigentes($cirugia)->sole()->estadoCirugia->nombreEstadoCirugia);
    }

    public function test_no_deja_la_relacion_cargada_con_el_estado_viejo(): void
    {
        $cirugia = Cirugia::with(ResumenCirugia::RELACIONES)->firstOrFail();
        $this->assertNotSame('A reprogramar', (new ResumenCirugia($cirugia))->estado());

        $this->servicio->cambiar($cirugia, 'A reprogramar');

        $this->assertSame('A reprogramar', (new ResumenCirugia($cirugia->load('cirugiaEstados.estadoCirugia')))->estado());
    }

    public function test_un_estado_que_no_existe_falla_sin_tocar_nada(): void
    {
        $cirugia = Cirugia::firstOrFail();
        $antes = $this->vigentes($cirugia)->sole();

        try {
            $this->servicio->cambiar($cirugia, 'Inventado');
            $this->fail('Un estado inexistente tendría que fallar.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertTrue($this->vigentes($cirugia)->sole()->is($antes));
    }
}
