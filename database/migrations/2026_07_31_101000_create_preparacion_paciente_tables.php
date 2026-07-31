<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TipoPreparacion', function (Blueprint $table) {
            $table->id('idTipoPreparacion');
            $table->string('nombreTipoPreparacion', 120);
            $table->dateTime('fechaBajaTipoPreparacion')->nullable();
        });

        Schema::create('TipoIndicacion', function (Blueprint $table) {
            $table->id('idTipoIndicacion');
            $table->string('nombreTipoIndicacion', 120);
            $table->dateTime('fechaBajaTipoIndicacion')->nullable();
        });

        Schema::create('PreparacionPaciente', function (Blueprint $table) {
            $table->id('idPreparacionPaciente');
            $table->unsignedBigInteger('idCirugia');
            $table->string('observacionesPreparacionPaciente', 255)->nullable();

            $table->foreign('idCirugia', 'fk_preparacionpaciente_cirugia')
                ->references('idCirugia')->on('Cirugia');
        });

        Schema::create('PreparacionPacienteTipoPreparacion', function (Blueprint $table) {
            $table->id('idPreparacionPacienteTipoPreparacion');
            $table->unsignedBigInteger('idPreparacionPaciente');
            $table->unsignedBigInteger('idTipoPreparacion');

            $table->foreign('idPreparacionPaciente', 'fk_pptp_preparacionpaciente')
                ->references('idPreparacionPaciente')->on('PreparacionPaciente');
            $table->foreign('idTipoPreparacion', 'fk_pptp_tipopreparacion')
                ->references('idTipoPreparacion')->on('TipoPreparacion');
        });

        Schema::create('PreparacionPacienteTipoPreparacionTipoIndicacion', function (Blueprint $table) {
            $table->id('idPreparacionPacienteTipoPreparacionTipoIndicacion');
            $table->unsignedBigInteger('idPreparacionPacienteTipoPreparacion');
            $table->unsignedBigInteger('idTipoIndicacion');
            $table->integer('hsReglaCantidadIngestaAnteriorCirugia')->nullable();

            $table->foreign('idPreparacionPacienteTipoPreparacion', 'fk_pptpti_pptp')
                ->references('idPreparacionPacienteTipoPreparacion')->on('PreparacionPacienteTipoPreparacion');
            $table->foreign('idTipoIndicacion', 'fk_pptpti_tipoindicacion')
                ->references('idTipoIndicacion')->on('TipoIndicacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PreparacionPacienteTipoPreparacionTipoIndicacion');
        Schema::dropIfExists('PreparacionPacienteTipoPreparacion');
        Schema::dropIfExists('PreparacionPaciente');
        Schema::dropIfExists('TipoIndicacion');
        Schema::dropIfExists('TipoPreparacion');
    }
};
