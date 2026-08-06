<?php

namespace Tests\Feature;

use App\Console\Commands\LimpiarDocumentosHuerfanos;
use App\Models\CirugiaTipoEstudio;
use App\Models\HisopadoSarm;
use App\Support\GestorDocumentalCloudinary;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `documentos:limpiar-huerfanos` habla con la cuenta real de Cloudinary para
 * listar y borrar assets, así que lo que hace ahí no se puede probar sin salir
 * a la red —y esta suite no sale a la red, ver GestorDocumentalTest::
 * test_la_suite_nunca_corre_contra_cloudinary(). Lo que cubren estos tests es
 * lo que sí es puro: el parser de punteros y la consulta de qué sigue vigente
 * en la base, más el camino en el que el comando no tiene nada para hacer
 * porque el gestor activo es el local.
 */
class LimpiarDocumentosHuerfanosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    public function test_sin_credenciales_de_cloudinary_el_comando_no_hace_nada(): void
    {
        // GestorDocumentalLocal es lo que resuelve el contenedor en toda la
        // suite (CLOUDINARY_URL viene vacía en phpunit.xml); el comando tiene
        // que reconocerlo y no intentar hablar con Cloudinary.
        $this->artisan('documentos:limpiar-huerfanos')
            ->expectsOutputToContain('disco local')
            ->assertExitCode(0);
    }

    public function test_referenciade_reconoce_un_puntero_de_cloudinary(): void
    {
        $referencia = GestorDocumentalCloudinary::referenciaDe('cloudinary:image:startmed/estudios/7/abc:pdf');

        $this->assertSame(['resource_type' => 'image', 'public_id' => 'startmed/estudios/7/abc'], $referencia);
    }

    public function test_referenciade_devuelve_null_para_un_puntero_que_no_es_de_cloudinary(): void
    {
        $this->assertNull(GestorDocumentalCloudinary::referenciaDe('local:estudios/demo.png'));
        $this->assertNull(GestorDocumentalCloudinary::referenciaDe('algo-sin-el-formato-esperado'));
    }

    public function test_punteros_referenciados_incluye_estudios_y_excluye_el_disco_local(): void
    {
        // DemoSeeder ya deja varios CirugiaTipoEstudio con archivo: alcanza con
        // reusar dos y ponerles punteros de distinto origen a propósito.
        [$conCloudinary, $conLocal] = CirugiaTipoEstudio::whereNotNull('urlArchivoCirugiaTipoEstudio')
            ->take(2)
            ->get();

        $conCloudinary->update(['urlArchivoCirugiaTipoEstudio' => 'cloudinary:image:startmed/estudios/1/abc:pdf']);
        $conLocal->update(['urlArchivoCirugiaTipoEstudio' => 'local:estudios/no-deberia-aparecer.pdf']);

        $referenciados = (new LimpiarDocumentosHuerfanos)->punterosReferenciados();

        // DemoSeeder sólo siembra punteros «local:»: si el filtrado funciona,
        // el único que sobrevive a referenciaDe() es el que acabamos de setear.
        // (Collection::contains() no sirve acá: con valores todo `true`,
        // compara la clave de forma laxa y cualquier string no vacío == true.)
        $this->assertSame(['image:startmed/estudios/1/abc' => true], $referenciados->all());
    }

    public function test_punteros_referenciados_incluye_el_adjunto_del_hisopado(): void
    {
        $hisopado = HisopadoSarm::firstOrFail();
        $hisopado->update(['urlHisopadoSarm' => 'cloudinary:raw:startmed/hisopados/5/xyz:pdf']);

        $referenciados = (new LimpiarDocumentosHuerfanos)->punterosReferenciados();

        $this->assertSame(['raw:startmed/hisopados/5/xyz' => true], $referenciados->all());
    }
}
