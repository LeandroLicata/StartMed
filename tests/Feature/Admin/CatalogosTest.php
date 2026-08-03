<?php

namespace Tests\Feature\Admin;

use App\Models\ObraSocial;
use App\Models\Plan;
use App\Models\Rol;
use App\Models\TipoCirugia;
use App\Models\TipoEstudio;
use App\Models\Usuario;
use App\Support\Catalogos;
use App\Support\FiltroBaja;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Catálogos + usuario admin. Los datos de demo no corren solos en testing.
        $this->seed();
    }

    private function admin(): Usuario
    {
        return Usuario::where('nombreUsuario', 'admin')->firstOrFail();
    }

    /**
     * El equivalente de ModelosTest para el mapa: si una columna se renombra o
     * un catálogo se declara mal, se ve acá y no en una pantalla rota.
     */
    public function test_el_mapa_coincide_con_el_esquema(): void
    {
        $this->assertCount(26, Catalogos::todos());

        foreach (Catalogos::todos() as $slug => $config) {
            $modelo = new $config['modelo'];
            $tabla = $modelo->getTable();

            $this->assertTrue(
                Schema::hasColumn($tabla, $config['baja']),
                "$slug declara una columna de baja que no existe: $tabla.{$config['baja']}",
            );

            foreach ($config['campos'] as $columna => $campo) {
                $this->assertTrue(
                    Schema::hasColumn($tabla, $columna),
                    "$slug declara un campo que no existe: $tabla.$columna",
                );

                $this->assertTrue(
                    $modelo->isFillable($columna),
                    "$columna no es asignable en masa en {$config['modelo']}",
                );

                $this->assertArrayHasKey($campo['tipo'] ?? 'texto', Catalogos::TIPOS, "$slug.$columna usa un tipo desconocido");
            }

            $this->assertArrayHasKey($config['grupo'], Catalogos::GRUPOS, "$slug está en un grupo sin ícono");
        }
    }

    public function test_todos_los_catalogos_tienen_listado(): void
    {
        foreach (array_keys(Catalogos::todos()) as $slug) {
            $this->actingAs($this->admin())
                ->get(route('admin.catalogos.index', $slug))
                ->assertOk();
        }
    }

    /**
     * El formulario es lo más sensible al mapa: dibuja un componente distinto
     * por tipo de campo. Se renderizan los 26 para que un tipo mal declarado
     * falle acá.
     */
    public function test_todos_los_catalogos_tienen_formulario_de_alta(): void
    {
        foreach (array_keys(Catalogos::todos()) as $slug) {
            $this->actingAs($this->admin())
                ->get(route('admin.catalogos.create', $slug))
                ->assertOk();
        }
    }

    public function test_el_formulario_de_edicion_carga_el_registro(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Hemograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.edit', ['tipo-estudio', $estudio->idTipoEstudio]))
            ->assertOk()
            ->assertSee('Hemograma');

        // El camino con select y checkbox, que es el que más se aparta del resto.
        $obraSocial = ObraSocial::create(['nombreObraSocial' => 'Galeno']);
        $plan = Plan::create([
            'idobrasocial' => $obraSocial->idObraSocial,
            'nombrePlan' => 'Azul 220',
            'habilitado_autorizaciones' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.edit', ['plan', $plan->idPlan]))
            ->assertOk()
            ->assertSee('Azul 220')
            ->assertSee('Galeno');
    }

    public function test_el_indice_de_administracion_lista_los_catalogos(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.inicio'))
            ->assertOk()
            ->assertSee('Tipos de cirugía')
            ->assertSee('Obras sociales')
            ->assertSee('Usuarios activos');
    }

    /**
     * El índice es la única puerta a la sección, así que un módulo que no
     * figure acá queda inalcanzable desde la interfaz.
     */
    public function test_el_indice_lleva_a_todos_los_modulos(): void
    {
        $respuesta = $this->actingAs($this->admin())
            ->get(route('admin.inicio'))
            ->assertOk();

        foreach ([
            'admin.usuarios.index',
            'admin.consentimientos.index',
            'admin.cuestionario.index',
            'admin.precios.index',
            'admin.auditoria',
        ] as $ruta) {
            $respuesta->assertSee('href="'.route($ruta).'"', false);
        }

        // Y a cada uno de los 26 catálogos.
        foreach (array_keys(Catalogos::todos()) as $slug) {
            $respuesta->assertSee('href="'.route('admin.catalogos.index', $slug).'"', false);
        }
    }

    public function test_se_puede_crear_un_catalogo_simple(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'tipo-estudio'), [
                'nombreTipoEstudio' => 'Espirometría',
            ])
            ->assertRedirect(route('admin.catalogos.index', 'tipo-estudio'))
            ->assertSessionHas('exito');

        $this->assertDatabaseHas('TipoEstudio', ['nombreTipoEstudio' => 'Espirometría']);
    }

    public function test_se_puede_editar_un_catalogo(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Hemograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', ['tipo-estudio', $estudio->idTipoEstudio]), [
                'nombreTipoEstudio' => 'Hemograma completo',
            ])
            ->assertRedirect(route('admin.catalogos.index', 'tipo-estudio'));

        $this->assertSame('Hemograma completo', $estudio->fresh()->nombreTipoEstudio);
    }

    public function test_un_catalogo_con_campos_extra_guarda_todas_sus_columnas(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'obra-social'), [
                'nombreObraSocial' => 'OSDE',
                'telefonoObraSocial' => '0800-555-6733',
                'emailObraSocial' => 'auditoria@osde.test',
                'diasVigenciaOrden' => 30,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ObraSocial', [
            'nombreObraSocial' => 'OSDE',
            'emailObraSocial' => 'auditoria@osde.test',
            'diasVigenciaOrden' => 30,
        ]);
    }

    public function test_un_catalogo_con_select_y_booleanos(): void
    {
        $obraSocial = ObraSocial::create(['nombreObraSocial' => 'Swiss Medical']);

        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'plan'), [
                'idobrasocial' => $obraSocial->idObraSocial,
                'nombrePlan' => 'SMG02',
                'es_sin_cobertura' => '0',
                'habilitado_autorizaciones' => '1',
            ])
            ->assertSessionHasNoErrors();

        $plan = Plan::where('nombrePlan', 'SMG02')->firstOrFail();

        $this->assertSame($obraSocial->idObraSocial, $plan->idobrasocial);
        $this->assertFalse((bool) $plan->es_sin_cobertura);
        $this->assertTrue((bool) $plan->habilitado_autorizaciones);
    }

    public function test_la_obra_social_del_plan_tiene_que_existir(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'plan'), [
                'idobrasocial' => 9999,
                'nombrePlan' => 'Inventado',
            ])
            ->assertSessionHasErrors('idobrasocial');
    }

    public function test_el_nombre_es_obligatorio_y_no_se_repite(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'tipo-estudio'), ['nombreTipoEstudio' => ''])
            ->assertSessionHasErrors('nombreTipoEstudio');

        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'tipo-estudio'), ['nombreTipoEstudio' => 'Hemograma'])
            ->assertSessionHasErrors('nombreTipoEstudio');
    }

    public function test_los_mensajes_de_validacion_estan_en_castellano(): void
    {
        // Sin lang/es/validation.php saldrían como la clave cruda "validation.required".
        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'tipo-estudio'), ['nombreTipoEstudio' => ''])
            ->assertSessionHasErrors(['nombreTipoEstudio' => 'El campo nombre es obligatorio.']);
    }

    public function test_editar_no_choca_con_el_nombre_del_propio_registro(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Hemograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', ['tipo-estudio', $estudio->idTipoEstudio]), [
                'nombreTipoEstudio' => 'Hemograma',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_dar_de_baja_no_borra_la_fila_y_se_puede_reactivar(): void
    {
        $estudio = TipoEstudio::where('nombreTipoEstudio', 'Coagulograma')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.catalogos.destroy', ['tipo-estudio', $estudio->idTipoEstudio]))
            ->assertSessionHas('exito');

        $this->assertNotNull($estudio->fresh()->fechaBajaTipoEstudio);
        $this->assertDatabaseHas('TipoEstudio', ['idTipoEstudio' => $estudio->idTipoEstudio]);

        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.restore', ['tipo-estudio', $estudio->idTipoEstudio]));

        $this->assertNull($estudio->fresh()->fechaBajaTipoEstudio);
    }

    /**
     * El filtro tiene tres estados, no dos: antes «ver dados de baja» mostraba
     * en realidad todos, y no había forma de ver solo las bajas.
     */
    public function test_el_filtro_de_estado_separa_activos_bajas_y_todos(): void
    {
        TipoEstudio::where('nombreTipoEstudio', 'Coagulograma')
            ->update(['fechaBajaTipoEstudio' => now()]);

        $ruta = fn (string $estado) => route('admin.catalogos.index', [
            'catalogo' => 'tipo-estudio',
            'estado' => $estado,
        ]);

        // Por defecto, solo los activos.
        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', 'tipo-estudio'))
            ->assertOk()
            ->assertSee('Hemograma')
            ->assertDontSee('Coagulograma');

        $this->actingAs($this->admin())
            ->get($ruta(FiltroBaja::BAJAS))
            ->assertOk()
            ->assertSee('Coagulograma')
            ->assertDontSee('Hemograma');

        $this->actingAs($this->admin())
            ->get($ruta(FiltroBaja::TODOS))
            ->assertOk()
            ->assertSee('Coagulograma')
            ->assertSee('Hemograma');
    }

    public function test_un_estado_desconocido_cae_en_el_de_por_defecto(): void
    {
        TipoEstudio::where('nombreTipoEstudio', 'Coagulograma')
            ->update(['fechaBajaTipoEstudio' => now()]);

        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', ['catalogo' => 'tipo-estudio', 'estado' => 'inventado']))
            ->assertOk()
            ->assertSee('Hemograma')
            ->assertDontSee('Coagulograma');
    }

    /**
     * Cada fila de esta lista es buscada por su nombre literal en algún lado
     * del código: el rol resuelve el acceso a la sección, los estados alimentan
     * los indicadores de los paneles. Renombrarlas no lanza ningún error —
     * simplemente deja de funcionar—, así que el ABM no las deja tocar.
     *
     * @return array<string, array{string, string}> slug y una de sus filas protegidas
     */
    public static function filasDelSistema(): array
    {
        return [
            'rol que da acceso' => ['rol', 'Administrador'],
            'estados de cirugía' => ['estado-cirugia', 'Realizada'],
            'estados de material' => ['estado-pedido-material', 'En auditoría'],
            'estado de autorización' => ['estado-autorizacion', 'Aprobada'],
            'estado de evaluación' => ['estado-evaluacion', 'Completada'],
        ];
    }

    #[DataProvider('filasDelSistema')]
    public function test_las_filas_del_sistema_no_se_pueden_renombrar(string $slug, string $protegida): void
    {
        $config = Catalogos::buscar($slug);
        $columna = Catalogos::columnaTitulo($config);
        $registro = $config['modelo']::where($columna, $protegida)->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', [$slug, $registro->getKey()]), [$columna => 'Otro nombre'])
            ->assertSessionHas('error');

        $this->assertSame($protegida, $registro->fresh()->$columna);
    }

    #[DataProvider('filasDelSistema')]
    public function test_las_filas_del_sistema_no_se_pueden_dar_de_baja(string $slug, string $protegida): void
    {
        $config = Catalogos::buscar($slug);
        $registro = $config['modelo']::where(Catalogos::columnaTitulo($config), $protegida)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.catalogos.destroy', [$slug, $registro->getKey()]))
            ->assertSessionHas('error');

        $this->assertNull($registro->fresh()->{$config['baja']});
    }

    /**
     * La protección es por fila, no por catálogo entero: el resto de los
     * estados y roles se administran con normalidad.
     */
    #[DataProvider('filasDelSistema')]
    public function test_las_demas_filas_del_mismo_catalogo_si_se_editan(string $slug): void
    {
        $config = Catalogos::buscar($slug);
        $columna = Catalogos::columnaTitulo($config);

        // Creada al momento a propósito: en estado-pedido-material las seis
        // filas sembradas están protegidas, así que no hay ninguna libre.
        $registro = $config['modelo']::create([$columna => 'Fila de prueba']);

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.update', [$slug, $registro->getKey()]), [$columna => 'Fila de prueba revisada'])
            ->assertSessionHas('exito');

        $this->assertSame('Fila de prueba revisada', $registro->fresh()->$columna);

        $this->actingAs($this->admin())
            ->delete(route('admin.catalogos.destroy', [$slug, $registro->getKey()]))
            ->assertSessionHas('exito');

        $this->assertNotNull($registro->fresh()->{$config['baja']});
    }

    public function test_el_listado_marca_las_filas_del_sistema_y_no_ofrece_darlas_de_baja(): void
    {
        $respuesta = $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', 'estado-cirugia'))
            ->assertOk()
            ->assertSee('Del sistema');

        // El botón lo ofrecen todas menos las protegidas. Se calcula en vez de
        // fijarse un número: el catálogo de estados crece con el proyecto.
        $config = Catalogos::buscar('estado-cirugia');
        $libres = $config['modelo']::count() - count($config['protegidos']);

        // Se cuenta el atributo del formulario, que aparece una vez por botón;
        // el texto «Dar de baja» se repite dentro de cada uno.
        $this->assertSame(
            $libres,
            substr_count($respuesta->getContent(), 'data-confirmar-accion="Dar de baja"'),
        );
    }

    public function test_el_formulario_explica_por_que_la_fila_esta_protegida(): void
    {
        $config = Catalogos::buscar('estado-cirugia');
        $realizada = $config['modelo']::where('nombreEstadoCirugia', 'Realizada')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.edit', ['estado-cirugia', $realizada->idEstadoCirugia]))
            ->assertOk()
            ->assertSee('Registro protegido')
            ->assertSee('indicadores')
            ->assertDontSee('Guardar cambios');

        $programada = $config['modelo']::where('nombreEstadoCirugia', 'Programada')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.edit', ['estado-cirugia', $programada->idEstadoCirugia]))
            ->assertOk()
            ->assertDontSee('Registro protegido')
            ->assertSee('Guardar cambios');
    }

    /**
     * El otro extremo del candado: que la lista declarada en el mapa no se
     * desincronice de los nombres que el código realmente busca.
     */
    public function test_toda_fila_protegida_existe_en_el_catalogo(): void
    {
        foreach (Catalogos::todos() as $slug => $config) {
            $protegidos = $config['protegidos'] ?? [];

            if ($protegidos === []) {
                continue;
            }

            $this->assertArrayHasKey('motivoProteccion', $config, "$slug protege filas sin explicar por qué");

            $existentes = $config['modelo']::pluck(Catalogos::columnaTitulo($config))->all();

            foreach ($protegidos as $nombre) {
                $this->assertContains($nombre, $existentes, "{$slug} protege «{$nombre}», que no existe en el catálogo");
            }
        }
    }

    /**
     * Los datos maestros entran por una sola puerta. Antes el menú repetía
     * catálogos sueltos en el nivel superior, duplicando lo que agrupa /admin.
     */
    public function test_el_menu_tiene_una_sola_puerta_a_administracion(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Administración')
            ->assertDontSee('Obras sociales');
    }

    public function test_administracion_queda_marcada_en_todas_sus_pantallas(): void
    {
        foreach (['admin.inicio', 'admin.usuarios.index'] as $ruta) {
            $this->actingAs($this->admin())
                ->get(route($ruta))
                ->assertOk()
                ->assertSee('aria-current="page"', false);
        }

        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', 'tipo-estudio'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_los_demas_roles_no_ven_administracion_en_el_menu(): void
    {
        $this->seed(DemoSeeder::class);

        $this->actingAs(Usuario::where('nombreUsuario', 'perez')->firstOrFail())
            ->get('/cirujano')
            ->assertOk()
            ->assertDontSee('Administración');
    }

    /**
     * La baja se confirma con un <dialog> propio en vez del confirm() del
     * navegador: la vista solo declara los textos y el diálogo vive una sola
     * vez en el layout, no uno por fila.
     */
    public function test_la_baja_pide_confirmacion_con_el_dialogo_propio(): void
    {
        $respuesta = $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', 'tipo-estudio'))
            ->assertOk()
            ->assertSee('data-confirmar-titulo="Dar de baja «Hemograma»"', false)
            ->assertSee('data-confirmar-accion="Dar de baja"', false)
            ->assertDontSee('onsubmit', false);

        // Un solo diálogo aunque el listado tenga varias filas.
        $this->assertSame(1, substr_count($respuesta->getContent(), 'id="dialogo-confirmar"'));
    }

    /**
     * Antes de dar de baja algo conviene saber si está en uso. El conteo sale
     * de las relaciones inversas del modelo, que se reflejan en vez de
     * declararse para que no se desincronicen del grafo de claves foráneas.
     */
    public function test_el_listado_muestra_cuantos_registros_usan_cada_fila(): void
    {
        $this->seed(DemoSeeder::class);

        $respuesta = $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', 'tipo-cirugia'))
            ->assertOk();

        $usados = TipoCirugia::withCount('cirugias')->get()->firstWhere('cirugias_count', '>', 0);

        $this->assertNotNull($usados, 'DemoSeeder debería dejar tipos de cirugía en uso');
        $respuesta->assertSee('data-confirmar="Lo referencian '.$usados->cirugias_count, false);
    }

    public function test_un_tipo_sin_usar_no_ofrece_un_conteo_inventado(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.catalogos.store', 'tipo-estudio'), ['nombreTipoEstudio' => 'Espirometría']);

        // Recién creado, no lo referencia nada: el mensaje va sin la frase.
        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', 'tipo-estudio'))
            ->assertOk()
            ->assertSee('data-confirmar="Deja de ofrecerse', false);
    }

    /**
     * El conteo son subconsultas dentro de la misma consulta, así que el
     * listado no puede crecer en consultas cuando crecen las filas.
     */
    public function test_el_conteo_de_usos_no_dispara_una_consulta_por_fila(): void
    {
        $contar = function (): int {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->actingAs($this->admin())
                ->get(route('admin.catalogos.index', 'tipo-estudio'))
                ->assertOk();
            $n = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $n;
        };

        $contar();                 // descarta el arranque
        $conSeis = $contar();

        foreach (range(1, 15) as $i) {
            TipoEstudio::create(['nombreTipoEstudio' => "Estudio de prueba {$i}"]);
        }

        $this->assertSame($conSeis, $contar(), 'hay un N+1 en el conteo de usos');
    }

    public function test_un_catalogo_inexistente_da_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.catalogos.index', 'inventado'))
            ->assertNotFound();
    }

    public function test_la_seccion_esta_cerrada_a_los_demas_roles(): void
    {
        $this->seed(DemoSeeder::class);

        $cirujano = Usuario::where('nombreUsuario', 'perez')->firstOrFail();

        $this->actingAs($cirujano)->get(route('admin.inicio'))->assertForbidden();
        $this->actingAs($cirujano)->get(route('admin.catalogos.index', 'tipo-estudio'))->assertForbidden();
        $this->actingAs($cirujano)
            ->post(route('admin.catalogos.store', 'tipo-estudio'), ['nombreTipoEstudio' => 'Colado'])
            ->assertForbidden();

        $this->assertDatabaseMissing('TipoEstudio', ['nombreTipoEstudio' => 'Colado']);
    }

    public function test_la_seccion_requiere_autenticacion(): void
    {
        $this->get(route('admin.inicio'))->assertRedirect(route('login'));
    }
}
