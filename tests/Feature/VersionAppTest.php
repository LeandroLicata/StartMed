<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\VersionApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_formatea_commit_configurado_como_revision_corta(): void
    {
        config(['app.version' => null, 'app.commit' => 'abcdef1234567890']);

        $this->assertSame('rev. abcdef1', VersionApp::actual());
    }

    public function test_formatea_version_sola_y_combinada_con_commit(): void
    {
        config(['app.version' => 'v1.2.0', 'app.commit' => '']);
        // En entorno local sin commit configurado igual toma el HEAD de .git si existe.
        $actual = VersionApp::actual();
        $this->assertStringStartsWith('v1.2.0', (string) $actual);

        config(['app.version' => 'v1.2.0', 'app.commit' => '9876543210fedcba']);
        $this->assertSame('v1.2.0 (9876543)', VersionApp::actual());
    }

    public function test_cuando_no_hay_commit_configurado_lee_el_head_del_repositorio(): void
    {
        config(['app.version' => null, 'app.commit' => null]);

        $actual = VersionApp::actual();

        $this->assertNotNull($actual);
        $this->assertMatchesRegularExpression('/^rev\. [0-9a-f]{7}$/i', $actual);
    }

    public function test_el_sidebar_muestra_la_version_en_las_pantallas_autenticadas(): void
    {
        config(['app.version' => 'v2.0', 'app.commit' => 'c524f1d000000000']);

        $admin = Usuario::where('nombreUsuario', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.inicio'))
            ->assertOk()
            ->assertSee('v2.0 (c524f1d)');
    }
}
