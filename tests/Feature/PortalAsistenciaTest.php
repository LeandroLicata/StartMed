<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\CirugiaQuirofano;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\CambiarEstadoCirugiaService;
use App\Support\ResumenCirugia;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Confirmación de asistencia del paciente: aparece a 48 h o menos y cada
 * respuesta cierra el estado vigente y abre uno nuevo.
 */
class PortalAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO = 'Confirmá tu asistencia';

    private Persona $maria;

    private Cirugia $cirugia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->actingAs($this->maria, 'paciente');

        // La de mañana, en estado 'Programada'.
        $this->cirugia = Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->where('fechaHoraCirugia', '>', today()->endOfDay())
            ->firstOrFail();
        $this->dentroDeHoras(24);
    }

    private function dentroDeHoras(int $horas): void
    {
        $this->cirugia->update(['fechaHoraCirugia' => now()->addHours($horas)]);
    }

    private function detalle()
    {
        return $this->get("/portal/cirugias/{$this->cirugia->idCirugia}")->assertOk();
    }

    private function responder(string $respuesta, ?Cirugia $cirugia = null)
    {
        $cirugia ??= $this->cirugia;

        return $this->post("/portal/cirugias/{$cirugia->idCirugia}/asistencia", ['respuesta' => $respuesta]);
    }

    /** @return Collection<int, CirugiaEstado> */
    private function estados(): Collection
    {
        return CirugiaEstado::with('estadoCirugia')
            ->where('idCirugia', $this->cirugia->idCirugia)
            ->get();
    }

    private function vigente(): string
    {
        return $this->estados()
            ->whereNull('fechaDesasignacionCirugiaEstado')
            ->sole()
            ->estadoCirugia->nombreEstadoCirugia;
    }

    public function test_el_aviso_aparece_a_48_horas_o_menos_y_no_antes(): void
    {
        $this->dentroDeHoras(47);
        $this->detalle()->assertSee(self::AVISO)->assertSee('Sí, voy a estar')->assertSee('Necesito reprogramar');

        $this->dentroDeHoras(49);
        $this->detalle()->assertDontSee(self::AVISO);
    }

    public function test_el_aviso_no_aparece_en_los_estados_donde_ya_no_cambia_nada(): void
    {
        foreach (ResumenCirugia::ESTADOS_SIN_CONFIRMACION as $estado) {
            app(CambiarEstadoCirugiaService::class)->cambiar($this->cirugia, $estado);

            $this->detalle()->assertDontSee(self::AVISO);
        }
    }

    public function test_si_voy_a_estar_deja_un_solo_estado_vigente_y_el_historial_completo(): void
    {
        $antes = $this->estados();
        $anterior = $antes->whereNull('fechaDesasignacionCirugiaEstado')->sole();

        $this->responder('confirmar')
            ->assertRedirect(route('portal.cirugias.show', $this->cirugia))
            ->assertSessionHas('exito');

        $this->assertSame('Asistencia de paciente confirmada', $this->vigente());
        $this->assertCount($antes->count() + 1, $this->estados());
        $this->assertNotNull($anterior->fresh()->fechaDesasignacionCirugiaEstado);

        $this->detalle()->assertDontSee(self::AVISO)->assertSee('Confirmaste tu asistencia');
    }

    public function test_necesito_reprogramar_cambia_el_estado_pero_no_libera_el_quirofano(): void
    {
        $this->responder('reprogramar')->assertSessionHas('info');

        $this->assertSame('A reprogramar', $this->vigente());
        $this->assertSame(1, CirugiaQuirofano::where('idCirugia', $this->cirugia->idCirugia)
            ->whereNull('fechaHoraDesasignacion')->count(), 'El quirófano lo libera el gestor al reprogramar, no el paciente.');

        $this->detalle()->assertDontSee(self::AVISO)->assertSee('Tu cirugía está para reprogramar');
    }

    /**
     * La respuesta llega cuando la cirugia es inminente, que es cuando el
     * semaforo del staff dice «En riesgo» o «Listo»: el estado va aparte para
     * que el gestor lo vea igual.
     */
    public function test_el_gestor_ve_la_respuesta_en_el_expediente_y_en_la_agenda(): void
    {
        $this->responder('reprogramar');

        $gestor = Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();

        $this->actingAs($gestor, 'web')
            ->get("/cirugias/{$this->cirugia->idCirugia}")
            ->assertOk()
            ->assertSee('A reprogramar');

        $this->actingAs($gestor, 'web')
            ->get('/agenda/'.$this->cirugia->fechaHoraCirugia->toDateString())
            ->assertOk()
            ->assertSee('A reprogramar');
    }

    public function test_un_segundo_envio_no_abre_otro_estado(): void
    {
        $this->responder('confirmar');
        $filas = $this->estados()->count();

        $this->responder('reprogramar')->assertSessionHas('aviso');

        $this->assertCount($filas, $this->estados());
        $this->assertSame('Asistencia de paciente confirmada', $this->vigente());
    }

    public function test_fuera_de_la_ventana_no_se_puede_responder(): void
    {
        foreach ([72, -2] as $horas) {
            $this->dentroDeHoras($horas);
            $antes = $this->estados()->count();

            $this->responder('confirmar')->assertSessionHas('aviso');

            $this->assertCount($antes, $this->estados());
        }
    }

    public function test_una_respuesta_que_no_es_ninguna_de_las_dos_se_rechaza(): void
    {
        $antes = $this->estados()->count();

        $this->responder('quizas')->assertSessionHasErrors('respuesta');

        $this->assertCount($antes, $this->estados());
    }

    public function test_no_se_puede_responder_por_la_cirugia_de_otro(): void
    {
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();
        $antes = CirugiaEstado::where('idCirugia', $ajena->idCirugia)->count();

        $this->responder('confirmar', $ajena)->assertNotFound();

        $this->assertSame($antes, CirugiaEstado::where('idCirugia', $ajena->idCirugia)->count());
    }
}
