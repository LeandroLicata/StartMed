<?php

namespace Tests\Feature\Admin;

use App\Models\Auditoria;
use App\Models\Material;
use App\Models\MaterialProveedor as Vinculo;
use App\Models\MaterialProveedorTipoMedida as Medida;
use App\Models\PedidoMaterial;
use App\Models\Proveedor;
use App\Models\TipoMedida;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreciosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    private function admin(): Usuario
    {
        return Usuario::where('nombreUsuario', 'admin')->firstOrFail();
    }

    private function material(): Material
    {
        return Material::orderBy('idMaterial')->firstOrFail();
    }

    /**
     * Uno nuevo en vez de buscar entre los sembrados: DemoSeeder ya vincula
     * proveedores con materiales, y el test no debería depender de cuáles.
     */
    private function proveedorLibre(Material $material): Proveedor
    {
        return Proveedor::create(['nombreProveedor' => 'Insumos del Oeste '.Proveedor::count()]);
    }

    private function vincular(Material $material): Vinculo
    {
        $proveedor = $this->proveedorLibre($material);

        $this->actingAs($this->admin())
            ->post(route('admin.precios.proveedores.store', $material), [
                'idProveedor' => $proveedor->idProveedor,
            ])
            ->assertSessionHasNoErrors();

        return Vinculo::where('idMaterial', $material->idMaterial)
            ->where('idProveedor', $proveedor->idProveedor)
            ->firstOrFail();
    }

    private function agregarMedida(
        Material $material,
        Vinculo $vinculo,
        ?string $unidad = null,
        ?float $precio = 1500.50,
        string $codigo = 'EXT-001',
    ): Medida {
        $tipo = $unidad
            ? TipoMedida::where('nombreTipoMedida', $unidad)->firstOrFail()
            : TipoMedida::orderBy('idTipoMedida')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.precios.medidas.store', [$material, $vinculo]), [
                'idTipoMedida' => $tipo->idTipoMedida,
                'codExternoMaterialProveedorTipoMedida' => $codigo,
                'precioExternoMaterialProveedorTipoMedida' => $precio,
            ])
            ->assertSessionHasNoErrors();

        return Medida::where('idMaterialProveedor', $vinculo->idMaterialProveedor)
            ->where('idTipoMedida', $tipo->idTipoMedida)
            ->whereNull('fechaFinAsignacionMaterialTipoMedida')
            ->firstOrFail();
    }

    // --- Proveedores de un material ---

    public function test_el_vinculo_con_el_proveedor_no_guarda_precio(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        // El precio depende de la unidad, así que el vínculo es solo eso: qué
        // proveedor vende qué material.
        $this->assertSame(
            ['idMaterialProveedor', 'idMaterial', 'idProveedor'],
            array_keys($vinculo->fresh()->getAttributes()),
        );
    }

    public function test_el_mismo_proveedor_no_se_carga_dos_veces_para_un_material(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        $this->actingAs($this->admin())
            ->post(route('admin.precios.proveedores.store', $material), [
                'idProveedor' => $vinculo->idProveedor,
            ])
            ->assertSessionHasErrors('idProveedor');
    }

    public function test_quitar_un_proveedor_se_lleva_sus_unidades(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $this->agregarMedida($material, $vinculo);

        $this->actingAs($this->admin())
            ->delete(route('admin.precios.proveedores.destroy', [$material, $vinculo]))
            ->assertSessionHas('exito');

        $this->assertNull(Vinculo::find($vinculo->idMaterialProveedor));
        $this->assertSame(0, Medida::where('idMaterialProveedor', $vinculo->idMaterialProveedor)->count());
    }

    /**
     * PedidoMaterial guarda su propio material, proveedor, unidad, unitario y
     * subtotal. Tocar el catálogo de precios no reescribe lo que ya se pidió.
     */
    public function test_cambiar_precios_no_altera_los_pedidos_ya_hechos(): void
    {
        $pedido = PedidoMaterial::whereNotNull('subtotalPedidoMaterial')->firstOrFail();
        $subtotal = $pedido->subtotalPedidoMaterial;

        $material = Material::findOrFail($pedido->idMaterial);
        $vinculo = $this->vincular($material);
        $this->agregarMedida($material, $vinculo, precio: 99999);

        $this->actingAs($this->admin())->delete(route('admin.precios.proveedores.destroy', [$material, $vinculo]));

        $this->assertEquals($subtotal, $pedido->fresh()->subtotalPedidoMaterial);
    }

    // --- Unidades de venta, con su precio ---

    public function test_se_agrega_una_unidad_con_su_codigo_y_su_precio(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        $medida = $this->agregarMedida($material, $vinculo);

        $this->assertSame('EXT-001', $medida->codExternoMaterialProveedorTipoMedida);
        $this->assertEqualsWithDelta(1500.50, (float) $medida->precioExternoMaterialProveedorTipoMedida, 0.001);

        $this->assertNotNull($medida->fechaAsignacionMaterialTipoMedida);
        $this->assertNull($medida->fechaFinAsignacionMaterialTipoMedida);
        $this->assertTrue((bool) $medida->disponibleMaterialTipoMedida);

        // Cargar un precio deja sentado cuándo se cargó.
        $this->assertNotNull($medida->fechaActualizacionPrecioMaterialProveedorTipoMedida);
    }

    /**
     * El motivo de todo esto: el mismo proveedor cobra distinto según la
     * presentación que venda, así que el precio no puede colgar del vínculo.
     */
    public function test_un_proveedor_cobra_distinto_segun_la_unidad(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        $unidad = $this->agregarMedida($material, $vinculo, 'Unidad', 10000, 'IMP-05');
        $caja = $this->agregarMedida($material, $vinculo, 'Caja', 20000, 'IMP-10');

        $this->assertEqualsWithDelta(10000, (float) $unidad->precioExternoMaterialProveedorTipoMedida, 0.001);
        $this->assertEqualsWithDelta(20000, (float) $caja->precioExternoMaterialProveedorTipoMedida, 0.001);
        $this->assertNotSame(
            $unidad->codExternoMaterialProveedorTipoMedida,
            $caja->codExternoMaterialProveedorTipoMedida,
        );
    }

    public function test_la_misma_unidad_no_se_asigna_dos_veces_a_la_vez(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo);

        $this->actingAs($this->admin())
            ->post(route('admin.precios.medidas.store', [$material, $vinculo]), [
                'idTipoMedida' => $medida->idTipoMedida,
            ])
            ->assertSessionHasErrors('idTipoMedida');
    }

    /**
     * La fecha se mueve sola cuando cambia el precio: a mano nadie la
     * mantendría al día, y sin ella no se sabe si el precio está vencido.
     */
    public function test_la_fecha_de_actualizacion_sigue_al_precio(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo);

        $medida->update(['fechaActualizacionPrecioMaterialProveedorTipoMedida' => now()->subMonths(6)]);
        $vieja = $medida->fresh()->fechaActualizacionPrecioMaterialProveedorTipoMedida;

        // Cambiar solo el código no toca la fecha.
        $this->actingAs($this->admin())->put(route('admin.precios.medidas.update', [$material, $vinculo, $medida]), [
            'codExternoMaterialProveedorTipoMedida' => 'EXT-002',
            'precioExternoMaterialProveedorTipoMedida' => 1500.50,
        ]);

        $this->assertEquals($vieja, $medida->fresh()->fechaActualizacionPrecioMaterialProveedorTipoMedida);

        // Cambiar el precio sí.
        $this->actingAs($this->admin())->put(route('admin.precios.medidas.update', [$material, $vinculo, $medida]), [
            'codExternoMaterialProveedorTipoMedida' => 'EXT-002',
            'precioExternoMaterialProveedorTipoMedida' => 1800,
        ]);

        $this->assertTrue(
            $medida->fresh()->fechaActualizacionPrecioMaterialProveedorTipoMedida->greaterThan($vieja)
        );
    }

    public function test_el_precio_no_admite_negativos_ni_texto(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo);

        foreach ([-1, 'gratis'] as $invalido) {
            $this->actingAs($this->admin())
                ->put(route('admin.precios.medidas.update', [$material, $vinculo, $medida]), [
                    'precioExternoMaterialProveedorTipoMedida' => $invalido,
                ])
                ->assertSessionHasErrors('precioExternoMaterialProveedorTipoMedida');
        }
    }

    /**
     * La pantalla repite los mismos campos en una fila por unidad, y old() y
     * los errores son globales a la página: sin acotarlos, un precio rechazado
     * en una fila reaparece marcado en rojo en todas las demás.
     */
    public function test_un_error_en_una_fila_no_ensucia_las_demas(): void
    {
        $material = Material::create(['nombreMaterial' => 'Tornillo canulado']);
        $vinculo = $this->vincular($material);
        $this->agregarMedida($material, $vinculo, 'Unidad', 10000);
        $caja = $this->agregarMedida($material, $vinculo, 'Caja', 20000);

        $this->actingAs($this->admin())
            ->put(route('admin.precios.medidas.update', [$material, $vinculo, $caja]), [
                'fila' => 'medida-'.$caja->idMaterialProveedorTipoMedida,
                'precioExternoMaterialProveedorTipoMedida' => -1,
            ]);

        // Sin assertSessionHasErrors() en el medio a propósito: esa aserción
        // consume el flash y la pantalla ya no vería el error. El error
        // renderizado es la verificación, y es más fuerte.
        $html = $this->actingAs($this->admin())
            ->get(route('admin.precios.show', $material))
            ->assertOk()
            ->getContent();

        // La fila que falló repuebla con lo tipeado; la otra queda como estaba.
        $this->assertStringContainsString('value="-1"', $html);
        $this->assertStringContainsString('value="10000.00"', $html);

        // Y el error se dibuja una sola vez, no en las tres filas de la página.
        $this->assertSame(1, substr_count($html, 'id="precioExternoMaterialProveedorTipoMedida-error"'));
        $this->assertSame(1, substr_count($html, 'border-red-600'));
    }

    /**
     * La fila tiene dos campos: decir "Precio actualizado" después de corregir
     * un código es mentira.
     */
    public function test_el_aviso_dice_que_fue_lo_que_cambio(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo);

        $campos = [
            'codExternoMaterialProveedorTipoMedida' => 'EXT-999',
            'precioExternoMaterialProveedorTipoMedida' => 1500.50,
        ];
        $ruta = route('admin.precios.medidas.update', [$material, $vinculo, $medida]);

        $this->actingAs($this->admin())->put($ruta, $campos)
            ->assertSessionHas('exito', 'Código actualizado.');

        $this->actingAs($this->admin())->put($ruta, [...$campos, 'precioExternoMaterialProveedorTipoMedida' => 1800])
            ->assertSessionHas('exito', 'Precio actualizado.');

        // Guardar sin tocar nada no miente ni ensucia la auditoría.
        $this->actingAs($this->admin())->put($ruta, [...$campos, 'precioExternoMaterialProveedorTipoMedida' => 1800])
            ->assertSessionHas('info', 'No había nada que cambiar.');
    }

    /**
     * Sin stock no es lo mismo que no venderlo así: son dos columnas distintas
     * y la pantalla las trata distinto.
     */
    public function test_marcar_sin_stock_es_distinto_de_dar_de_baja_la_unidad(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo);

        $this->actingAs($this->admin())
            ->patch(route('admin.precios.medidas.disponibilidad', [$material, $vinculo, $medida]));

        $medida->refresh();
        $this->assertFalse((bool) $medida->disponibleMaterialTipoMedida);
        $this->assertNull($medida->fechaFinAsignacionMaterialTipoMedida, 'seguir asignada');

        $this->actingAs($this->admin())
            ->delete(route('admin.precios.medidas.destroy', [$material, $vinculo, $medida]));

        $this->assertNotNull($medida->fresh()->fechaFinAsignacionMaterialTipoMedida);
        // Cerrada, no borrada: queda hasta cuándo se vendió así, y a qué precio.
        $this->assertNotNull(Medida::find($medida->idMaterialProveedorTipoMedida));
        $this->assertNotNull($medida->fresh()->precioExternoMaterialProveedorTipoMedida);
    }

    public function test_una_unidad_dada_de_baja_se_puede_volver_a_asignar(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo);

        $this->actingAs($this->admin())->delete(route('admin.precios.medidas.destroy', [$material, $vinculo, $medida]));
        $this->agregarMedida($material, $vinculo);

        // Dos filas: una cerrada y una vigente.
        $filas = Medida::where('idMaterialProveedor', $vinculo->idMaterialProveedor)->get();

        $this->assertCount(2, $filas);
        $this->assertCount(1, $filas->whereNull('fechaFinAsignacionMaterialTipoMedida'));
    }

    // --- Pantallas, auditoría y acceso ---

    public function test_el_listado_marca_los_materiales_sin_proveedor(): void
    {
        // Uno recién creado: sin proveedor no hay a quién pedírselo.
        Material::create(['nombreMaterial' => 'Grapadora circular']);

        $this->actingAs($this->admin())
            ->get(route('admin.precios.index'))
            ->assertOk()
            ->assertSee('Grapadora circular')
            ->assertSee('Sin proveedor');
    }

    /**
     * Un proveedor cargado pero sin ninguna unidad con precio tampoco sirve
     * para pedir: el listado tiene que distinguirlo de "no hay proveedor".
     */
    public function test_el_listado_marca_los_materiales_sin_precio(): void
    {
        $material = Material::create(['nombreMaterial' => 'Grapadora lineal']);
        $this->vincular($material);

        $this->actingAs($this->admin())
            ->get(route('admin.precios.index'))
            ->assertOk()
            ->assertSee('Sin precio');
    }

    public function test_el_listado_muestra_el_rango_de_precios_del_material(): void
    {
        $material = Material::create(['nombreMaterial' => 'Clavo endomedular']);
        $vinculo = $this->vincular($material);
        $this->agregarMedida($material, $vinculo, 'Unidad', 10000);
        $this->agregarMedida($material, $vinculo, 'Caja', 20000);

        $this->actingAs($this->admin())
            ->get(route('admin.precios.index'))
            ->assertOk()
            ->assertSee('desde')
            ->assertSee('10.000,00');
    }

    /**
     * El catálogo de materiales de un hospital son cientos de filas, así que el
     * listado pagina. Y por eso mismo trae buscador: con solo paginar, dar con
     * un material sería pasar páginas hasta la letra.
     */
    public function test_el_listado_de_materiales_pagina_y_se_busca(): void
    {
        foreach (range(1, 30) as $n) {
            Material::create(['nombreMaterial' => 'Sutura de prueba '.str_pad($n, 2, '0', STR_PAD_LEFT)]);
        }

        $this->actingAs($this->admin())
            ->get(route('admin.precios.index'))
            ->assertOk()
            ->assertSee('Sutura de prueba 01')
            ->assertDontSee('Sutura de prueba 30')
            ->assertSee('page=2');

        $this->actingAs($this->admin())
            ->get(route('admin.precios.index', ['q' => 'prueba 30']))
            ->assertOk()
            ->assertSee('Sutura de prueba 30')
            ->assertDontSee('Sutura de prueba 01');
    }

    public function test_la_pantalla_del_material_no_ofrece_proveedores_ya_cargados(): void
    {
        // Material propio: DemoSeeder ya vincula proveedores a los sembrados.
        $material = Material::create(['nombreMaterial' => 'Grapadora curva']);
        $vinculo = $this->vincular($material);
        $this->agregarMedida($material, $vinculo, 'Unidad', 1500.50, 'EXT-042');
        $libre = Proveedor::create(['nombreProveedor' => 'Insumos del Sur']);

        $respuesta = $this->actingAs($this->admin())
            ->get(route('admin.precios.show', $material))
            ->assertOk()
            ->assertSee($vinculo->proveedor->nombreProveedor)
            // La fila de la unidad trae su propio código y su propio precio.
            ->assertSee('EXT-042')
            ->assertSee('1500.50');

        // Acotado al select de proveedores: un value="4" suelto también
        // aparece en el desplegable de unidades.
        preg_match('/<select[^>]*name="idProveedor".*?<\/select>/s', $respuesta->getContent(), $select);

        $this->assertNotEmpty($select, 'debería quedar el desplegable de proveedores');
        $this->assertStringContainsString('value="'.$libre->idProveedor.'"', $select[0], 'el libre sí se ofrece');
        $this->assertStringNotContainsString('value="'.$vinculo->idProveedor.'"', $select[0], 'el ya cargado no');
    }

    public function test_los_cambios_de_precio_quedan_auditados(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo, 'Unidad');

        $this->actingAs($this->admin())->put(route('admin.precios.medidas.update', [$material, $vinculo, $medida]), [
            'precioExternoMaterialProveedorTipoMedida' => 2000,
        ]);

        $registro = Auditoria::orderByDesc('idAuditoria')->firstOrFail();

        $this->assertSame('MaterialProveedorTipoMedida', $registro->tablaAuditoria);
        $this->assertArrayHasKey('precioExternoMaterialProveedorTipoMedida', $registro->cambiosAuditoria);

        // Nombra la unidad: si no, tres precios del mismo material quedan como
        // tres renglones idénticos.
        $this->assertStringContainsString('Unidad', $registro->descripcionAuditoria);
    }

    public function test_la_seccion_esta_cerrada_a_los_demas_roles(): void
    {
        $cirujano = Usuario::where('nombreUsuario', 'perez')->firstOrFail();

        $this->actingAs($cirujano)->get(route('admin.precios.index'))->assertForbidden();
        $this->actingAs($cirujano)
            ->post(route('admin.precios.proveedores.store', $this->material()), ['idProveedor' => 1])
            ->assertForbidden();
    }
}
