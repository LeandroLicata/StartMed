<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CirugiaTipoEstudio;
use App\Models\HisopadoSarm;
use App\Models\Usuario;
use App\Support\DocumentoNoDisponible;
use App\Support\GestorDocumental;
use App\Support\GestorDocumentalCloudinary;
use App\Support\GestorDocumentalLocal;
use Cloudinary\Cloudinary;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Subida y entrega de los resultados de estudios prequirúrgicos.
 *
 * Los tests corren siempre contra `GestorDocumentalLocal` (sin CLOUDINARY_URL el
 * binding de AppServiceProvider cae ahí), así que la suite no sale a la red. Lo
 * que se verifica acá es el contrato que comparten las dos implementaciones: que
 * el archivo se guarde, que lo que quede en la base sea un puntero y no una URL,
 * y que el documento no se alcance sin sesión ni sin rol.
 */
class GestorDocumentalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    private function usuario(string $nombre): Usuario
    {
        return Usuario::where('nombreUsuario', $nombre)->firstOrFail();
    }

    private function estudioPendiente(): CirugiaTipoEstudio
    {
        return CirugiaTipoEstudio::whereNull('urlArchivoCirugiaTipoEstudio')->firstOrFail();
    }

    private function estudioConArchivo(): CirugiaTipoEstudio
    {
        return CirugiaTipoEstudio::whereNotNull('urlArchivoCirugiaTipoEstudio')->firstOrFail();
    }

    private function urlSubida(CirugiaTipoEstudio $estudio): string
    {
        return "/cirugias/{$estudio->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}";
    }

    // --- Guardarraíl --------------------------------------------------------

    public function test_la_suite_nunca_corre_contra_cloudinary(): void
    {
        /*
         * Los tests leen el .env, así que en una máquina con CLOUDINARY_URL
         * configurada cada corrida subía archivos de prueba a la cuenta real. Lo
         * evita `<env name="CLOUDINARY_URL" value=""/>` en phpunit.xml, y esta
         * aserción está para que no se caiga sin que nadie lo note.
         */
        $this->assertInstanceOf(GestorDocumentalLocal::class, app(GestorDocumental::class));
    }

    // --- Subida -------------------------------------------------------------

    public function test_subir_un_resultado_guarda_el_archivo_y_su_puntero(): void
    {
        $estudio = $this->estudioPendiente();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'archivoResultadoEstudio' => UploadedFile::fake()->create('hemograma.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect()
            // El flash tiene que confirmar el archivo puntualmente: un genérico
            // "actualizado" no distingue subir un PDF de sólo cambiar una fecha.
            ->assertSessionHas('exito', 'Archivo subido correctamente al gestor documental.');

        $estudio->refresh();

        $this->assertNotNull($estudio->fechaSubidaCirugiaTipoEstudio);
        $this->assertStringStartsWith('local:estudios/', $estudio->urlArchivoCirugiaTipoEstudio);

        // Lo que se persiste es un puntero, no una URL: la de entrega vence.
        $this->assertStringNotContainsString('http', $estudio->urlArchivoCirugiaTipoEstudio);

        $ruta = substr($estudio->urlArchivoCirugiaTipoEstudio, strlen('local:'));
        Storage::disk('local')->assertExists($ruta);
    }

    public function test_el_puntero_entra_en_la_columna_del_esquema(): void
    {
        $estudio = $this->estudioPendiente();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'archivoResultadoEstudio' => UploadedFile::fake()->create('hemograma.pdf', 12, 'application/pdf'),
            ]);

        // urlArchivoCirugiaTipoEstudio es varchar(255): el puntero tiene que
        // entrar con margen, que es parte de por qué no guardamos la URL firmada.
        $this->assertLessThanOrEqual(255, strlen($estudio->refresh()->urlArchivoCirugiaTipoEstudio));
    }

    public function test_subir_el_resultado_no_borra_la_fecha_esperada_ni_el_resultado(): void
    {
        $estudio = $this->estudioPendiente();
        $estudio->update([
            'fechaEsperadaResultadoCirugiaTipoEstudio' => '2026-09-01',
            'resultadoCirugiaTipoEstudio' => 'Pedido al laboratorio',
        ]);

        // El formulario de subida manda sólo el archivo: los otros dos campos no
        // viajan y no se tienen que tocar.
        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'archivoResultadoEstudio' => UploadedFile::fake()->create('hemograma.pdf', 12, 'application/pdf'),
            ]);

        $estudio->refresh();

        $this->assertSame('Pedido al laboratorio', $estudio->resultadoCirugiaTipoEstudio);
        $this->assertSame('2026-09-01', $estudio->fechaEsperadaResultadoCirugiaTipoEstudio->format('Y-m-d'));
    }

    public function test_editar_el_estudio_sin_adjuntar_nada_no_dice_que_se_subio_un_archivo(): void
    {
        $estudio = $this->estudioPendiente();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'resultadoCirugiaTipoEstudio' => 'Sin particularidades',
            ])
            ->assertSessionHas('exito', 'Estudio actualizado correctamente.');
    }

    public function test_rechaza_un_archivo_de_tipo_no_permitido(): void
    {
        $estudio = $this->estudioPendiente();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'archivoResultadoEstudio' => UploadedFile::fake()->create('script.exe', 12, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('archivoResultadoEstudio');

        $this->assertNull($estudio->refresh()->urlArchivoCirugiaTipoEstudio);
    }

    public function test_rechaza_un_archivo_que_pasa_los_20_mb(): void
    {
        $estudio = $this->estudioPendiente();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'archivoResultadoEstudio' => UploadedFile::fake()->create('resonancia.pdf', 20481, 'application/pdf'),
            ])
            ->assertSessionHasErrors('archivoResultadoEstudio');

        $this->assertNull($estudio->refresh()->urlArchivoCirugiaTipoEstudio);
    }

    public function test_si_el_gestor_falla_el_estudio_no_queda_marcado_como_subido(): void
    {
        $estudio = $this->estudioPendiente();

        $this->instance(GestorDocumental::class, new GestorDocumentalCaido);

        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'archivoResultadoEstudio' => UploadedFile::fake()->create('hemograma.pdf', 12, 'application/pdf'),
            ])
            ->assertSessionHas('error');

        $estudio->refresh();

        // Marcar «Subido» sin archivo detrás era lo que hacía la versión mock.
        $this->assertNull($estudio->fechaSubidaCirugiaTipoEstudio);
        $this->assertNull($estudio->urlArchivoCirugiaTipoEstudio);
    }

    // --- Entrega ------------------------------------------------------------

    public function test_ver_el_resultado_redirige_a_la_url_que_da_el_gestor(): void
    {
        $estudio = $this->estudioConArchivo();

        // Con un stub en vez del gestor real: lo que se verifica acá es el
        // trabajo del controlador (comprobar pertenencia, pedir la URL recién
        // ahora y redirigir), no cómo la firma cada implementación.
        $this->instance(GestorDocumental::class, new GestorDocumentalDeUtileria);

        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$estudio->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}/archivo")
            ->assertRedirect(GestorDocumentalDeUtileria::URL);
    }

    public function test_el_gestor_local_entrega_lo_que_guardo(): void
    {
        $estudio = $this->estudioPendiente();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch($this->urlSubida($estudio), [
                'archivoResultadoEstudio' => UploadedFile::fake()->create('hemograma.pdf', 12, 'application/pdf'),
            ]);

        // Ida y vuelta completo contra GestorDocumentalLocal, que es el que corre
        // en cualquier instalación sin credenciales de Cloudinary.
        $destino = $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$estudio->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}/archivo")
            ->assertRedirect()
            ->headers->get('Location');

        // Fuera de la aplicación y con vencimiento: la URL es la única llave del
        // archivo mientras dura.
        $this->assertStringNotContainsString('/cirugias/', $destino);
        $this->assertStringContainsString('expiration=', $destino);
    }

    public function test_el_estudio_sin_archivo_no_tiene_documento_que_mostrar(): void
    {
        $estudio = $this->estudioPendiente();

        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$estudio->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}/archivo")
            ->assertNotFound();
    }

    public function test_no_se_puede_pedir_el_archivo_de_un_estudio_de_otra_cirugia(): void
    {
        $estudio = $this->estudioConArchivo();
        $otra = Cirugia::where('idCirugia', '!=', $estudio->idCirugia)->firstOrFail();

        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$otra->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}/archivo")
            ->assertNotFound();
    }

    public function test_un_invitado_no_alcanza_el_archivo(): void
    {
        $estudio = $this->estudioConArchivo();

        $this->get("/cirugias/{$estudio->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}/archivo")
            ->assertRedirect('/login');
    }

    public function test_un_puntero_que_no_es_del_gestor_configurado_avisa_en_vez_de_romper(): void
    {
        $estudio = $this->estudioConArchivo();
        $estudio->update(['urlArchivoCirugiaTipoEstudio' => 'cloudinary:image:startmed/estudios/x:pdf']);

        // Base sembrada contra Cloudinary y aplicación corriendo con el gestor
        // local (o al revés): el puntero lleva prefijo justamente para que esto
        // se note como un aviso y no como un archivo equivocado o un 500.
        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$estudio->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}/archivo")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // --- Pantallas ----------------------------------------------------------

    public function test_la_pestana_de_estudios_enlaza_el_resultado_subido(): void
    {
        $estudio = $this->estudioConArchivo();

        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$estudio->idCirugia}?tab=estudios")
            ->assertOk()
            ->assertSee(route('cirugias.estudios.archivo', [$estudio->idCirugia, $estudio->idCirugiaTipoEstudio]), false)
            // El alert() de la maqueta no tiene que quedar en ninguna parte.
            ->assertDontSee('gestor documental externo');
    }

    // --- Hisopado SARM ------------------------------------------------------

    /** Caso con hisopado «Pendiente» en la demo. */
    private function cirugiaConHisopado(): Cirugia
    {
        return Cirugia::whereHas('paciente', fn ($q) => $q->where('apellidos', 'Vidal'))->firstOrFail();
    }

    private function hisopadoDe(Cirugia $cirugia): HisopadoSarm
    {
        return HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->firstOrFail();
    }

    public function test_el_modal_de_edicion_guarda_el_adjunto_del_hisopado(): void
    {
        $cirugia = $this->cirugiaConHisopado();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado", [
                'observacionesHisopadoSarm' => 'Muestra tomada en preadmisión.',
                'archivoHisopadoSarm' => UploadedFile::fake()->create('hisopado.pdf', 8, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('exito', 'Datos del hisopado actualizados y archivo subido correctamente.');

        $hisopado = $this->hisopadoDe($cirugia);

        $this->assertStringStartsWith('local:hisopados/', $hisopado->urlHisopadoSarm);
        $this->assertSame('Muestra tomada en preadmisión.', $hisopado->observacionesHisopadoSarm);
    }

    public function test_registrar_el_resultado_guarda_el_adjunto_y_el_estado(): void
    {
        $cirugia = $this->cirugiaConHisopado();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado/estado", [
                'estado' => 'Negativo',
                'archivoHisopadoSarm' => UploadedFile::fake()->create('resultado.pdf', 8, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('exito', 'Resultado del hisopado registrado y archivo subido correctamente.');

        $this->assertStringStartsWith('local:hisopados/', $this->hisopadoDe($cirugia)->urlHisopadoSarm);
        $this->assertSame('Negativo', $this->hisopadoDe($cirugia)->hisopadoSarmEstados()
            ->whereNull('fechaFinAsignacionHisopadoSarmEstado')
            ->first()?->estadoHisopadoSarm?->nombreEstadoHisopadoSarm);
    }

    public function test_guardar_el_hisopado_sin_adjuntar_nada_no_borra_el_archivo_anterior(): void
    {
        $cirugia = $this->cirugiaConHisopado();
        $this->hisopadoDe($cirugia)->update(['urlHisopadoSarm' => 'local:hisopados/previo.pdf']);

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado", [
                'observacionesHisopadoSarm' => 'Sólo cambio las observaciones.',
            ]);

        $this->assertSame('local:hisopados/previo.pdf', $this->hisopadoDe($cirugia)->urlHisopadoSarm);
    }

    public function test_si_el_gestor_falla_el_resultado_del_hisopado_no_se_registra(): void
    {
        $cirugia = $this->cirugiaConHisopado();

        $this->instance(GestorDocumental::class, new GestorDocumentalCaido);

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado/estado", [
                'estado' => 'Negativo',
                'archivoHisopadoSarm' => UploadedFile::fake()->create('resultado.pdf', 8, 'application/pdf'),
            ])
            ->assertSessionHas('error');

        // Un SAMR negativo sin el resultado detrás decide el protocolo
        // antibiótico sobre un dato que nadie puede verificar.
        $this->assertSame('Pendiente', $this->hisopadoDe($cirugia)->hisopadoSarmEstados()
            ->whereNull('fechaFinAsignacionHisopadoSarmEstado')
            ->first()?->estadoHisopadoSarm?->nombreEstadoHisopadoSarm);
        $this->assertNull($this->hisopadoDe($cirugia)->urlHisopadoSarm);
    }

    public function test_el_hisopado_rechaza_un_archivo_de_tipo_no_permitido(): void
    {
        $cirugia = $this->cirugiaConHisopado();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado", [
                'archivoHisopadoSarm' => UploadedFile::fake()->create('script.exe', 8, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('archivoHisopadoSarm');

        $this->assertNull($this->hisopadoDe($cirugia)->urlHisopadoSarm);
    }

    public function test_ver_el_adjunto_del_hisopado_redirige_al_gestor(): void
    {
        $cirugia = $this->cirugiaConHisopado();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado", [
                'archivoHisopadoSarm' => UploadedFile::fake()->create('hisopado.pdf', 8, 'application/pdf'),
            ]);

        $destino = $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$cirugia->idCirugia}/hisopado/archivo")
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString('expiration=', $destino);
    }

    public function test_la_pestana_de_profilaxis_enlaza_el_adjunto_del_hisopado(): void
    {
        $cirugia = $this->cirugiaConHisopado();
        $ruta = route('cirugias.hisopado.archivo', $cirugia->idCirugia);

        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$cirugia->idCirugia}?tab=profilaxis")
            ->assertOk()
            ->assertDontSee($ruta, false);

        $this->hisopadoDe($cirugia)->update(['urlHisopadoSarm' => 'local:hisopados/previo.pdf']);

        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$cirugia->idCirugia}?tab=profilaxis")
            ->assertOk()
            ->assertSee($ruta, false);
    }

    public function test_el_hisopado_sin_adjunto_no_tiene_documento_que_mostrar(): void
    {
        $cirugia = $this->cirugiaConHisopado();

        $this->actingAs($this->usuario('gonzalez'))
            ->get("/cirugias/{$cirugia->idCirugia}/hisopado/archivo")
            ->assertNotFound();
    }

    public function test_el_anestesista_no_alcanza_el_adjunto_del_hisopado(): void
    {
        $cirugia = $this->cirugiaConHisopado();

        // Mismo grupo de rutas que el resto del hisopado: Gestor y Cirujano.
        $this->actingAs($this->usuario('ramos'))
            ->get("/cirugias/{$cirugia->idCirugia}/hisopado/archivo")
            ->assertForbidden();
    }

    // --- Implementación de Cloudinary ---------------------------------------

    /**
     * `privateDownloadUrl()` firma sobre los parámetros y arma la URL sin pegarle
     * a la API, así que el firmado se puede verificar sin salir a la red. Lo que
     * no se puede probar acá es `guardar()`, que sí hace HTTP.
     */
    private function cloudinary(): GestorDocumentalCloudinary
    {
        return new GestorDocumentalCloudinary(
            new Cloudinary('cloudinary://clave:secreto@nube-de-prueba'),
            'startmed',
            10,
        );
    }

    public function test_cloudinary_entrega_una_url_firmada_y_con_vencimiento(): void
    {
        $url = $this->cloudinary()->urlTemporal('cloudinary:image:startmed/estudios/7/abc:pdf');

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('nube-de-prueba', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $parametros);

        $this->assertArrayHasKey('expires_at', $parametros);
        $this->assertSame('authenticated', $parametros['type']);

        /*
         * Cada parte del puntero tiene que caer en su parámetro. Antes esto se
         * verificaba sólo mirando que la URL tuviera firma y vencimiento, y con
         * public_id y formato invertidos la firma salía igual: Cloudinary
         * respondía «Invalid extension in transformation» recién al abrirla.
         */
        $this->assertSame('startmed/estudios/7/abc', $parametros['public_id']);
        $this->assertSame('pdf', $parametros['format']);
    }

    public function test_cloudinary_respeta_el_tipo_de_recurso_del_puntero(): void
    {
        $url = $this->cloudinary()->urlTemporal('cloudinary:raw:startmed/estudios/7/abc:pdf');

        // El resource_type viaja en la ruta, no en la query.
        $this->assertStringContainsString('/raw/', $url);
    }

    public function test_cloudinary_rechaza_un_puntero_del_gestor_local(): void
    {
        $this->expectException(DocumentoNoDisponible::class);

        $this->cloudinary()->urlTemporal('local:estudios/demo.png');
    }
}

/** Gestor que falla siempre, para el camino de error del controlador. */
final class GestorDocumentalCaido implements GestorDocumental
{
    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        throw new DocumentoNoDisponible('Gestor caído.');
    }

    public function urlTemporal(string $puntero, ?int $minutos = null): string
    {
        throw new DocumentoNoDisponible('Gestor caído.');
    }
}

/** Gestor que devuelve una URL fija, para aislar al controlador del backend. */
final class GestorDocumentalDeUtileria implements GestorDocumental
{
    public const URL = 'https://gestor.example/documento?firma=abc';

    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        return 'utileria:documento';
    }

    public function urlTemporal(string $puntero, ?int $minutos = null): string
    {
        return self::URL;
    }
}
