<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\EvaluacionAnestesica;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\TipoAnestesia;
use App\Models\TipoASA;
use App\Models\TipoCirugia;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpedienteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnestesistaCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseSeeder deja los catálogos y el usuario admin. Los datos de
        // demo no los corre solo, porque en testing no estamos en local.
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->seed(ExpedienteSeeder::class);
    }

    private function usuario(string $nombre): Usuario
    {
        return Usuario::where('nombreUsuario', $nombre)->firstOrFail();
    }

    private function cirugiaDe(string $apellido): Cirugia
    {
        return Cirugia::whereHas('paciente', fn ($q) => $q->where('apellidos', $apellido))->firstOrFail();
    }

    /** @return array{0: Usuario, 1: Personal} */
    private function nuevoAnestesista(): array
    {
        $persona = Persona::create([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => '4000'.random_int(100000, 999999),
            'apellidos' => 'Otamendi',
            'nombres' => 'Nadia',
        ]);

        $personal = Personal::create(['idPersona' => $persona->idPersona]);

        $personal->roles()->attach(
            Rol::where('nombreRol', 'Anestesista')->value('idRol'),
            ['fechaHoraAsignacionRolPersonal' => now()],
        );

        $usuario = Usuario::create([
            'idPersonal' => $personal->idPersonal,
            'nombreUsuario' => 'otamendi',
            'passwordUsuario' => 'demo1234',
        ]);

        return [$usuario, $personal];
    }

    /** @param  list<array{0:string,1:int}>  $estudios */
    private function cirugiaPara(Personal $anestesista, string $apellido = 'Nuevo'): Cirugia
    {
        $tipo = TipoCirugia::firstOrFail();

        $paciente = Persona::create([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => '4100'.random_int(100000, 999999),
            'apellidos' => $apellido,
            'nombres' => 'Paciente',
        ]);

        return Cirugia::create([
            'idPersonaPaciente' => $paciente->idPersona,
            'idTipoCirugia' => $tipo->idTipoCirugia,
            'idPersonalCirujano' => $anestesista->idPersonal,
            'idPersonalAnestesista' => $anestesista->idPersonal,
            'fechaHoraCirugia' => now()->addDays(3)->setTime(8, 0),
            'descripcionCirugia' => $tipo->nombreTipoCirugia,
            'requiereImplante' => false,
        ]);
    }

    public function test_ramos_inicia_sesion_con_demo1234_y_aterriza_en_su_panel(): void
    {
        $this->post('/login', [
            'nombreUsuario' => 'ramos',
            'password' => 'demo1234',
        ])->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($this->usuario('ramos'));

        $this->get('/anestesista')
            ->assertOk()
            ->assertSee('Evaluaciones anestésicas');
    }

    public function test_el_formulario_de_evaluacion_se_muestra_para_una_cirugia_sin_evaluar(): void
    {
        [$usuario, $personal] = $this->nuevoAnestesista();
        $cirugia = $this->cirugiaPara($personal);

        $this->actingAs($usuario)
            ->get(route('anestesista.evaluar', $cirugia))
            ->assertOk()
            ->assertSee('Clasificación ASA')
            ->assertSee('Tipo de anestesia')
            ->assertSee('Apto')
            ->assertSee('No apto');
    }

    public function test_si_la_cirugia_ya_tiene_evaluacion_se_deriva_a_editar(): void
    {
        $vidal = $this->cirugiaDe('Vidal');

        $this->actingAs($this->usuario('ramos'))
            ->get(route('anestesista.evaluar', $vidal))
            ->assertRedirect(route('anestesista.editar', $vidal));
    }

    public function test_el_anestesista_puede_registrar_una_evaluacion(): void
    {
        [$usuario, $personal] = $this->nuevoAnestesista();
        $cirugia = $this->cirugiaPara($personal);

        $this->actingAs($usuario)
            ->post(route('anestesista.store', $cirugia), [
                'idTipoAsa' => TipoASA::where('aliasTipoAsa', 'ASA II')->value('idTipoAsa'),
                'idTipoAnestesia' => TipoAnestesia::where('nombreTipoAnestesia', 'General')->value('idTipoAnestesia'),
                'observacionesEquipoEvaluacion' => 'Sin novedades',
                'observacionesPacienteEvaluacion' => 'Paciente colaborador',
            ])
            ->assertRedirect(route('cirugias.show', [$cirugia, 'tab' => 'evaluacion']));

        $evaluacion = EvaluacionAnestesica::where('idCirugia', $cirugia->idCirugia)->first();
        $this->assertNotNull($evaluacion);
        $this->assertSame('Sin novedades', $evaluacion->observacionesEquipoEvaluacion);
        $this->assertSame('Paciente colaborador', $evaluacion->observacionesPacienteEvaluacion);

        $estado = $evaluacion->evaluacionAnestesicaEstados()->whereNull('fechaFinEvaluacionAnestesicaEstado')->first();
        $this->assertSame('Completada', $estado->estadoEvaluacionAnestesica->nombreEstadoEvaluacionAnestesica);

        $this->assertSame('ASA II', $evaluacion->evaluacionTipoAsas()->whereNull('fechaFinTipoAsa')->first()->tipoAsa->aliasTipoAsa);
        $this->assertSame('General', $evaluacion->evaluacionTipoAnestesias()->whereNull('fechaFinTipoAnestesia')->first()->tipoAnestesia->nombreTipoAnestesia);
    }

    public function test_la_evaluacion_requiere_asa_y_tipo_de_anestesia(): void
    {
        [$usuario, $personal] = $this->nuevoAnestesista();
        $cirugia = $this->cirugiaPara($personal);

        $this->actingAs($usuario)
            ->post(route('anestesista.store', $cirugia), [])
            ->assertSessionHasErrors(['idTipoAsa', 'idTipoAnestesia']);
    }

    public function test_el_formulario_de_edicion_muestra_la_evaluacion_cargada(): void
    {
        $garcia = $this->cirugiaDe('García');

        $this->actingAs($this->usuario('ramos'))
            ->get(route('anestesista.editar', $garcia))
            ->assertOk()
            ->assertSee('Apto')
            ->assertSee('No apto')
            ->assertDontSee('Eliminar evaluación');
    }

    /**
     * La reevaluación: se evalúa con un ASA y después se corrige.
     *
     * La premisa la arma el test evaluando primero, y no un caso del seeder,
     * por dos motivos. Uno, así prueba la transición entera —evaluar y volver
     * a evaluar— y no solo el segundo paso. Dos, un caso con ASA cargado es
     * por definición uno Completado: el formulario exige ASA y tipo de
     * anestesia, y guardar llama a completar(). No existe en la aplicación una
     * evaluación Pendiente con ASA, así que apoyarse en los datos de demo para
     * conseguir una era apoyarse en algo que el seeder no puede darle.
     */
    public function test_el_anestesista_puede_actualizar_la_evaluacion(): void
    {
        [$usuario, $personal] = $this->nuevoAnestesista();
        $cirugia = $this->cirugiaPara($personal);

        $this->actingAs($usuario)
            ->post(route('anestesista.store', $cirugia), [
                'idTipoAsa' => TipoASA::where('aliasTipoAsa', 'ASA II')->value('idTipoAsa'),
                'idTipoAnestesia' => TipoAnestesia::where('nombreTipoAnestesia', 'Sedación + local')->value('idTipoAnestesia'),
                'observacionesEquipoEvaluacion' => 'Primera evaluación',
            ])
            ->assertRedirect(route('cirugias.show', [$cirugia, 'tab' => 'evaluacion']));

        $evaluacion = $cirugia->evaluacionAnestesicas()->first();
        $this->assertNotNull($evaluacion);

        $this->actingAs($usuario)
            ->put(route('anestesista.update', $cirugia), [
                'idTipoAsa' => TipoASA::where('aliasTipoAsa', 'ASA III')->value('idTipoAsa'),
                'idTipoAnestesia' => TipoAnestesia::where('nombreTipoAnestesia', 'General')->value('idTipoAnestesia'),
                'observacionesEquipoEvaluacion' => 'Actualizada tras el cuestionario',
            ])
            ->assertRedirect(route('cirugias.show', [$cirugia, 'tab' => 'evaluacion']));

        $this->assertSame('Actualizada tras el cuestionario', $evaluacion->fresh()->observacionesEquipoEvaluacion);

        // Quedó Completada y los valores vigentes son los nuevos.
        $estado = $evaluacion->evaluacionAnestesicaEstados()->whereNull('fechaFinEvaluacionAnestesicaEstado')->first();
        $this->assertSame('Completada', $estado->estadoEvaluacionAnestesica->nombreEstadoEvaluacionAnestesica);

        $this->assertSame('ASA III', $evaluacion->evaluacionTipoAsas()->whereNull('fechaFinTipoAsa')->first()->tipoAsa->aliasTipoAsa);
        $this->assertSame('General', $evaluacion->evaluacionTipoAnestesias()->whereNull('fechaFinTipoAnestesia')->first()->tipoAnestesia->nombreTipoAnestesia);

        // Lo anterior no se pisa: queda cerrado en el historial, que es como el
        // esquema guarda todos sus cambios de estado.
        $cerradas = $evaluacion->evaluacionTipoAsas()->whereNotNull('fechaFinTipoAsa')->get();
        $this->assertCount(1, $cerradas);
        $this->assertSame('ASA II', $cerradas->first()->tipoAsa->aliasTipoAsa);

        $anestesiaCerrada = $evaluacion->evaluacionTipoAnestesias()->whereNotNull('fechaFinTipoAnestesia')->first();
        $this->assertSame('Sedación + local', $anestesiaCerrada->tipoAnestesia->nombreTipoAnestesia);
    }

    public function test_el_anestesista_puede_eliminar_la_evaluacion(): void
    {
        $garcia = $this->cirugiaDe('García');
        $this->assertNotNull($garcia->evaluacionAnestesicas()->first());

        $this->actingAs($this->usuario('ramos'))
            ->delete(route('anestesista.destroy', $garcia))
            ->assertRedirect(route('cirugias.show', [$garcia, 'tab' => 'evaluacion']));

        $this->assertNull($garcia->evaluacionAnestesicas()->first());
    }

    public function test_un_anestesista_no_puede_tocar_una_cirugia_ajena(): void
    {
        $garcia = $this->cirugiaDe('García');

        [$otro, $otroPersonal] = $this->nuevoAnestesista();

        $this->actingAs($otro)
            ->get(route('anestesista.evaluar', $garcia))
            ->assertForbidden();

        $this->actingAs($otro)
            ->post(route('anestesista.store', $garcia), [])
            ->assertForbidden();
    }

    public function test_el_cirujano_no_puede_entrar_al_crud_del_anestesista(): void
    {
        $garcia = $this->cirugiaDe('García');

        $this->actingAs($this->usuario('perez'))
            ->get(route('anestesista.evaluar', $garcia))
            ->assertForbidden();
    }
}
