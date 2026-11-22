<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Support\Asistente\DestinosAsistente;
use App\Support\Asistente\InstruccionesAsistente;
use App\Support\Asistente\ProveedorAsistente;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fakes\ProveedorAsistenteFalso;
use Tests\TestCase;

/**
 * Los botones «Ir a …» del asistente: a que pantallas puede llevar a cada
 * quien. Es una lista blanca, asi que lo que importa es que no ofrezca de mas
 * (una pantalla ajena da 403) ni quede corta respecto de lo que las guias le
 * hacen nombrar al modelo.
 */
class AsistenteDestinosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);

        config(['asistente.clave' => 'clave-de-prueba']);
        $this->app->instance(ProveedorAsistente::class, new ProveedorAsistenteFalso);
    }

    private function usuarioCon(string $rol): Usuario
    {
        static $numero = 0;
        $numero++;

        $persona = Persona::create([
            'tipo_documento_id' => TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento'),
            'documento' => (string) (41000000 + $numero),
            'apellidos' => 'Prueba',
            'nombres' => $rol,
        ]);

        $personal = Personal::create(['idPersona' => $persona->idPersona]);
        $personal->roles()->attach(
            Rol::where('nombreRol', $rol)->value('idRol'),
            ['fechaHoraAsignacionRolPersonal' => now()],
        );

        return Usuario::create([
            'idPersonal' => $personal->idPersonal,
            'nombreUsuario' => 'prueba'.$numero,
            'passwordUsuario' => 'secreto123',
        ]);
    }

    public function test_el_gestor_puede_ir_a_sus_pantallas_y_no_a_las_ajenas(): void
    {
        $destinos = DestinosAsistente::paraStaff($this->usuarioCon('Gestor de quirófano'));

        $this->assertSame('Nueva cirugía', $destinos['/cirugias/nueva'] ?? null);
        $this->assertSame('Agenda', $destinos['/agenda'] ?? null);
        $this->assertArrayNotHasKey('/admin/usuarios', $destinos);
        $this->assertArrayNotHasKey('/cirujano', $destinos);
        $this->assertArrayNotHasKey('/portal', $destinos);
    }

    public function test_el_paciente_solo_puede_ir_a_las_pantallas_del_portal_con_sesion(): void
    {
        $this->assertSame([
            '/portal' => 'Próximas cirugías',
            '/portal/historial' => 'Historial de cirugías',
            '/portal/perfil' => 'Mi perfil',
        ], DestinosAsistente::paraPaciente());
    }

    /**
     * Cada pantalla que la guia de un rol le hace nombrar al modelo tiene que
     * ser una a la que ese rol puede entrar. Si no, el asistente lo manda a un
     * 403 (pasaba con la agenda del anestesista, que el menu le muestra pero
     * la ruta solo deja pasar al cirujano).
     */
    public function test_cada_pantalla_que_nombra_la_guia_de_un_rol_es_accesible_para_ese_rol(): void
    {
        foreach (InstruccionesAsistente::AYUDA_POR_ROL as $rol => $archivo) {
            $destinos = DestinosAsistente::paraStaff($this->usuarioCon($rol));

            foreach (DestinosAsistente::direccionesDe($archivo) as $direccion) {
                $this->assertArrayHasKey(
                    $direccion,
                    $destinos,
                    "La guía de «{$rol}» nombra {$direccion}, pero ese rol no puede entrar.",
                );
            }
        }
    }

    public function test_cada_pantalla_que_nombra_la_guia_del_portal_es_del_paciente_o_publica(): void
    {
        $destinos = DestinosAsistente::paraPaciente();

        foreach (DestinosAsistente::direccionesDe('paciente') as $direccion) {
            $ruta = collect(Route::getRoutes()->get('GET'))->first(fn ($r) => '/'.$r->uri() === $direccion);
            $publica = $ruta && ! collect($ruta->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth'));

            $this->assertTrue(
                isset($destinos[$direccion]) || $publica,
                "La guía del portal nombra {$direccion}, que no es del paciente ni pública.",
            );
        }
    }

    public function test_cada_direccion_de_las_guias_tiene_etiqueta(): void
    {
        $etiquetas = DestinosAsistente::etiquetas();

        foreach (InstruccionesAsistente::archivos() as $archivo) {
            foreach (DestinosAsistente::direccionesDe($archivo) as $direccion) {
                $this->assertArrayHasKey(
                    $direccion,
                    $etiquetas,
                    "{$direccion} (resources/ayuda/{$archivo}.md) no está en ningún menú: sumale una etiqueta en DestinosAsistente::ETIQUETAS.",
                );
            }
        }
    }

    public function test_ningun_destino_lleva_parametros(): void
    {
        $todos = [
            ...DestinosAsistente::paraStaff(Usuario::where('nombreUsuario', 'admin')->firstOrFail()),
            ...DestinosAsistente::paraPaciente(),
        ];

        foreach (array_keys($todos) as $direccion) {
            $this->assertStringNotContainsString('{', $direccion);
            $this->assertStringStartsWith('/', $direccion);
        }
    }

    public function test_el_widget_recibe_la_lista_de_su_area(): void
    {
        $this->actingAs(Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-destinos="{&quot;/dashboard&quot;:&quot;Tablero&quot;', false)
            ->assertDontSee('/admin/usuarios&quot;', false);
    }
}
