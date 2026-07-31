<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TipoHemoderivado', function (Blueprint $table) {
            $table->id('idTipoHemoderivado');
            $table->string('nombreTipoHemoderivado', 180);
            $table->dateTime('fechaBajaTipoHemoderivado')->nullable();
        });

        Schema::create('Establecimiento', function (Blueprint $table) {
            $table->id('idEstablecimiento');
            $table->string('nombreEstablecimiento', 180);
            $table->string('descripcionEstablecimiento', 255)->nullable();
            $table->string('codTelefonoEstablecimiento', 10)->nullable();
            $table->string('numeroTelefonoEstablecimiento', 20)->nullable();
            $table->string('emailEstablecimiento', 120)->nullable();
            $table->dateTime('fechaEstablecimiento')->nullable();
        });

        Schema::create('EstadoPedidoHemoderivado', function (Blueprint $table) {
            $table->id('idEstadoPedidoHemoderivado');
            $table->string('nombreEstadoPedidoHemoderivado', 120);
            $table->dateTime('fechaBajaEstadoPedidoHemoderivado')->nullable();
        });

        Schema::create('EstadoPedidoTipoHemoderivado', function (Blueprint $table) {
            $table->id('idEstadoPedidoTipoHemoderivado');
            $table->string('nombreEstadoPedidoTipoHemoderivado', 120);
            $table->dateTime('fechaBajaEstadoPedidoTipoHemoderivado')->nullable();
        });

        Schema::create('PedidoHemoderivado', function (Blueprint $table) {
            $table->id('idPedidoHemoderivado');
            $table->unsignedBigInteger('idCirugia');
            $table->string('observacionesPedidoHemoderivados', 255)->nullable();
            $table->dateTime('fechaPedidoHemoderivado')->nullable();

            $table->foreign('idCirugia', 'fk_pedidohemoderivado_cirugia')
                ->references('idCirugia')->on('Cirugia');
        });

        Schema::create('PedidoTipoHemoderivado', function (Blueprint $table) {
            $table->id('idPedidoTipoHemoderivado');
            $table->unsignedBigInteger('idPedidoHemoderivado');
            $table->unsignedBigInteger('idTipoHemoderivado');
            $table->unsignedBigInteger('idEstablecimiento')->nullable();
            $table->integer('cantidadPedidoTipoHemoderivado')->default(1);
            $table->string('descripcionPedidoTipoHemoderivado', 255)->nullable();

            $table->foreign('idPedidoHemoderivado', 'fk_pth_pedidohemoderivado')
                ->references('idPedidoHemoderivado')->on('PedidoHemoderivado');
            $table->foreign('idTipoHemoderivado', 'fk_pth_tipohemoderivado')
                ->references('idTipoHemoderivado')->on('TipoHemoderivado');
            $table->foreign('idEstablecimiento', 'fk_pth_establecimiento')
                ->references('idEstablecimiento')->on('Establecimiento');
        });

        Schema::create('PedidoHemoderivadoEstado', function (Blueprint $table) {
            $table->id('idPedidoHemoderivadoEstado');
            $table->unsignedBigInteger('idPedidoHemoderivado');
            $table->unsignedBigInteger('idEstadoPedidoHemoderivado');
            $table->dateTime('fechaAsignacionPedidoHemoderivadoEstado')->nullable();
            $table->dateTime('fechaFinAsignacionPedidoHemoderivadoEstado')->nullable();

            $table->foreign('idPedidoHemoderivado', 'fk_phe_pedidohemoderivado')
                ->references('idPedidoHemoderivado')->on('PedidoHemoderivado');
            $table->foreign('idEstadoPedidoHemoderivado', 'fk_phe_estado')
                ->references('idEstadoPedidoHemoderivado')->on('EstadoPedidoHemoderivado');
        });

        Schema::create('PedidoTipoHemoderivadoEstado', function (Blueprint $table) {
            $table->id('idPedidoTipoHemoderivadoEstado');
            $table->unsignedBigInteger('idPedidoTipoHemoderivado');
            $table->unsignedBigInteger('idEstadoPedidoTipoHemoderivado');
            $table->dateTime('fechaAsignacionPedidoTipoHemoderivadoEstado')->nullable();
            $table->dateTime('fechaFinAsignacionPedidoTipoHemoderivadoEstado')->nullable();

            $table->foreign('idPedidoTipoHemoderivado', 'fk_pthe_pedidotipohemoderivado')
                ->references('idPedidoTipoHemoderivado')->on('PedidoTipoHemoderivado');
            $table->foreign('idEstadoPedidoTipoHemoderivado', 'fk_pthe_estado')
                ->references('idEstadoPedidoTipoHemoderivado')->on('EstadoPedidoTipoHemoderivado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PedidoTipoHemoderivadoEstado');
        Schema::dropIfExists('PedidoHemoderivadoEstado');
        Schema::dropIfExists('PedidoTipoHemoderivado');
        Schema::dropIfExists('PedidoHemoderivado');
        Schema::dropIfExists('EstadoPedidoTipoHemoderivado');
        Schema::dropIfExists('EstadoPedidoHemoderivado');
        Schema::dropIfExists('Establecimiento');
        Schema::dropIfExists('TipoHemoderivado');
    }
};
