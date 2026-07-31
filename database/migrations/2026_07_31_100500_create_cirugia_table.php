<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TipoCirugia', function (Blueprint $table) {
            $table->id('idTipoCirugia');
            $table->string('nombreTipoCirugia', 180);
            $table->text('descripcionTipoCirugia')->nullable();
            $table->dateTime('fechaBajaTipoCirugia')->nullable();
        });

        Schema::create('Cirugia', function (Blueprint $table) {
            $table->id('idCirugia');
            $table->unsignedBigInteger('idPersonaPaciente');
            $table->unsignedBigInteger('idTipoCirugia');
            $table->unsignedBigInteger('idPersonalCirujano')->nullable();
            $table->unsignedBigInteger('idPersonalAnestesista')->nullable();
            $table->dateTime('fechaHoraCirugia')->nullable();
            $table->string('descripcionCirugia', 255)->nullable();
            $table->string('observacionesPaciente', 255)->nullable();
            $table->boolean('requiereImplante')->default(false);

            $table->foreign('idPersonaPaciente', 'fk_cirugia_paciente')
                ->references('idPersona')->on('Persona');
            $table->foreign('idTipoCirugia', 'fk_cirugia_tipocirugia')
                ->references('idTipoCirugia')->on('TipoCirugia');
            $table->foreign('idPersonalCirujano', 'fk_cirugia_cirujano')
                ->references('idPersonal')->on('Personal');
            $table->foreign('idPersonalAnestesista', 'fk_cirugia_anestesista')
                ->references('idPersonal')->on('Personal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Cirugia');
        Schema::dropIfExists('TipoCirugia');
    }
};
