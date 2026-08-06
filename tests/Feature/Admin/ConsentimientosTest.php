<?php

namespace Tests\Feature\Admin;

use App\Models\Auditoria;
use App\Models\ConfigConsentimiento;
use App\Models\ConsentimientoPaciente;
use App\Models\TipoCirugia;
use App\Models\Usuario;
use App\Support\Consentimiento;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentimientosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function admin(): Usuario
    {
        return Usuario::where('nombreUsuario', 'admin')->firstOrFail();
    }

    private function tipo(string $nombre = 'Tiroidectomía'): TipoCirugia
    {
        return TipoCirugia::where('nombreTipoCirugia', $nombre)->firstOrFail();
    }

    private function texto(string $extra = ''): string
    {
        return 'Yo, {{paciente}}, DNI {{dni}}, autorizo al profesional {{cirujano}} a realizarme '
            ."el procedimiento {{procedimiento}}, del que me explicaron riesgos y alternativas. {$extra}";
    }

    // --- Publicación y versionado ---

    public function test_publica_la_primera_version_de_un_tipo_sin_plantilla(): void
    {
        $tipo = $this->tipo();

        $this->actingAs($this->admin())
            ->post(route('admin.consentimientos.store', $tipo), [
                'textoConfigConsentimiento' => $this->texto(),
            ])
            ->assertRedirect(route('admin.consentimientos.show', $tipo))
            ->assertSessionHas('exito');

        $version = ConfigConsentimiento::where('idTipoCirugia', $tipo->idTipoCirugia)->firstOrFail();

        $this->assertNotNull($version->fechaInicioConfigConsentimiento);
        $this->assertNull($version->fechaFinConfigConsentimiento);
    }

    /**
     * Publicar no pisa: cierra la vigente y abre otra, para poder decir qué
     * texto estaba en vigencia en una fecha dada.
     */
    public function test_publicar_cierra_la_version_anterior_en_vez_de_pisarla(): void
    {
        $tipo = $this->tipo();

        $this->actingAs($this->admin())->post(route('admin.consentimientos.store', $tipo), [
            'textoConfigConsentimiento' => $this->texto('Primera redacción.'),
        ]);
        $primera = ConfigConsentimiento::where('idTipoCirugia', $tipo->idTipoCirugia)->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.consentimientos.store', $tipo), [
            'textoConfigConsentimiento' => $this->texto('Segunda redacción.'),
        ]);

        $versiones = ConfigConsentimiento::where('idTipoCirugia', $tipo->idTipoCirugia)->get();

        $this->assertCount(2, $versiones);
        $this->assertNotNull($primera->fresh()->fechaFinConfigConsentimiento, 'la primera debería quedar cerrada');
        $this->assertCount(1, $versiones->fresh()->whereNull('fechaFinConfigConsentimiento'));
        $this->assertStringContainsString('Primera redacción.', $primera->fresh()->textoConfigConsentimiento);
    }

    public function test_retirar_deja_al_tipo_sin_plantilla_vigente(): void
    {
        $tipo = $this->tipo();

        $this->actingAs($this->admin())->post(route('admin.consentimientos.store', $tipo), [
            'textoConfigConsentimiento' => $this->texto(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.consentimientos.destroy', $tipo))
            ->assertSessionHas('exito');

        $this->assertSame(0, ConfigConsentimiento::where('idTipoCirugia', $tipo->idTipoCirugia)
            ->whereNull('fechaFinConfigConsentimiento')->count());

        // Pero la versión sigue en el historial.
        $this->assertSame(1, ConfigConsentimiento::where('idTipoCirugia', $tipo->idTipoCirugia)->count());
    }

    // --- Marcadores ---

    public function test_rechaza_marcadores_que_el_sistema_no_sabe_completar(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.consentimientos.store', $this->tipo()), [
                'textoConfigConsentimiento' => 'Yo, {{pacientes}}, DNI {{ dni }}, autorizo el procedimiento '
                    .'descripto y declaro haber comprendido la información recibida.',
            ])
            ->assertSessionHasErrors('textoConfigConsentimiento');

        $this->assertSame(0, ConfigConsentimiento::count());
    }

    public function test_el_mensaje_de_error_nombra_el_marcador_mal_escrito(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.consentimientos.store', $this->tipo()), [
                'textoConfigConsentimiento' => 'Yo, {{pacientes}}, autorizo el procedimiento descripto '
                    .'y declaro haber comprendido toda la información recibida al respecto.',
            ])
            ->assertSessionHasErrors('textoConfigConsentimiento');

        $mensaje = session('errors')->first('textoConfigConsentimiento');

        // Tiene que decir cuál está mal y cuáles valen, o no se puede corregir.
        $this->assertStringContainsString('{{pacientes}}', $mensaje);
        $this->assertStringContainsString('{{paciente}}', $mensaje);
    }

    public function test_los_marcadores_validos_pasan(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.consentimientos.store', $this->tipo()), [
                'textoConfigConsentimiento' => $this->texto(),
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_la_vista_previa_resuelve_los_marcadores(): void
    {
        $this->actingAs($this->admin())->post(route('admin.consentimientos.store', $this->tipo()), [
            'textoConfigConsentimiento' => $this->texto(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.consentimientos.create', $this->tipo()))
            ->assertOk()
            // Los datos de ejemplo aparecen resueltos…
            ->assertSee('Pérez, Juan Carlos')
            // …y el listado de marcadores sigue mostrando el crudo.
            ->assertSee('{{paciente}}');
    }

    // --- Corregir solo lo que no se firmó ---

    public function test_una_version_sin_firmas_se_corrige(): void
    {
        $tipo = $this->tipo();
        $this->actingAs($this->admin())->post(route('admin.consentimientos.store', $tipo), [
            'textoConfigConsentimiento' => $this->texto(),
        ]);
        $version = ConfigConsentimiento::where('idTipoCirugia', $tipo->idTipoCirugia)->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.consentimientos.update', [$tipo, $version]), [
                'textoConfigConsentimiento' => $this->texto('Con una aclaración extra.'),
            ])
            ->assertSessionHas('exito');

        $this->assertStringContainsString('aclaración extra', $version->fresh()->textoConfigConsentimiento);

        // Corregir no crea una versión nueva.
        $this->assertSame(1, ConfigConsentimiento::where('idTipoCirugia', $tipo->idTipoCirugia)->count());
    }

    /**
     * Con firmas encima, corregir haría que la auditoría diga que un
     * consentimiento salió de un texto que hoy es otro.
     */
    public function test_una_version_ya_firmada_no_se_corrige(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);

        $firmado = ConsentimientoPaciente::with('configConsentimiento')->firstOrFail();
        $version = $firmado->configConsentimiento;
        $tipo = $version->tipoCirugia;

        $this->actingAs($this->admin())
            ->get(route('admin.consentimientos.edit', [$tipo, $version]))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->put(route('admin.consentimientos.update', [$tipo, $version]), [
                'textoConfigConsentimiento' => $this->texto('Intento de cambio.'),
            ])
            ->assertForbidden();

        $this->assertStringNotContainsString('Intento de cambio', $version->fresh()->textoConfigConsentimiento);
    }

    /**
     * El snapshot de ConsentimientoPaciente es la razón por la que publicar una
     * versión nueva no toca a nadie que ya firmó.
     */
    public function test_publicar_una_version_no_altera_lo_ya_firmado(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);

        $firmado = ConsentimientoPaciente::with('configConsentimiento.tipoCirugia')->firstOrFail();
        $textoOriginal = $firmado->textoRenderizadoConsentimiento;
        $hashOriginal = $firmado->hashConsentimiento;

        $this->actingAs($this->admin())->post(
            route('admin.consentimientos.store', $firmado->configConsentimiento->tipoCirugia),
            ['textoConfigConsentimiento' => $this->texto('Redacción completamente distinta.')],
        );

        $firmado->refresh();

        $this->assertSame($textoOriginal, $firmado->textoRenderizadoConsentimiento);
        $this->assertSame($hashOriginal, $firmado->hashConsentimiento);
    }

    // --- Pantallas y acceso ---

    public function test_el_listado_avisa_que_tipos_no_tienen_plantilla(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.consentimientos.index'))
            ->assertOk()
            ->assertSee('Sin plantilla')
            ->assertSee('Tiroidectomía');
    }

    public function test_el_historial_muestra_las_versiones_y_cuantos_firmaron(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);

        $version = ConsentimientoPaciente::with('configConsentimiento')->firstOrFail()->configConsentimiento;

        $this->actingAs($this->admin())
            ->get(route('admin.consentimientos.show', $version->tipoCirugia))
            ->assertOk()
            ->assertSee('Versión vigente')
            ->assertSee('Firmados con esta versión');
    }

    /**
     * El listado tiene una fila por tipo de cirugía activo: crece con el
     * catálogo, así que pagina.
     */
    public function test_el_listado_de_procedimientos_pagina(): void
    {
        foreach (range(1, 30) as $n) {
            TipoCirugia::create([
                'nombreTipoCirugia' => 'Procedimiento de prueba '.str_pad($n, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->actingAs($this->admin())
            ->get(route('admin.consentimientos.index'))
            ->assertOk()
            ->assertSee('Procedimiento de prueba 01')
            ->assertDontSee('Procedimiento de prueba 30')
            ->assertSee('page=2');
    }

    /**
     * El historial suma una versión por publicación y cada tarjeta trae el
     * texto entero: se pagina de a pocas, con la vigente siempre primera.
     */
    public function test_el_historial_de_versiones_pagina_con_la_vigente_primero(): void
    {
        $tipo = $this->tipo();

        foreach (range(1, 7) as $n) {
            $this->actingAs($this->admin())->post(route('admin.consentimientos.store', $tipo), [
                'textoConfigConsentimiento' => $this->texto('Redacción número '.$n.'.'),
            ]);
        }

        $this->actingAs($this->admin())
            ->get(route('admin.consentimientos.show', $tipo))
            ->assertOk()
            ->assertSee('Versión vigente')
            ->assertSee('Redacción número 7.')
            ->assertDontSee('Redacción número 1.')
            ->assertSee('page=2');
    }

    public function test_la_publicacion_queda_auditada(): void
    {
        $tipo = $this->tipo();

        $this->actingAs($this->admin())->post(route('admin.consentimientos.store', $tipo), [
            'textoConfigConsentimiento' => $this->texto(),
        ]);

        $registro = Auditoria::orderByDesc('idAuditoria')->firstOrFail();

        $this->assertSame('ConfigConsentimiento', $registro->tablaAuditoria);
        $this->assertSame('Consentimiento de «Tiroidectomía»', $registro->descripcionAuditoria);
    }

    public function test_la_seccion_esta_cerrada_a_los_demas_roles(): void
    {
        $this->seed(DemoSeeder::class);

        $cirujano = Usuario::where('nombreUsuario', 'perez')->firstOrFail();

        $this->actingAs($cirujano)->get(route('admin.consentimientos.index'))->assertForbidden();
        $this->actingAs($cirujano)
            ->post(route('admin.consentimientos.store', $this->tipo()), [
                'textoConfigConsentimiento' => $this->texto(),
            ])
            ->assertForbidden();
    }

    /**
     * El seeder y la pantalla tienen que resolver los marcadores igual: por eso
     * la lógica se movió a App\Support\Consentimiento.
     */
    public function test_el_seeder_y_la_pantalla_comparten_el_resolvedor(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);

        $firmado = ConsentimientoPaciente::with(['configConsentimiento', 'cirugia.paciente', 'cirugia.tipoCirugia', 'cirugia.cirujano.persona'])
            ->whereHas('cirugia', fn ($q) => $q->whereNotNull('fechaHoraCirugia'))
            ->firstOrFail();

        $this->assertSame(
            $firmado->textoRenderizadoConsentimiento,
            Consentimiento::paraCirugia(
                $firmado->configConsentimiento->textoConfigConsentimiento,
                $firmado->cirugia,
            ),
        );

        // Y no queda ningún marcador sin resolver en lo que firma el paciente.
        $this->assertSame([], Consentimiento::desconocidos($firmado->textoRenderizadoConsentimiento));
        $this->assertStringNotContainsString('{{', $firmado->textoRenderizadoConsentimiento);
    }
}
