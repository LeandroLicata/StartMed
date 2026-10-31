<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Cirugia;
use App\Models\EstadoEvaluacionAnestesica;
use App\Models\EvaluacionAnestesica;
use App\Models\EvaluacionAnestesicaEstado;
use App\Models\Persona;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTurnosTest extends TestCase
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

    public function test_cirugia_futura_muestra_aviso_canales_habituales_y_evaluacion_pendiente(): void
    {
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/turnos");

        $response->assertOk()
            ->assertSee('Turnos previos a tu cirugía')
            ->assertSee('Coordiná tus turnos por los canales habituales')
            ->assertSee('Los turnos (evaluación con el anestesista, cardiología, clínica u otros que te pida tu equipo) se coordinan por los canales habituales del hospital')
            ->assertSee('Evaluación con el anestesista')
            ->assertSee('Pendiente')
            ->assertSee('¿Necesitás coordinar un turno? Contactá al hospital')
            ->assertSee(route('portal.cirugias.show', [$this->cirugia, 'contacto']));
    }

    public function test_cirugia_futura_muestra_evaluacion_anestesica_completa(): void
    {
        $evaluacion = EvaluacionAnestesica::where('idCirugia', $this->cirugia->idCirugia)->firstOrFail();
        $estadoCompletada = EstadoEvaluacionAnestesica::where('nombreEstadoEvaluacionAnestesica', 'Completada')->firstOrFail();

        EvaluacionAnestesicaEstado::where('idEvaluacionAnestesica', $evaluacion->idEvaluacionAnestesica)
            ->whereNull('fechaFinEvaluacionAnestesicaEstado')
            ->update(['fechaFinEvaluacionAnestesicaEstado' => now()]);

        EvaluacionAnestesicaEstado::create([
            'idEvaluacionAnestesica' => $evaluacion->idEvaluacionAnestesica,
            'idEstadoEvaluacionAnestesica' => $estadoCompletada->idEstadoEvaluacionAnestesica,
            'fechaInicioEvaluacionAnestesicaEstado' => now(),
            'fechaFinEvaluacionAnestesicaEstado' => null,
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/turnos");

        $response->assertOk()
            ->assertSee('Evaluación con el anestesista')
            ->assertSee('Completa')
            ->assertDontSee('Pendiente de realización por parte del equipo de anestesia.');
    }

    public function test_muestra_recordatorio_de_estudios_pedientes_con_enlace_a_pestana_estudios(): void
    {
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/turnos");

        $response->assertOk()
            ->assertSee('Estudios prequirúrgicos')
            ->assertSee('Faltan subir')
            ->assertSee('Electrocardiograma')
            ->assertSee(route('portal.cirugias.show', [$this->cirugia, 'estudios']));
    }

    public function test_no_muestra_recordatorio_de_estudios_si_no_hay_pendientes(): void
    {
        // Marcamos todos los estudios como subidos
        $this->cirugia->cirugiaTipoEstudios()->update([
            'fechaSubidaCirugiaTipoEstudio' => now(),
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/turnos");

        $response->assertOk()
            ->assertDontSee('Faltan subir')
            ->assertDontSee('pendiente(s) de subir');
    }

    public function test_no_muestra_horarios_inventados_ni_consultorios_dentro_de_la_tarjeta(): void
    {
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/turnos");
        $response->assertOk();

        $content = $response->getContent();
        $pos = strpos($content, 'Turnos previos a tu cirugía');
        $this->assertNotFalse($pos, 'No se encontró el título de la tarjeta de turnos.');

        $tarjetaHtml = substr($content, $pos);
        $finTarjeta = strpos($tarjetaHtml, '</section>');
        if ($finTarjeta !== false) {
            $tarjetaHtml = substr($tarjetaHtml, 0, $finTarjeta);
        }

        $this->assertStringNotContainsString('Consultorio', $tarjetaHtml);
        $this->assertStringNotContainsString('consultorio', $tarjetaHtml);
        $this->assertStringNotContainsString(' hs', $tarjetaHtml);
    }

    public function test_cirugia_anterior_muestra_texto_de_historial_en_vez_de_indicaciones(): void
    {
        $this->cirugia->update([
            'fechaHoraCirugia' => Carbon::now()->subMonths(2),
        ]);

        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/turnos");

        $response->assertOk()
            ->assertSee('Los turnos de esta cirugía se coordinaron fuera de StartMed.')
            ->assertDontSee('Coordiná tus turnos por los canales habituales')
            ->assertDontSee('¿Necesitás coordinar un turno? Contactá al hospital');
    }

    public function test_un_paciente_no_puede_ver_los_turnos_de_una_cirugia_ajena(): void
    {
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();

        $this->get("/portal/cirugias/{$ajena->idCirugia}/turnos")
            ->assertNotFound();
    }

    public function test_no_muestra_la_palabra_critico(): void
    {
        $response = $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/turnos");

        $response->assertOk()
            ->assertDontSee('Crítico')
            ->assertDontSee('crítico');
    }
}
