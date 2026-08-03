<?php

namespace Tests\Feature\Admin;

use App\Models\ConfigTipoExamenPreAnestesico as Version;
use App\Models\ConfigTipoExamenPreAnestesicoPregunta as Pregunta;
use App\Models\ConfigTipoExamenPreAnestesicoPreguntaRespuesta as Respuesta;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuestionarioTest extends TestCase
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

    /** Una versión vigente y vacía, lista para editar. */
    private function versionEditable(): Version
    {
        $this->actingAs($this->admin())->post(route('admin.cuestionario.store'));

        return Version::whereNull('fechaFinVigeConfigTipoExamenPreAnestesico')->firstOrFail();
    }

    private function agregarPregunta(Version $version, string $texto, bool $conOpciones = false): Pregunta
    {
        $this->actingAs($this->admin())
            ->post(route('admin.cuestionario.preguntas.store', $version), [
                'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => $texto,
                'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => $conOpciones ? '1' : '0',
            ])
            ->assertSessionHasNoErrors();

        return Pregunta::where('nombrePreguntaConfigTipoExamenPreAnestesicoPregunta', $texto)->firstOrFail();
    }

    // --- Versiones ---

    public function test_publica_la_primera_version(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.cuestionario.store'))
            ->assertSessionHas('exito');

        $version = Version::firstOrFail();

        $this->assertNotNull($version->fechaInicioVigeConfigTipoExamenPreAnestesico);
        $this->assertNull($version->fechaFinVigeConfigTipoExamenPreAnestesico);
    }

    /**
     * Publicar clona el árbol: se retoca un cuestionario, no se escribe uno
     * nuevo desde cero.
     */
    public function test_publicar_clona_las_preguntas_y_sus_opciones(): void
    {
        $primera = $this->versionEditable();
        $pregunta = $this->agregarPregunta($primera, '¿Fumás?', conOpciones: true);

        foreach (['No', 'Sí, menos de 10 por día'] as $opcion) {
            $this->actingAs($this->admin())->post(
                route('admin.cuestionario.respuestas.store', [$primera, $pregunta]),
                ['nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => $opcion],
            );
        }

        $this->actingAs($this->admin())->post(route('admin.cuestionario.store'));

        $segunda = Version::whereNull('fechaFinVigeConfigTipoExamenPreAnestesico')->firstOrFail();

        $this->assertNotSame($primera->idConfigTipoExamenPreAnestesico, $segunda->idConfigTipoExamenPreAnestesico);
        $this->assertNotNull($primera->fresh()->fechaFinVigeConfigTipoExamenPreAnestesico);

        $clonada = $segunda->configTipoExamenPreAnestesicoPreguntas()->firstOrFail();

        $this->assertSame('¿Fumás?', $clonada->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta);
        $this->assertCount(2, $clonada->configTipoExamenPreAnestesicoPreguntaRespuestas);

        // Y son copias: la original queda intacta.
        $this->assertCount(2, $pregunta->fresh()->configTipoExamenPreAnestesicoPreguntaRespuestas);
    }

    public function test_retirar_deja_sin_cuestionario_vigente(): void
    {
        $version = $this->versionEditable();

        $this->actingAs($this->admin())
            ->delete(route('admin.cuestionario.destroy', $version))
            ->assertSessionHas('exito');

        $this->assertSame(0, Version::whereNull('fechaFinVigeConfigTipoExamenPreAnestesico')->count());
        $this->assertSame(1, Version::count());
    }

    // --- Preguntas y opciones ---

    public function test_se_agregan_preguntas_de_texto_libre_y_con_opciones(): void
    {
        $version = $this->versionEditable();

        $libre = $this->agregarPregunta($version, '¿Tomás medicación habitual?');
        $cerrada = $this->agregarPregunta($version, '¿Fumás?', conOpciones: true);

        $this->assertFalse((bool) $libre->requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta);
        $this->assertTrue((bool) $cerrada->requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta);
    }

    public function test_la_pregunta_es_obligatoria(): void
    {
        $version = $this->versionEditable();

        $this->actingAs($this->admin())
            ->post(route('admin.cuestionario.preguntas.store', $version), [
                'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => '',
            ])
            ->assertSessionHasErrors('nombrePreguntaConfigTipoExamenPreAnestesicoPregunta');
    }

    public function test_se_edita_y_se_elimina_una_pregunta_con_sus_opciones(): void
    {
        $version = $this->versionEditable();
        $pregunta = $this->agregarPregunta($version, '¿Fumás?', conOpciones: true);

        $this->actingAs($this->admin())->post(
            route('admin.cuestionario.respuestas.store', [$version, $pregunta]),
            ['nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => 'No'],
        );

        $this->actingAs($this->admin())->put(route('admin.cuestionario.preguntas.update', [$version, $pregunta]), [
            'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => '¿Fumás actualmente?',
            'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => '1',
        ]);

        $this->assertSame('¿Fumás actualmente?', $pregunta->fresh()->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta);

        $this->actingAs($this->admin())
            ->delete(route('admin.cuestionario.preguntas.destroy', [$version, $pregunta]))
            ->assertSessionHas('exito');

        $this->assertSame(0, Pregunta::count());
        // Las opciones se van con la pregunta: si no, quedarían huérfanas.
        $this->assertSame(0, Respuesta::count());
    }

    public function test_se_quita_una_opcion_suelta(): void
    {
        $version = $this->versionEditable();
        $pregunta = $this->agregarPregunta($version, '¿Fumás?', conOpciones: true);

        $this->actingAs($this->admin())->post(
            route('admin.cuestionario.respuestas.store', [$version, $pregunta]),
            ['nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => 'Tal vez'],
        );
        $respuesta = Respuesta::firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.cuestionario.respuestas.destroy', [$version, $pregunta, $respuesta]))
            ->assertSessionHas('exito');

        $this->assertSame(0, Respuesta::count());
        $this->assertSame(1, Pregunta::count());
    }

    public function test_la_pantalla_avisa_si_una_pregunta_cerrada_no_tiene_opciones(): void
    {
        $version = $this->versionEditable();
        $this->agregarPregunta($version, '¿Fumás?', conOpciones: true);

        $this->actingAs($this->admin())
            ->get(route('admin.cuestionario.show', $version))
            ->assertOk()
            ->assertSee('Sin opciones para elegir');
    }

    /**
     * Esta pantalla repite el mismo campo en un formulario por pregunta. Si el
     * id saliera del name —como hacía <x-input> antes— quedarían duplicados y
     * cada label enfocaría el primero, no el suyo.
     */
    public function test_los_formularios_repetidos_no_duplican_ids(): void
    {
        $version = $this->versionEditable();
        $this->agregarPregunta($version, '¿Fumás?', conOpciones: true);
        $this->agregarPregunta($version, '¿Tomás medicación habitual?', conOpciones: true);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.cuestionario.show', $version))
            ->assertOk()
            ->getContent();

        preg_match_all('/\sid="([^"]+)"/', $html, $encontrados);
        $repetidos = array_keys(array_filter(
            array_count_values($encontrados[1]),
            fn (int $veces) => $veces > 1,
        ));

        $this->assertSame([], $repetidos, 'hay id repetidos en la página');

        // Y cada label sigue apuntando a un campo que existe.
        preg_match_all('/<label[^>]+for="([^"]+)"/', $html, $labels);
        foreach ($labels[1] as $destino) {
            $this->assertContains($destino, $encontrados[1], "el label apunta a #{$destino}, que no existe");
        }
    }

    // --- El congelamiento, que es el punto de todo esto ---

    /**
     * ResumenCirugia lee el texto de la pregunta en vivo desde esta tabla: no
     * hay snapshot como el de ConsentimientoPaciente. Editar una pregunta ya
     * respondida cambiaría qué se le preguntó a ese paciente.
     */
    public function test_una_version_ya_respondida_no_se_edita(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);

        $version = Version::whereNull('fechaFinVigeConfigTipoExamenPreAnestesico')->firstOrFail();
        $pregunta = $version->configTipoExamenPreAnestesicoPreguntas()->firstOrFail();
        $textoOriginal = $pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta;

        $this->assertTrue($version->examenPreAnestesicoConfiges()->exists(), 'el seeder debería dejarla respondida');

        $this->actingAs($this->admin())
            ->put(route('admin.cuestionario.preguntas.update', [$version, $pregunta]), [
                'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => 'Pregunta cambiada',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->delete(route('admin.cuestionario.preguntas.destroy', [$version, $pregunta]))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('admin.cuestionario.preguntas.store', $version), [
                'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => 'Pregunta nueva',
            ])
            ->assertForbidden();

        $this->assertSame($textoOriginal, $pregunta->fresh()->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta);
    }

    public function test_la_pantalla_de_una_version_respondida_es_de_solo_lectura(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);

        $version = Version::whereNull('fechaFinVigeConfigTipoExamenPreAnestesico')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.cuestionario.show', $version))
            ->assertOk()
            ->assertSee('Solo lectura')
            ->assertDontSee('Agregar una pregunta');
    }

    public function test_una_version_cerrada_tampoco_se_edita(): void
    {
        $version = $this->versionEditable();
        $this->actingAs($this->admin())->delete(route('admin.cuestionario.destroy', $version));

        $this->actingAs($this->admin())
            ->post(route('admin.cuestionario.preguntas.store', $version), [
                'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => 'A destiempo',
            ])
            ->assertForbidden();
    }

    // --- Acceso ---

    public function test_la_seccion_esta_cerrada_a_los_demas_roles(): void
    {
        $this->seed(DemoSeeder::class);

        $cirujano = Usuario::where('nombreUsuario', 'perez')->firstOrFail();

        $this->actingAs($cirujano)->get(route('admin.cuestionario.index'))->assertForbidden();
        $this->actingAs($cirujano)->post(route('admin.cuestionario.store'))->assertForbidden();
    }
}
