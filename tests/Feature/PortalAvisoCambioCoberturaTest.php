<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Mail\AvisoCambioCobertura;
use App\Models\Cirugia;
use App\Models\Persona;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Aviso al gestor cuando un paciente con cirugias pendientes cambia su obra
 * social predeterminada desde el portal.
 */
class PortalAvisoCambioCoberturaTest extends TestCase
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

        Mail::fake();
        $this->withoutDefer();
    }

    private function predeterminada(): PlanObraSocial
    {
        return $this->maria->planObraSociales()
            ->where('esPredeterminadaPlanObraSocial', true)
            ->firstOrFail();
    }

    /** Le suma a Maria otra cobertura vigente, sin marcarla. */
    private function otraCobertura(): PlanObraSocial
    {
        $idPlanActual = $this->predeterminada()->idPlan;

        $otroPlan = Plan::disponiblesParaPaciente()->keys()
            ->first(fn ($idPlan) => $idPlan != $idPlanActual);

        return PlanObraSocial::agregar($this->maria->idPersona, $otroPlan, '999');
    }

    public function test_cambiar_la_predeterminada_avisa_a_los_gestores(): void
    {
        $otra = $this->otraCobertura();

        $this->patch(route('portal.perfil.predeterminada', $otra->idPlanObraSocial));

        Mail::assertSent(AvisoCambioCobertura::class, fn (AvisoCambioCobertura $mail) => $mail->coberturaAnterior !== null
            && $mail->coberturaNueva !== null
            && count($mail->cirugias) > 0);
    }

    public function test_marcar_la_que_ya_era_predeterminada_no_avisa(): void
    {
        $this->patch(route('portal.perfil.predeterminada', $this->predeterminada()->idPlanObraSocial));

        Mail::assertNothingSent();
    }

    public function test_dar_de_baja_la_predeterminada_avisa(): void
    {
        $this->patch(route('portal.perfil.baja', $this->predeterminada()->idPlanObraSocial));

        Mail::assertSent(AvisoCambioCobertura::class, fn (AvisoCambioCobertura $mail) => $mail->coberturaNueva === null);
    }

    public function test_sin_cirugias_pendientes_no_avisa(): void
    {
        Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->update(['fechaHoraCirugia' => now()->subMonth()]);

        $otra = $this->otraCobertura();

        $this->patch(route('portal.perfil.predeterminada', $otra->idPlanObraSocial));

        Mail::assertNothingSent();
    }
}
