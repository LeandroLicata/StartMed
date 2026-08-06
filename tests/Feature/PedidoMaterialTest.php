<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\Material;
use App\Models\MaterialProveedorTipoMedida as Medida;
use App\Models\PedidoMaterial;
use App\Models\Proveedor;
use App\Models\TipoMedida;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Lo que cuesta un pedido sale del precio de la unidad pedida, no del
 * proveedor: el mismo material en dos presentaciones son dos precios.
 */
class PedidoMaterialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    private function gestor(): Usuario
    {
        return Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
    }

    private function cirugia(): Cirugia
    {
        return Cirugia::orderBy('idCirugia')->firstOrFail();
    }

    /** La malla es la que DemoSeeder vende suelta y por caja, y no tiene pedidos. */
    private function malla(): Material
    {
        return Material::where('nombreMaterial', 'Malla de polipropileno')->firstOrFail();
    }

    private function proveedor(): Proveedor
    {
        return Proveedor::where('nombreProveedor', 'Implantes Cuyo S.A.')->firstOrFail();
    }

    private function unidad(string $nombre): int
    {
        return TipoMedida::where('nombreTipoMedida', $nombre)->value('idTipoMedida');
    }

    private function pedir(string $unidad, int $cantidad): TestResponse
    {
        return $this->actingAs($this->gestor())
            ->post(route('cirugias.materiales.store', $this->cirugia()), [
                'idMaterial' => $this->malla()->idMaterial,
                'idProveedor' => $this->proveedor()->idProveedor,
                'idTipoMedida' => $this->unidad($unidad),
                'cantidadPedidoMaterial' => $cantidad,
            ]);
    }

    private function ultimoPedido(): PedidoMaterial
    {
        return PedidoMaterial::orderByDesc('idPedidoMaterial')->firstOrFail();
    }

    public function test_el_subtotal_sale_del_precio_de_la_unidad_pedida(): void
    {
        $this->pedir('Unidad', 2)->assertSessionHasNoErrors();
        $suelta = $this->ultimoPedido();

        $this->pedir('Caja', 2)->assertSessionHasNoErrors();
        $porCaja = $this->ultimoPedido();

        // Misma cantidad y mismo proveedor: lo único que cambia es la unidad.
        $this->assertEqualsWithDelta(180.00, (float) $suelta->precioUnitarioPedidoMaterial, 0.001);
        $this->assertEqualsWithDelta(360.00, (float) $suelta->subtotalPedidoMaterial, 0.001);

        $this->assertEqualsWithDelta(1620.00, (float) $porCaja->precioUnitarioPedidoMaterial, 0.001);
        $this->assertEqualsWithDelta(3240.00, (float) $porCaja->subtotalPedidoMaterial, 0.001);
    }

    public function test_editar_la_cantidad_usa_el_unitario_congelado(): void
    {
        $this->pedir('Unidad', 2)->assertSessionHasNoErrors();
        $pedido = $this->ultimoPedido();

        // El proveedor aumenta después de que el pedido se hizo.
        Medida::query()
            ->where('idTipoMedida', $this->unidad('Unidad'))
            ->whereHas('materialProveedor', fn ($q) => $q->where('idMaterial', $this->malla()->idMaterial))
            ->update(['precioExternoMaterialProveedorTipoMedida' => 999]);

        $this->actingAs($this->gestor())
            ->put(route('cirugias.materiales.update', [$this->cirugia(), $pedido]), [
                'cantidadPedidoMaterial' => 3,
            ])
            ->assertSessionHasNoErrors();

        $pedido->refresh();

        $this->assertEqualsWithDelta(180.00, (float) $pedido->precioUnitarioPedidoMaterial, 0.001);
        $this->assertEqualsWithDelta(540.00, (float) $pedido->subtotalPedidoMaterial, 0.001);
    }

    /**
     * Sin precio el subtotal sería cero y nadie lo volvería a mirar, así que
     * frena en la validación.
     */
    public function test_no_se_puede_pedir_una_unidad_sin_precio_cargado(): void
    {
        $antes = PedidoMaterial::count();

        $this->pedir('Par', 1)->assertSessionHasErrors('idTipoMedida');

        $this->assertSame($antes, PedidoMaterial::count());
    }

    public function test_una_unidad_dada_de_baja_ya_no_se_puede_pedir(): void
    {
        Medida::query()
            ->where('idTipoMedida', $this->unidad('Caja'))
            ->whereHas('materialProveedor', fn ($q) => $q->where('idMaterial', $this->malla()->idMaterial))
            ->update(['fechaFinAsignacionMaterialTipoMedida' => now()]);

        $this->pedir('Caja', 1)->assertSessionHasErrors('idTipoMedida');
    }
}
