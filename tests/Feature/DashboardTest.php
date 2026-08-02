<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\Usuario;
use App\Support\ResumenCirugia;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function conDatosDemo(): Usuario
    {
        $this->seed(CatalogosSeeder::class);
        $this->seed(DemoSeeder::class);

        return Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
    }

    private function resumen(string $apellido): ResumenCirugia
    {
        $cirugia = Cirugia::with([
            'paciente.planObraSociales.plan.obraSocial',
            'cirujano.persona', 'anestesista.persona', 'tipoCirugia',
            'cirugiaEstados.estadoCirugia',
            'cirugiaQuirofanos.quirofano',
            'cirugiaTipoEstudios.tipoEstudio',
            'autCirugias.plan.obraSocial',
            'autCirugias.autoCirugiaEstados.estadoAutCirugia',
            'pedidoMateriales.pedidoMaterialEstados.estadoPedidoMaterial',
            'evaluacionAnestesicas.evaluacionAnestesicaEstados.estadoEvaluacionAnestesica',
            'evaluacionAnestesicas.evaluacionTipoAsas.tipoAsa',
        ])
            ->whereHas('paciente', fn ($q) => $q->where('apellidos', $apellido))
            ->firstOrFail();

        return new ResumenCirugia($cirugia);
    }

    public function test_el_tablero_muestra_las_cirugias_proximas(): void
    {
        $usuario = $this->conDatosDemo();

        $this->actingAs($usuario)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ramírez, Luis')
            ->assertSee('Prótesis total de rodilla')
            ->assertSee('Agenda de hoy')
            ->assertSee('Cirugías de hoy')
            ->assertSee('Estado de los pacientes');
    }

    public function test_una_cirugia_sin_pendientes_queda_lista(): void
    {
        $this->conDatosDemo();

        $garcia = $this->resumen('García');

        $this->assertTrue($garcia->autorizacionAprobada());
        $this->assertSame(0, $garcia->estudiosPendientes());
        $this->assertTrue($garcia->evaluacionCompleta());
        $this->assertTrue($garcia->estaLista(), 'García no debería tener pendientes.');
        $this->assertSame('exito', $garcia->semaforo());
    }

    public function test_una_cirugia_con_materiales_en_auditoria_no_esta_lista(): void
    {
        $this->conDatosDemo();

        $ramirez = $this->resumen('Ramírez');

        $this->assertSame('En auditoría', $ramirez->materiales());
        $this->assertFalse($ramirez->materialesAprobados());
        $this->assertFalse($ramirez->estaLista());
        $this->assertContains('Materiales: en auditoría', $ramirez->pendientes()->all());
        $this->assertSame('ASA III', $ramirez->asa());
        $this->assertEqualsWithDelta(7100.0, $ramirez->importeMateriales(), 0.01);
    }

    public function test_los_estudios_sin_subir_se_cuentan_como_pendientes(): void
    {
        $this->conDatosDemo();

        $lopez = $this->resumen('López');

        $this->assertSame(2, $lopez->estudiosTotal());
        $this->assertSame(1, $lopez->estudiosPendientes());
        $this->assertContains('Electrocardiograma', $lopez->nombresEstudiosPendientes()->all());
        $this->assertFalse($lopez->estaLista());
    }

    public function test_una_cirugia_particular_no_necesita_autorizacion(): void
    {
        $this->conDatosDemo();

        $rodriguez = $this->resumen('Rodríguez');

        $this->assertSame('Sin cobertura', $rodriguez->autorizacion());
        $this->assertTrue($rodriguez->autorizacionAprobada());
    }

    /**
     * Guarda contra N+1: la cantidad de consultas tiene que depender de las
     * relaciones que se cargan, no de cuantas cirugias haya.
     */
    public function test_el_numero_de_consultas_no_crece_con_las_cirugias(): void
    {
        $usuario = $this->conDatosDemo();

        $contar = function () use ($usuario): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($usuario)->get('/dashboard')->assertOk();
            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $consultas;
        };

        // El primer request de la sesión de test hace un par de consultas de
        // arranque que después no se repiten; se descarta para no medirlas.
        $contar();

        $conSeisCirugias = $contar();

        // Se duplican las cirugias existentes, cada una con su propio paciente.
        foreach (Cirugia::with('paciente')->get() as $original) {
            $paciente = $original->paciente->replicate();
            $paciente->documento = (string) random_int(40_000_000, 49_999_999);
            $paciente->save();

            $copia = $original->replicate();
            $copia->idPersonaPaciente = $paciente->idPersona;
            $copia->save();
        }

        $this->assertSame(12, Cirugia::count());
        $this->assertSame(
            $conSeisCirugias,
            $contar(),
            'El tablero hace más consultas al haber más cirugías: hay un N+1.'
        );
    }

    public function test_el_tablero_funciona_sin_ninguna_cirugia(): void
    {
        // DatabaseSeeder solo corre DemoSeeder en local, no en testing.
        $this->seed();

        $this->actingAs(Usuario::where('nombreUsuario', 'admin')->firstOrFail())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No hay cirugías programadas');
    }
}
