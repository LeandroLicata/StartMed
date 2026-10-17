<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Persona;
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
}