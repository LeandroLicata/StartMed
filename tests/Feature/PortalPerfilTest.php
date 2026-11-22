<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Persona;
use App\Models\PlanObraSocial;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_el_paciente_no_puede_agregar_ni_dar_de_baja_obras_sociales_desde_el_portal(): void
    {
        $cobertura = $this->maria->planObraSociales()->firstOrFail();

        $this->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee('Solo podés elegir tu obra social predeterminada')
            ->assertDontSee('Agregar una obra social')
            ->assertDontSee('Dar de baja');

        $this->post('/portal/perfil/obras-sociales', [
            'idPlan' => $cobertura->idPlan,
            'nroBeneficiario' => '123456',
        ])->assertNotFound();

        $this->patch("/portal/perfil/obras-sociales/{$cobertura->idPlanObraSocial}/baja")
            ->assertNotFound();

        $this->assertNull($cobertura->fresh()->fechaFinPlanObraSocial);
    }

    public function test_un_id_de_cobertura_que_no_es_numero_da_404_y_no_500(): void
    {
        $this->patch('/portal/perfil/obras-sociales/abc/predeterminada')->assertNotFound();
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

    public function test_una_predeterminada_cuya_baja_programada_ya_paso_no_cuenta(): void
    {
        // El flag queda puesto, pero la cobertura ya no es vigente.
        $osde = $this->maria->planObraSociales()->firstOrFail();
        $osde->update(['fechaFinPlanObraSocial' => now()->subDay()]);

        $this->assertFalse(PlanObraSocial::tienePredeterminadaVigente($this->maria->idPersona));

        $nueva = PlanObraSocial::agregar($this->maria->idPersona, $osde->idPlan, '123456');
        $this->assertTrue($nueva->esPredeterminadaPlanObraSocial);
    }

    // --- Mis datos y datos de contacto ------------------------------------

    public function test_el_perfil_muestra_los_datos_de_solo_lectura(): void
    {
        $this->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee('28456789')
            ->assertSee('14/03/1976')
            ->assertSee('A+');
    }

    public function test_el_paciente_actualiza_telefonos_y_donacion(): void
    {
        $this->patch(route('portal.perfil.datos'), [
            'telefono_codigo' => '261',
            'telefono_numero' => '4251234',
            'contacto_celular_codigo' => '261',
            'contacto_celular_numero' => '5551234',
            'no_acepta_donacion_sanguinea' => '1',
        ])
            ->assertRedirect(route('portal.perfil'))
            ->assertSessionHas('exito');

        $maria = $this->maria->fresh();

        $this->assertSame('4251234', $maria->telefono_numero);
        $this->assertSame('5551234', $maria->contacto_celular_numero);
        $this->assertTrue($maria->no_acepta_donacion_sanguinea);
    }

    public function test_destildar_la_donacion_la_guarda_en_falso(): void
    {
        $this->maria->update(['no_acepta_donacion_sanguinea' => true]);

        // Destildada llega como 0: <x-checkbox> manda un hidden antes del checkbox.
        $this->patch(route('portal.perfil.datos'), [
            'contacto_celular_numero' => '5551234',
            'no_acepta_donacion_sanguinea' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($this->maria->fresh()->no_acepta_donacion_sanguinea);
    }

    public function test_un_patch_sin_la_casilla_no_borra_el_rechazo_de_transfusiones(): void
    {
        $this->maria->update(['no_acepta_donacion_sanguinea' => true]);

        $this->patch(route('portal.perfil.datos'), [
            'contacto_celular_numero' => '5551234',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->maria->fresh()->no_acepta_donacion_sanguinea);
    }

    public function test_el_formulario_manda_la_casilla_aunque_este_destildada(): void
    {
        $this->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee('<input type="hidden" name="no_acepta_donacion_sanguinea" value="0">', false);
    }

    public function test_un_patch_con_campos_de_solo_lectura_no_los_cambia(): void
    {
        $antes = $this->maria->fresh();

        $this->patch(route('portal.perfil.datos'), [
            'contacto_celular_numero' => '5551234',
            // Todo esto es "trampa": no está en las reglas del request.
            'documento' => '99999999',
            'nombres' => 'Otra',
            'fecha_nacimiento' => '2000-01-01',
            'grupo_sanguineo_id' => null,
            'contacto_email_direccion' => 'trucho@example.com',
        ])->assertRedirect(route('portal.perfil'));

        $despues = $this->maria->fresh();

        // Lo editable sí se guardó...
        $this->assertSame('5551234', $despues->contacto_celular_numero);

        // ...pero lo de solo lectura quedó igual.
        $this->assertSame($antes->documento, $despues->documento);
        $this->assertSame($antes->nombres, $despues->nombres);
        $this->assertEquals($antes->fecha_nacimiento, $despues->fecha_nacimiento);
        $this->assertSame($antes->grupo_sanguineo_id, $despues->grupo_sanguineo_id);
        $this->assertSame($antes->contacto_email_direccion, $despues->contacto_email_direccion);
    }

    public function test_un_telefono_con_letras_no_se_guarda(): void
    {
        $this->patch(route('portal.perfil.datos'), [
            'contacto_celular_numero' => 'abc123',
        ])->assertSessionHasErrors('contacto_celular_numero');

        $this->assertNull($this->maria->fresh()->contacto_celular_numero);
    }

    // --- Cambio de email ---------------------------------------------------

    public function test_pedir_el_mismo_email_con_otras_mayusculas_se_rechaza(): void
    {
        Mail::fake();

        $this->post(route('portal.perfil.email'), [
            'email_nuevo' => strtoupper($this->maria->contacto_email_direccion),
        ])->assertSessionHasErrors(['email_nuevo' => 'Ese ya es tu email actual.']);

        Mail::assertNothingSent();
    }
}
