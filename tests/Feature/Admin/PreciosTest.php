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

    private function vincular(Material $material, ?float $precio = 1500.50): Vinculo
    {
        $proveedor = $this->proveedorLibre($material);

        $this->actingAs($this->admin())
            ->post(route('admin.precios.proveedores.store', $material), [
                'idProveedor' => $proveedor->idProveedor,
                'codExternoMaterialProveedor' => 'EXT-001',
                'precioExternoMaterialProveedor' => $precio,
            ])
            ->assertSessionHasNoErrors();

        return Vinculo::where('idMaterial', $material->idMaterial)
            ->where('idProveedor', $proveedor->idProveedor)
            ->firstOrFail();
    }

    // --- Proveedores de un material ---

    public function test_se_agrega_un_proveedor_con_su_codigo_y_precio(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        $this->assertSame('EXT-001', $vinculo->codExternoMaterialProveedor);
        $this->assertEqualsWithDelta(1500.50, (float) $vinculo->precioExternoMaterialProveedor, 0.001);

        // Cargar un precio deja sentado cuándo se cargó.
        $this->assertNotNull($vinculo->fechaActualizacionPrecio);
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

    /**
     * La fecha se mueve sola cuando cambia el precio: a mano nadie la
     * mantendría al día, y sin ella no se sabe si el precio está vencido.
     */
    public function test_la_fecha_de_actualizacion_sigue_al_precio(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        $vinculo->update(['fechaActualizacionPrecio' => now()->subMonths(6)]);
        $vieja = $vinculo->fresh()->fechaActualizacionPrecio;

        // Cambiar solo el código no toca la fecha.
        $this->actingAs($this->admin())->put(route('admin.precios.proveedores.update', [$material, $vinculo]), [
            'codExternoMaterialProveedor' => 'EXT-002',
            'precioExternoMaterialProveedor' => 1500.50,
        ]);

        $this->assertEquals($vieja, $vinculo->fresh()->fechaActualizacionPrecio);

        // Cambiar el precio sí.
        $this->actingAs($this->admin())->put(route('admin.precios.proveedores.update', [$material, $vinculo]), [
            'codExternoMaterialProveedor' => 'EXT-002',
            'precioExternoMaterialProveedor' => 1800,
        ]);

        $this->assertTrue($vinculo->fresh()->fechaActualizacionPrecio->greaterThan($vieja));
    }

    public function test_el_precio_no_admite_negativos_ni_texto(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        foreach ([-1, 'gratis'] as $invalido) {
            $this->actingAs($this->admin())
                ->put(route('admin.precios.proveedores.update', [$material, $vinculo]), [
                    'precioExternoMaterialProveedor' => $invalido,
                ])
                ->assertSessionHasErrors('precioExternoMaterialProveedor');
        }
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
     * PedidoMaterial guarda su propio material, proveedor, unidad y subtotal.
     * Tocar el catálogo de precios no reescribe lo que ya se pidió.
     */
    public function test_cambiar_precios_no_altera_los_pedidos_ya_hechos(): void
    {
        $pedido = PedidoMaterial::whereNotNull('subtotalPedidoMaterial')->firstOrFail();
        $subtotal = $pedido->subtotalPedidoMaterial;

        $material = Material::findOrFail($pedido->idMaterial);
        $vinculo = $this->vincular($material, precio: 99999);

        $this->actingAs($this->admin())->delete(route('admin.precios.proveedores.destroy', [$material, $vinculo]));

        $this->assertEquals($subtotal, $pedido->fresh()->subtotalPedidoMaterial);
    }

    // --- Unidades de venta ---

    private function agregarMedida(Material $material, Vinculo $vinculo): Medida
    {
        $medida = TipoMedida::orderBy('idTipoMedida')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.precios.medidas.store', [$material, $vinculo]), [
                'idTipoMedida' => $medida->idTipoMedida,
            ])
            ->assertSessionHasNoErrors();

        return Medida::where('idMaterialProveedor', $vinculo->idMaterialProveedor)
            ->whereNull('fechaFinAsignacionMaterialTipoMedida')
            ->firstOrFail();
    }

    public function test_se_agrega_una_unidad_de_venta_vigente_y_disponible(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);

        $medida = $this->agregarMedida($material, $vinculo);

        $this->assertNotNull($medida->fechaAsignacionMaterialTipoMedida);
        $this->assertNull($medida->fechaFinAsignacionMaterialTipoMedida);
        $this->assertTrue((bool) $medida->disponibleMaterialTipoMedida);
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
     * Sin stock no es lo mismo que no venderlo así: son dos columnas distintas
     * y la pantalla las trata distinto.
     */
    public function test_marcar_sin_stock_es_distinto_de_dar_de_baja_la_unidad(): void
    {
        $material = $this->material();
        $vinculo = $this->vincular($material);
        $medida = $this->agregarMedida($material, $vinculo);

        $this->actingAs($this->admin())
            ->put(route('admin.precios.medidas.update', [$material, $vinculo, $medida]));

        $medida->refresh();
        $this->assertFalse((bool) $medida->disponibleMaterialTipoMedida);
        $this->assertNull($medida->fechaFinAsignacionMaterialTipoMedida, 'seguir asignada');

        $this->actingAs($this->admin())
            ->delete(route('admin.precios.medidas.destroy', [$material, $vinculo, $medida]));

        $this->assertNotNull($medida->fresh()->fechaFinAsignacionMaterialTipoMedida);
        // Cerrada, no borrada: queda hasta cuándo se vendió así.
        $this->assertNotNull(Medida::find($medida->idMaterialProveedorTipoMedida));
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
        Material::create(['nombreMaterial' => 'Malla de polipropileno']);

        $this->actingAs($this->admin())
            ->get(route('admin.precios.index'))
            ->assertOk()
            ->assertSee('Malla de polipropileno')
            ->assertSee('Sin proveedor');
    }

    public function test_la_pantalla_del_material_no_ofrece_proveedores_ya_cargados(): void
    {
        // Material propio: DemoSeeder ya vincula proveedores a los sembrados.
        $material = Material::create(['nombreMaterial' => 'Grapadora lineal']);
        $vinculo = $this->vincular($material);
        $libre = Proveedor::create(['nombreProveedor' => 'Insumos del Sur']);

        $respuesta = $this->actingAs($this->admin())
            ->get(route('admin.precios.show', $material))
            ->assertOk()
            ->assertSee($vinculo->proveedor->nombreProveedor);

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

        $this->actingAs($this->admin())->put(route('admin.precios.proveedores.update', [$material, $vinculo]), [
            'precioExternoMaterialProveedor' => 2000,
        ]);

        $registro = Auditoria::orderByDesc('idAuditoria')->firstOrFail();

        $this->assertSame('MaterialProveedor', $registro->tablaAuditoria);
        $this->assertArrayHasKey('precioExternoMaterialProveedor', $registro->cambiosAuditoria);
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
