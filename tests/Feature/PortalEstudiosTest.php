<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Http\Controllers\Portal\EstudioController;
use App\Models\Cirugia;
use App\Models\CirugiaTipoEstudio;
use App\Models\Persona;
use App\Models\TipoEstudio;
use App\Models\Usuario;
use App\Support\DocumentoNoDisponible;
use App\Support\GestorDocumental;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Estudios prequirúrgicos desde el portal: el paciente sube el resultado de un
 * estudio pendiente y ve el que ya está subido.
 *
 * La subida es la misma que la del staff (ResultadoEstudioService), cubierta en
 * GestorDocumentalTest. Acá se verifica lo propio del portal: que escribe el
 * puntero y la fecha juntos, que no reemplaza ni sube a una cirugía anterior, y
 * que ningún estudio ajeno se alcanza.
 */
class PortalEstudiosTest extends TestCase
{
    use RefreshDatabase;

    private Persona $maria;

    /** La de mañana: se le puede subir. */
    private Cirugia $cirugia;

    private CirugiaTipoEstudio $pendiente;

    private CirugiaTipoEstudio $subido;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->maria = Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
        $this->actingAs($this->maria, 'paciente');

        $this->cirugia = Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->where('fechaHoraCirugia', '>', today()->endOfDay())
            ->firstOrFail();

        // Estudios propios del test, para no depender de cuáles siembra la demo.
        CirugiaTipoEstudio::where('idCirugia', $this->cirugia->idCirugia)->delete();
        [$uno, $otro] = TipoEstudio::orderBy('idTipoEstudio')->take(2)->get()->all();

        $this->pendiente = CirugiaTipoEstudio::create([
            'idCirugia' => $this->cirugia->idCirugia,
            'idTipoEstudio' => $uno->idTipoEstudio,
            'fechaAsignacionCirugiaTipoEstudio' => now()->subDays(5),
            'fechaEsperadaResultadoCirugiaTipoEstudio' => now()->addDay(),
        ]);

        $this->subido = CirugiaTipoEstudio::create([
            'idCirugia' => $this->cirugia->idCirugia,
            'idTipoEstudio' => $otro->idTipoEstudio,
            'fechaAsignacionCirugiaTipoEstudio' => now()->subDays(5),
            'fechaSubidaCirugiaTipoEstudio' => now()->subDay(),
            'urlArchivoCirugiaTipoEstudio' => 'local:estudios/previo.pdf',
        ]);
    }

    private function pestana()
    {
        return $this->get("/portal/cirugias/{$this->cirugia->idCirugia}/estudios")->assertOk();
    }

    private function subir(CirugiaTipoEstudio $estudio, ?UploadedFile $archivo = null, ?Cirugia $cirugia = null)
    {
        $cirugia ??= $this->cirugia;

        return $this->post(
            "/portal/cirugias/{$cirugia->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}",
            $archivo ? ['archivoResultadoEstudio' => $archivo] : [],
        );
    }

    private function pdf(int $kilobytes = 12): UploadedFile
    {
        return UploadedFile::fake()->create('hemograma.pdf', $kilobytes, 'application/pdf');
    }

    private function urlArchivo(CirugiaTipoEstudio $estudio, ?Cirugia $cirugia = null): string
    {
        $cirugia ??= $this->cirugia;

        return "/portal/cirugias/{$cirugia->idCirugia}/estudios/{$estudio->idCirugiaTipoEstudio}/archivo";
    }

    // --- Pantalla -----------------------------------------------------------

    public function test_la_pestana_lista_cada_estudio_pedido_con_su_estado(): void
    {
        $this->pestana()
            ->assertSee($this->pendiente->tipoEstudio->nombreTipoEstudio)
            ->assertSee($this->subido->tipoEstudio->nombreTipoEstudio)
            ->assertSee('Pendiente')
            ->assertSee('Subido')
            ->assertSee('Subilo antes del '.$this->pendiente->fechaEsperadaResultadoCirugiaTipoEstudio->translatedFormat('j \d\e F'));
    }

    public function test_un_estudio_atrasado_se_puede_subir_igual_y_no_dice_subilo_antes(): void
    {
        // La fecha esperada ya pasó pero la cirugía no: "Subilo antes del" con
        // una fecha vieja se leía como que ya no se podía.
        $this->pendiente->update(['fechaEsperadaResultadoCirugiaTipoEstudio' => now()->subDays(3)]);
        $fecha = $this->pendiente->fresh()->fechaEsperadaResultadoCirugiaTipoEstudio->translatedFormat('j \d\e F');

        $this->pestana()
            ->assertSee("Se esperaba para el {$fecha}. Subilo cuanto antes.")
            ->assertDontSee("Subilo antes del {$fecha}")
            ->assertSee('action="'.route('portal.cirugias.estudios.subir', [$this->cirugia, $this->pendiente]).'"', false);

        $this->subir($this->pendiente, $this->pdf())->assertSessionHas('exito');
    }

    public function test_un_estudio_que_vence_hoy_todavia_no_esta_atrasado(): void
    {
        $this->pendiente->update(['fechaEsperadaResultadoCirugiaTipoEstudio' => today()]);

        $this->pestana()
            ->assertSee('Subilo antes del '.today()->translatedFormat('j \d\e F'))
            ->assertDontSee('Subilo cuanto antes');
    }

    public function test_el_pendiente_tiene_formulario_y_el_subido_solo_se_ve(): void
    {
        $this->pestana()
            ->assertSee(route('portal.cirugias.estudios.subir', [$this->cirugia, $this->pendiente]), false)
            // El action exacto: la URL de subida es prefijo de la de "Ver".
            ->assertDontSee('action="'.route('portal.cirugias.estudios.subir', [$this->cirugia, $this->subido]).'"', false)
            ->assertSee(route('portal.cirugias.estudios.archivo', [$this->cirugia, $this->subido]), false)
            // El puntero no sale al HTML: solo la ruta que firma al pedirla.
            ->assertDontSee('local:estudios/previo.pdf');
    }

    public function test_sin_estudios_pedidos_lo_dice(): void
    {
        CirugiaTipoEstudio::where('idCirugia', $this->cirugia->idCirugia)->delete();

        $this->pestana()->assertSee('No te pidieron estudios para esta cirugía.');
    }

    // --- Subida -------------------------------------------------------------

    public function test_subir_guarda_el_puntero_y_la_fecha_juntos(): void
    {
        $this->subir($this->pendiente, $this->pdf())
            ->assertRedirect(route('portal.cirugias.show', [$this->cirugia, 'estudios']))
            ->assertSessionHas('exito');

        $estudio = $this->pendiente->fresh();

        $this->assertStringStartsWith('local:estudios/'.$this->cirugia->idCirugia.'/', $estudio->urlArchivoCirugiaTipoEstudio);
        $this->assertNotNull($estudio->fechaSubidaCirugiaTipoEstudio);
        $this->assertNotEmpty(Storage::disk('local')->allFiles('estudios/'.$this->cirugia->idCirugia));
    }

    public function test_si_el_gestor_falla_avisa_y_no_queda_marcado_como_subido(): void
    {
        $this->instance(GestorDocumental::class, new class implements GestorDocumental
        {
            public function guardar(UploadedFile $archivo, string $carpeta): string
            {
                throw new DocumentoNoDisponible('Gestor caído.');
            }

            public function urlTemporal(string $puntero, ?int $minutos = null): string
            {
                throw new DocumentoNoDisponible('Gestor caído.');
            }

            public function reconoce(string $puntero): bool
            {
                return true;
            }
        });

        $this->subir($this->pendiente, $this->pdf())->assertSessionHas('error');

        $estudio = $this->pendiente->fresh();
        $this->assertNull($estudio->urlArchivoCirugiaTipoEstudio);
        $this->assertNull($estudio->fechaSubidaCirugiaTipoEstudio);
    }

    public function test_el_archivo_es_obligatorio_y_se_valida_como_el_del_staff(): void
    {
        $bolsa = EstudioController::bolsaDeErrores($this->pendiente);

        $this->subir($this->pendiente)->assertSessionHasErrorsIn($bolsa, 'archivoResultadoEstudio');
        $this->subir($this->pendiente, UploadedFile::fake()->create('script.exe', 12, 'application/x-msdownload'))
            ->assertSessionHasErrorsIn($bolsa, 'archivoResultadoEstudio');
        $this->subir($this->pendiente, $this->pdf(20481))->assertSessionHasErrorsIn($bolsa, 'archivoResultadoEstudio');

        $this->assertNull($this->pendiente->fresh()->urlArchivoCirugiaTipoEstudio);
    }

    public function test_una_foto_tambien_se_acepta(): void
    {
        $this->subir($this->pendiente, UploadedFile::fake()->image('hemograma.jpg'))->assertSessionHas('exito');

        $this->assertNotNull($this->pendiente->fresh()->urlArchivoCirugiaTipoEstudio);
    }

    public function test_el_error_se_muestra_solo_en_el_estudio_que_se_intento_subir(): void
    {
        $otroPendiente = $this->subido;
        $otroPendiente->update(['urlArchivoCirugiaTipoEstudio' => null, 'fechaSubidaCirugiaTipoEstudio' => null]);

        $this->from("/portal/cirugias/{$this->cirugia->idCirugia}/estudios")->subir($this->pendiente);

        $html = $this->pestana()->getContent();
        $this->assertSame(1, substr_count($html, 'border-red-600'));
    }

    public function test_no_reemplaza_un_estudio_ya_subido(): void
    {
        $archivos = Storage::disk('local')->allFiles();

        $this->subir($this->subido, $this->pdf())->assertSessionHas('aviso');

        $this->assertSame('local:estudios/previo.pdf', $this->subido->fresh()->urlArchivoCirugiaTipoEstudio);
        // Ni siquiera llega al gestor: no queda un archivo huerfano.
        $this->assertSame($archivos, Storage::disk('local')->allFiles());
    }

    public function test_en_una_cirugia_anterior_no_se_sube_nada(): void
    {
        $this->cirugia->update(['fechaHoraCirugia' => now()->subDays(3)]);

        $this->pestana()
            ->assertDontSee(route('portal.cirugias.estudios.subir', [$this->cirugia, $this->pendiente]), false)
            ->assertSee('No se subió el resultado.');

        $this->subir($this->pendiente, $this->pdf())->assertSessionHas('aviso');

        $this->assertNull($this->pendiente->fresh()->urlArchivoCirugiaTipoEstudio);
    }

    // --- Entrega ------------------------------------------------------------

    public function test_ver_redirige_a_la_url_firmada_del_gestor(): void
    {
        $this->instance(GestorDocumental::class, new class implements GestorDocumental
        {
            public function guardar(UploadedFile $archivo, string $carpeta): string
            {
                return 'utileria:documento';
            }

            public function urlTemporal(string $puntero, ?int $minutos = null): string
            {
                return 'https://gestor.example/documento?firma=abc';
            }

            public function reconoce(string $puntero): bool
            {
                return true;
            }
        });

        $this->get($this->urlArchivo($this->subido))->assertRedirect('https://gestor.example/documento?firma=abc');
    }

    public function test_un_estudio_sin_archivo_no_tiene_nada_que_ver(): void
    {
        $this->get($this->urlArchivo($this->pendiente))->assertNotFound();
    }

    public function test_si_el_gestor_no_entrega_el_archivo_la_pestana_no_muestra_el_portal(): void
    {
        // 'local:estudios/previo.pdf' no existe en el disco falso. «Ver» abrió
        // una pestaña nueva: ahí queda una página suelta, no otra copia del portal.
        $this->get($this->urlArchivo($this->subido))
            ->assertStatus(503)
            ->assertSee('No pudimos abrir el resultado de '.$this->subido->tipoEstudio->nombreTipoEstudio)
            ->assertSee('Cerrar esta pestaña')
            ->assertDontSee(route('portal.inicio'), false)
            ->assertDontSee('Confirmá tu asistencia');
    }

    public function test_ver_abre_el_archivo_en_otra_pestana(): void
    {
        $this->pestana()->assertSee('target="_blank"', false);
    }

    public function test_un_puntero_de_otro_gestor_no_ofrece_ver(): void
    {
        // Base sembrada contra Cloudinary servida con el gestor local: ya se
        // sabe que «Ver» fallaría, así que no se ofrece.
        $this->subido->update(['urlArchivoCirugiaTipoEstudio' => 'cloudinary:image:startmed/estudios/x:png']);

        $this->pestana()
            ->assertDontSee(route('portal.cirugias.estudios.archivo', [$this->cirugia, $this->subido]), false)
            ->assertSee('El archivo no está disponible para ver.')
            ->assertSee('Subido');
    }

    // --- Pertenencia --------------------------------------------------------

    public function test_un_estudio_de_otra_cirugia_del_mismo_paciente_da_404(): void
    {
        $otra = Cirugia::where('idPersonaPaciente', $this->maria->idPersona)
            ->whereKeyNot($this->cirugia->idCirugia)
            ->firstOrFail();

        // La cirugía es suya, pero el estudio cuelga de la otra.
        $this->subir($this->pendiente, $this->pdf(), $otra)->assertNotFound();
        $this->get($this->urlArchivo($this->subido, $otra))->assertNotFound();

        $this->assertNull($this->pendiente->fresh()->urlArchivoCirugiaTipoEstudio);
    }

    public function test_el_estudio_de_una_cirugia_ajena_da_404(): void
    {
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $this->maria->idPersona)->firstOrFail();
        $estudio = CirugiaTipoEstudio::create([
            'idCirugia' => $ajena->idCirugia,
            'idTipoEstudio' => $this->pendiente->idTipoEstudio,
            'fechaAsignacionCirugiaTipoEstudio' => now(),
            'urlArchivoCirugiaTipoEstudio' => 'local:estudios/ajeno.pdf',
        ]);

        $this->subir($estudio, $this->pdf(), $ajena)->assertNotFound();
        $this->get($this->urlArchivo($estudio, $ajena))->assertNotFound();
    }

    public function test_una_sesion_de_staff_no_alcanza_los_estudios_del_portal(): void
    {
        // Con guard explicito: el actingAs del setUp dejo `paciente` como guard por
        // defecto, y sin nombrarlo el usuario del staff terminaria en ese.
        auth('paciente')->logout();
        $this->actingAs(Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail(), 'web');

        $this->get($this->urlArchivo($this->subido))->assertRedirect(route('portal.ingresar'));
        $this->subir($this->pendiente, $this->pdf())->assertRedirect(route('portal.ingresar'));
    }
}
