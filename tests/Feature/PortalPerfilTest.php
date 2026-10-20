<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Persona;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perfil del paciente en el portal: obras sociales vigentes e historicas.
 */
class PortalPerfilTest extends TestCase
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

    public function test_el_perfil_muestra_la_obra_social_vigente(): void
    {
        $this->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee('OSDE 410');
    }

    public function test_una_baja_futura_sigue_vigente(): void
    {
        $this->maria->planObraSociales()->update([
            'fechaFinPlanObraSocial' => now()->addMonth(),
        ]);

        $this->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee('Vigente hasta el')
            ->assertDontSee('Dada de baja');
    }

    public function test_una_baja_pasada_aparece_como_anterior(): void
    {
        $this->maria->planObraSociales()->update([
            'fechaFinPlanObraSocial' => now()->subMonth(),

        ]);

        $this->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee('Obras sociales anteriores')
            ->assertSee('Dada de baja el');
    }

    public function test_marcar_predeterminada_desmarca_las_demas(): void
    {
        $original = $this->maria->planObraSociales()->firstOrFail();

        $otra = $original->replicate();
        $otra->esPredeterminadaPlanObraSocial = true;
        $otra->save();

        $this->patch(route('portal.perfil.predeterminada', $original->idPlanObraSocial))
            ->assertRedirect(route('portal.perfil'));

        $this->assertTrue($original->fresh()->esPredeterminadaPlanObraSocial);
        $this->assertFalse($otra->fresh()->esPredeterminadaPlanObraSocial);
    }

    public function test_no_se_puede_marcar_una_cobertura_dada_de_baja(): void
    {
        $cobertura = $this->maria->planObraSociales()->firstOrFail();
        $cobertura->update([
            'fechaFinPlanObraSocial' => now()->subMonth(),
            'esPredeterminadaPlanObraSocial' => false,
        ]);

        $this->patch(route('portal.perfil.predeterminada', $cobertura->idPlanObraSocial))
            ->assertSessionHas('error');

        $this->assertFalse($cobertura->fresh()->esPredeterminadaPlanObraSocial);
    }

    public function test_no_se_puede_marcar_la_cobertura_de_otro_paciente(): void
    {
        $ajena = PlanObraSocial::where('idPersona', '!=', $this->maria->idPersona)->firstOrFail();
        $antes = $ajena->esPredeterminadaPlanObraSocial;

        $this->patch(route('portal.perfil.predeterminada', $ajena->idPlanObraSocial))
            ->assertNotFound();

        $this->assertSame($antes, $ajena->fresh()->esPredeterminadaPlanObraSocial);
    }

    public function test_dar_de_baja_pasa_la_cobertura_a_anteriores(): void
    {
        $cobertura = $this->maria->planObraSociales()->firstOrFail();

        $this->patch(route('portal.perfil.baja', $cobertura->idPlanObraSocial))
            ->assertRedirect(route('portal.perfil'));

        $this->assertNotNull($cobertura->fresh()->fechaFinPlanObraSocial);

        $this->get(route('portal.perfil'))
            ->assertSee('Obras sociales anteriores')
            ->assertSee('No tenés obras sociales vigentes');
    }

    public function test_dar_de_baja_la_predeterminada_la_desmarca_y_avisa(): void
    {
        $cobertura = $this->maria->planObraSociales()->firstOrFail();
        $cobertura->update(['esPredeterminadaPlanObraSocial' => true]);

        $this->patch(route('portal.perfil.baja', $cobertura->idPlanObraSocial))
            ->assertSessionHas('aviso');

        $this->assertFalse($cobertura->fresh()->esPredeterminadaPlanObraSocial);
    }

    public function test_no_se_puede_dar_de_baja_una_cobertura_ya_dada_de_baja(): void
    {
        $cobertura = $this->maria->planObraSociales()->firstOrFail();
        $cobertura->update(['fechaFinPlanObraSocial' => now()->subMonth()]);

        $this->patch(route('portal.perfil.baja', $cobertura->idPlanObraSocial))
            ->assertSessionHas('error');
    }

    public function test_no_se_puede_dar_de_baja_la_cobertura_de_otro_paciente(): void
    {
        $ajena = PlanObraSocial::where('idPersona', '!=', $this->maria->idPersona)->firstOrFail();
        $antes = $ajena->fechaFinPlanObraSocial;

        $this->patch(route('portal.perfil.baja', $ajena->idPlanObraSocial))
            ->assertNotFound();

        $this->assertEquals($antes, $ajena->fresh()->fechaFinPlanObraSocial);
    }

    public function test_un_id_de_cobertura_que_no_es_numero_da_404_y_no_500(): void
    {
        $this->patch('/portal/perfil/obras-sociales/abc/predeterminada')->assertNotFound();
        $this->patch('/portal/perfil/obras-sociales/abc/baja')->assertNotFound();
    }

    public function test_agregar_suma_una_obra_social_vigente(): void
    {
        $osde = $this->maria->planObraSociales()->firstOrFail();
        $osde->update(['fechaFinPlanObraSocial' => now()->subMonth()]);

        $this->post(route('portal.perfil.agregar'), [
            'idPlan' => $osde->idPlan,
            'nroBeneficiario' => '123456',
        ])->assertRedirect(route('portal.perfil'));

        $this->assertDatabaseHas('PlanObraSocial', [
            'idPersona' => $this->maria->idPersona,
            'idPlan' => $osde->idPlan,
            'nroBeneficiaroPlanObraSocial' => '123456',
            'fechaFinPlanObraSocial' => null,
        ]);
    }

    public function test_no_se_puede_agregar_un_plan_que_ya_esta_vigente(): void
    {
        $osde = $this->maria->planObraSociales()->firstOrFail();
        $antes = $this->maria->planObraSociales()->count();

        $this->post(route('portal.perfil.agregar'), [
            'idPlan' => $osde->idPlan,
            'nroBeneficiario' => '123456',
        ])->assertSessionHas('error');

        $this->assertSame($antes, $this->maria->planObraSociales()->count());
    }

    public function test_no_se_puede_agregar_el_plan_particular(): void
    {
        $particular = Plan::where('es_sin_cobertura', true)->firstOrFail();

        $this->post(route('portal.perfil.agregar'), [
            'idPlan' => $particular->idPlan,
            'nroBeneficiario' => '123456',
        ])->assertSessionHasErrors('idPlan');
    }

    public function test_marcar_la_que_ya_tenia_el_flag_no_deja_al_paciente_sin_predeterminada(): void
    {
        // Dos marcadas a la vez (datos viejos): se elige una que ya estaba en true.
        $original = $this->maria->planObraSociales()->firstOrFail();
        $original->update(['esPredeterminadaPlanObraSocial' => true]);
        $otra = $original->replicate();
        $otra->save();

        $this->patch(route('portal.perfil.predeterminada', $original->idPlanObraSocial));

        $this->assertTrue($original->fresh()->esPredeterminadaPlanObraSocial);
        $this->assertFalse($otra->fresh()->esPredeterminadaPlanObraSocial);
    }

    public function test_la_primera_obra_social_que_se_agrega_queda_como_predeterminada(): void
    {
        $osde = $this->maria->planObraSociales()->firstOrFail();

        // Dio de baja la predeterminada: el portal le pide elegir otra o agregar una.
        $this->patch(route('portal.perfil.baja', $osde->idPlanObraSocial));

        $this->post(route('portal.perfil.agregar'), [
            'idPlan' => $osde->idPlan,
            'nroBeneficiario' => '123456',
        ])->assertSessionHas('exito', 'Agregaste la obra social. Quedó como tu predeterminada.');

        $nueva = $this->maria->planObraSociales()->vigentes()->sole();
        $this->assertTrue($nueva->esPredeterminadaPlanObraSocial);
    }

    public function test_agregar_no_le_cambia_la_predeterminada_a_quien_ya_tiene_una(): void
    {
        $osde = $this->maria->planObraSociales()->firstOrFail();
        $this->assertTrue($osde->esPredeterminadaPlanObraSocial, 'DemoSeeder deja la primera cobertura como predeterminada');

        $otroPlan = Plan::disponiblesParaPaciente()->keys()->first(fn ($id) => $id !== $osde->idPlan);

        $this->post(route('portal.perfil.agregar'), [
            'idPlan' => $otroPlan,
            'nroBeneficiario' => '123456',
        ])->assertSessionHas('exito', 'Agregaste la obra social.');

        $this->assertTrue($osde->fresh()->esPredeterminadaPlanObraSocial);
        $this->assertFalse($this->maria->planObraSociales()->where('idPlan', $otroPlan)->sole()->esPredeterminadaPlanObraSocial);
    }

    public function test_una_predeterminada_cuya_baja_programada_ya_paso_no_cuenta(): void
    {
        // El flag queda puesto, pero la cobertura ya no es vigente.
        $osde = $this->maria->planObraSociales()->firstOrFail();
        $osde->update(['fechaFinPlanObraSocial' => now()->subDay()]);

        $this->assertFalse(PlanObraSocial::tienePredeterminadaVigente($this->maria->idPersona));

        $nueva = PlanObraSocial::agregar($this->maria->idPersona, $osde->idPlan, '123456');
        $this->assertTrue($nueva->esPredeterminadaPlanObraSocial);
    }

    public function test_el_numero_de_afiliado_es_obligatorio(): void
    {
        $this->post(route('portal.perfil.agregar'), [
            'idPlan' => Plan::disponiblesParaPaciente()->keys()->first(),
            'nroBeneficiario' => '',
        ])->assertSessionHasErrors('nroBeneficiario');
    }
}
