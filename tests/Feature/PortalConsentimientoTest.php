<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Cirugia;
use App\Models\ConfigConsentimiento;
use App\Models\ConsentimientoPaciente;
use App\Models\Persona;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pestaña Consentimiento del portal del paciente:
 * - En pantalla solo el recuadro "Mi firma"; el documento va en una ventana con
 *   la casilla destildada y la doble confirmación.
 * - Firmar guarda fecha, hash, IP y navegador sobre el texto que se leyó.
 * - Sin casilla, con el documento cambiado o ya firmado, no se firma.
 * - Cirugía anterior: solo lectura. Cirugía ajena: 404.
 */
class PortalConsentimientoTest extends TestCase
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

        $this->cirugia = Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->where('fechaHoraCirugia', '>', today()->endOfDay())
            ->firstOrFail();

        // Arranca sin firmar, sea cual sea el estado que sembró el seeder.
        ConsentimientoPaciente::where('idCirugia', $this->cirugia->idCirugia)
            ->update(['fechaFirmaConsentimiento' => null]);
    }

    public function test_la_pestana_muestra_mi_firma_y_el_documento_en_una_ventana_con_la_casilla_destildada(): void
    {
        $response = $this->pestana()->assertOk()
            ->assertSee('Mi firma')
            ->assertSee('Tocá acá para leer y firmar')
            ->assertSee('id="dialogo-consentimiento"', false)
            ->assertSee($this->maria->nombre_completo)
            ->assertSee('Leí el documento y comprendí')
            ->assertSee('data-confirmar="', false)
            ->assertSee('value="'.hash('sha256', $this->texto()).'"', false);

        // La casilla no viene tildada y el botón arranca deshabilitado.
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="leido"[^>]*checked/s', $response->getContent());
        $this->assertMatchesRegularExpression('/disabled[^>]*data-firmar-consentimiento|data-firmar-consentimiento[^>]*disabled/s', $response->getContent());
    }

    public function test_firmar_guarda_fecha_hash_ip_y_navegador_sobre_el_texto_leido(): void
    {
        $texto = $this->texto();

        $this->firmar(['hash' => hash('sha256', $texto)], ['User-Agent' => 'NavegadorDePrueba/1.0'])
            ->assertRedirect($this->url())
            ->assertSessionHas('exito');

        $firmado = ConsentimientoPaciente::where('idCirugia', $this->cirugia->idCirugia)->sole();
        $this->assertNotNull($firmado->fechaFirmaConsentimiento);
        $this->assertSame($texto, $firmado->textoRenderizadoConsentimiento);
        $this->assertSame(hash('sha256', $texto), $firmado->hashConsentimiento);
        $this->assertSame('127.0.0.1', $firmado->ipFirmaConsentimiento);
        $this->assertSame('NavegadorDePrueba/1.0', $firmado->userAgentFirmaConsentimiento);
        $this->assertStringContainsString($this->maria->documento, $firmado->firmaConsentimiento);

        $this->pestana()->assertSee('Firmaste el consentimiento informado')
            ->assertSee('Ver el documento firmado')
            ->assertDontSee('name="leido"', false);

        $this->get($this->url('resumen'))->assertOk()->assertSee('Firmado');
    }

    public function test_sin_marcar_la_casilla_no_se_firma(): void
    {
        $this->firmar(['leido' => null, 'hash' => hash('sha256', $this->texto())])
            ->assertSessionHasErrors('leido');

        $this->assertNull($this->consentimiento()->fechaFirmaConsentimiento);
    }

    public function test_si_el_documento_cambio_mientras_lo_leia_no_se_firma(): void
    {
        $this->firmar(['hash' => hash('sha256', 'otro texto')])
            ->assertRedirect($this->url())
            ->assertSessionHas('aviso');

        $this->assertNull($this->consentimiento()->fechaFirmaConsentimiento);
    }

    public function test_un_consentimiento_ya_firmado_no_se_vuelve_a_firmar(): void
    {
        $fecha = now()->subDay()->startOfMinute();
        $this->consentimiento()->update(['fechaFirmaConsentimiento' => $fecha]);

        $this->firmar(['hash' => hash('sha256', $this->texto())])->assertSessionHas('aviso');

        $this->assertTrue($fecha->equalTo($this->consentimiento()->fechaFirmaConsentimiento));
    }

    public function test_sin_documento_generado_se_firma_la_plantilla_vigente_con_los_datos_del_paciente(): void
    {
        ConsentimientoPaciente::where('idCirugia', $this->cirugia->idCirugia)->delete();
        $vigente = ConfigConsentimiento::vigentePara($this->cirugia->idTipoCirugia);
        $this->assertNotNull($vigente);

        $texto = $this->texto();
        $this->assertStringContainsString($this->maria->documento, $texto);

        $this->firmar(['hash' => hash('sha256', $texto)])->assertSessionHas('exito');

        $creado = ConsentimientoPaciente::where('idCirugia', $this->cirugia->idCirugia)->sole();
        $this->assertSame($vigente->idConfigConsentimiento, $creado->idConfigConsentimiento);
        $this->assertSame($texto, $creado->textoRenderizadoConsentimiento);
        $this->assertNotNull($creado->fechaFirmaConsentimiento);
    }

    public function test_sin_plantilla_ni_documento_avisa_y_no_ofrece_firmar(): void
    {
        ConsentimientoPaciente::where('idCirugia', $this->cirugia->idCirugia)->delete();
        ConfigConsentimiento::where('idTipoCirugia', $this->cirugia->idTipoCirugia)
            ->update(['fechaFinConfigConsentimiento' => now()]);

        $this->pestana()->assertOk()
            ->assertSee('Tu equipo todavía no cargó el consentimiento')
            ->assertDontSee('id="dialogo-consentimiento"', false);

        $this->firmar(['hash' => str_repeat('a', 64)])->assertSessionHas('error');
        $this->assertSame(0, ConsentimientoPaciente::where('idCirugia', $this->cirugia->idCirugia)->count());
    }

    public function test_una_cirugia_anterior_es_de_solo_lectura(): void
    {
        $this->cirugia->update(['fechaHoraCirugia' => now()->subDays(3)]);

        $this->pestana()->assertOk()
            ->assertSee('Esta cirugía ya pasó')
            ->assertDontSee('name="leido"', false);

        $this->firmar(['hash' => hash('sha256', $this->consentimiento()->textoRenderizadoConsentimiento)])
            ->assertSessionHas('aviso');

        $this->assertNull($this->consentimiento()->fechaFirmaConsentimiento);
    }

    public function test_un_paciente_no_puede_ver_ni_firmar_el_consentimiento_de_una_cirugia_ajena(): void
    {
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();

        $this->get("/portal/cirugias/{$ajena->idCirugia}/consentimiento")->assertNotFound();
        $this->post("/portal/cirugias/{$ajena->idCirugia}/consentimiento", [
            'leido' => '1',
            'hash' => str_repeat('a', 64),
        ])->assertNotFound();
    }

    private function url(string $seccion = 'consentimiento'): string
    {
        return "/portal/cirugias/{$this->cirugia->idCirugia}/{$seccion}";
    }

    private function pestana(): TestResponse
    {
        return $this->get($this->url());
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $cabeceras
     */
    private function firmar(array $datos, array $cabeceras = []): TestResponse
    {
        return $this->withHeaders($cabeceras)
            ->from($this->url())
            ->post($this->url(), array_filter($datos + ['leido' => '1'], fn ($v) => $v !== null));
    }

    private function consentimiento(): ConsentimientoPaciente
    {
        return ConsentimientoPaciente::where('idCirugia', $this->cirugia->idCirugia)->sole();
    }

    /** El texto que la pestaña le muestra al paciente. */
    private function texto(): string
    {
        $html = $this->pestana()->getContent();
        preg_match('/<div class="whitespace-pre-wrap[^"]*">(.*?)<\/div>/s', $html, $m);

        return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5);
    }
}
