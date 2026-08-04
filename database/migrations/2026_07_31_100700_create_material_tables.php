<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Material', function (Blueprint $table) {
            $table->id('idMaterial');
            $table->string('nombreMaterial', 180);
            $table->string('codMaterial', 60)->nullable();
            $table->dateTime('fechaBajaMaterial')->nullable();
        });

        Schema::create('Proveedor', function (Blueprint $table) {
            $table->id('idProveedor');
            $table->string('nombreProveedor', 180);
            $table->string('cuitProveedor', 13)->nullable();
            $table->string('telefonoProveedor', 30)->nullable();
            $table->dateTime('fechaBajaProveedor')->nullable();
        });

        // Que proveedores venden cada material. El precio no vive aca: un
        // proveedor cobra distinto segun la unidad en que lo venda, asi que
        // cuelga de MaterialProveedorTipoMedida.
        Schema::create('MaterialProveedor', function (Blueprint $table) {
            $table->id('idMaterialProveedor');
            $table->unsignedBigInteger('idMaterial');
            $table->unsignedBigInteger('idProveedor');

            $table->foreign('idMaterial', 'fk_materialproveedor_material')
                ->references('idMaterial')->on('Material');
            $table->foreign('idProveedor', 'fk_materialproveedor_proveedor')
                ->references('idProveedor')->on('Proveedor');
        });

        Schema::create('TipoMedida', function (Blueprint $table) {
            $table->id('idTipoMedida');
            $table->string('nombreTipoMedida', 120);
            $table->dateTime('fechaBajaTipoMedida')->nullable();
        });

        Schema::create('MaterialProveedorTipoMedida', function (Blueprint $table) {
            $table->id('idMaterialProveedorTipoMedida');
            $table->unsignedBigInteger('idMaterialProveedor');
            $table->unsignedBigInteger('idTipoMedida');
            $table->dateTime('fechaAsignacionMaterialTipoMedida')->nullable();
            $table->dateTime('fechaFinAsignacionMaterialTipoMedida')->nullable();
            $table->boolean('disponibleMaterialTipoMedida')->default(true);
            // Lo que el proveedor cobra por esta presentacion, y su codigo en
            // el catalogo de ese proveedor: son dos identificaciones del mismo
            // articulo facturable (implante de 0,5 m y de 1 m no son lo mismo).
            $table->string('codExternoMaterialProveedorTipoMedida', 60)->nullable();
            $table->decimal('precioExternoMaterialProveedorTipoMedida', 12, 2)->nullable();
            $table->dateTime('fechaActualizacionPrecioMaterialProveedorTipoMedida')->nullable();

            $table->foreign('idMaterialProveedor', 'fk_mptm_materialproveedor')
                ->references('idMaterialProveedor')->on('MaterialProveedor');
            $table->foreign('idTipoMedida', 'fk_mptm_tipomedida')
                ->references('idTipoMedida')->on('TipoMedida');
        });

        Schema::create('EstadoPedidoMaterial', function (Blueprint $table) {
            $table->id('idEstadoPedidoMaterial');
            $table->string('nombreEstadoPedidoMaterial', 120);
            $table->dateTime('fechaBajaEstadoPedidoMaterial')->nullable();
        });

        Schema::create('PedidoMaterial', function (Blueprint $table) {
            $table->id('idPedidoMaterial');
            $table->unsignedBigInteger('idCirugia');
            $table->unsignedBigInteger('idMaterial');
            $table->unsignedBigInteger('idPlan')->nullable();
            $table->unsignedBigInteger('idProveedor')->nullable();
            $table->unsignedBigInteger('idTipoMedida')->nullable();
            $table->integer('cantidadPedidoMaterial')->default(1);
            $table->string('observacionesPedidoMaterial', 255)->nullable();
            // Copia del precio al momento del pedido: editar la cantidad de un
            // pedido viejo no puede traerse la lista de precios de hoy.
            $table->decimal('precioUnitarioPedidoMaterial', 12, 2)->nullable();
            $table->decimal('subtotalPedidoMaterial', 12, 2)->nullable();
            $table->dateTime('fechaPedidoMaterial')->nullable();

            $table->foreign('idCirugia', 'fk_pedidomaterial_cirugia')
                ->references('idCirugia')->on('Cirugia');
            $table->foreign('idMaterial', 'fk_pedidomaterial_material')
                ->references('idMaterial')->on('Material');
            $table->foreign('idPlan', 'fk_pedidomaterial_plan')
                ->references('idPlan')->on('Plan');
            $table->foreign('idProveedor', 'fk_pedidomaterial_proveedor')
                ->references('idProveedor')->on('Proveedor');
            $table->foreign('idTipoMedida', 'fk_pedidomaterial_tipomedida')
                ->references('idTipoMedida')->on('TipoMedida');
        });

        Schema::create('PedidoMaterialEstado', function (Blueprint $table) {
            $table->id('idPedidoMaterialEstado');
            $table->unsignedBigInteger('idPedidoMaterial');
            $table->unsignedBigInteger('idEstadoPedidoMaterial');
            $table->string('observacionesPedidoMaterialEstado', 255)->nullable();

            $table->foreign('idPedidoMaterial', 'fk_pme_pedidomaterial')
                ->references('idPedidoMaterial')->on('PedidoMaterial');
            $table->foreign('idEstadoPedidoMaterial', 'fk_pme_estado')
                ->references('idEstadoPedidoMaterial')->on('EstadoPedidoMaterial');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PedidoMaterialEstado');
        Schema::dropIfExists('PedidoMaterial');
        Schema::dropIfExists('EstadoPedidoMaterial');
        Schema::dropIfExists('MaterialProveedorTipoMedida');
        Schema::dropIfExists('TipoMedida');
        Schema::dropIfExists('MaterialProveedor');
        Schema::dropIfExists('Proveedor');
        Schema::dropIfExists('Material');
    }
};
