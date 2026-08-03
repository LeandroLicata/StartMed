<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('EstadoCirugia', function (Blueprint $table) {
            $table->id('idEstadoCirugia');
            $table->string('nombreEstadoCirugia', 120);
            $table->dateTime('fechaBajaEstadoCirugia')->nullable();
        });

        Schema::create('CirugiaEstado', function (Blueprint $table) {
            $table->id('idCirugiaEstado');
            $table->unsignedBigInteger('idCirugia');
            $table->unsignedBigInteger('idEstadoCirugia');
            $table->dateTime('fechaAsignacionCirugiaEstado')->nullable();
            $table->dateTime('fechaDesasignacionCirugiaEstado')->nullable();

            $table->foreign('idCirugia', 'fk_cirugiaestado_cirugia')
                ->references('idCirugia')->on('Cirugia');
            $table->foreign('idEstadoCirugia', 'fk_cirugiaestado_estado')
                ->references('idEstadoCirugia')->on('EstadoCirugia');
        });

        Schema::create('CirugiaQuirofano', function (Blueprint $table) {
            $table->id('idCirugiaQuirofano');
            $table->unsignedBigInteger('idQuirofano');
            $table->unsignedBigInteger('idCirugia');
            $table->dateTime('fechaHoraAsignacion')->nullable();
            $table->dateTime('fechaHoraDesasignacion')->nullable();
            $table->string('observacionCirugiaQuirofano', 255)->nullable();

            $table->foreign('idQuirofano', 'fk_cirugiaquirofano_quirofano')
                ->references('idQuirofano')->on('Quirofano');
            $table->foreign('idCirugia', 'fk_cirugiaquirofano_cirugia')
                ->references('idCirugia')->on('Cirugia');
        });

        Schema::create('CirugiaPersonal', function (Blueprint $table) {
            $table->id('idCirugiaPersonal');
            $table->unsignedBigInteger('idCirugia');
            $table->unsignedBigInteger('idPersonal');
            $table->unsignedBigInteger('idRol');
            $table->dateTime('fechaInicioAsignacionCirugiaPersonal')->nullable();
            $table->dateTime('fechaFinAsignacionCirugiaPersonal')->nullable();
            $table->string('observacionesCirugiaPersonal', 255)->nullable();

            $table->foreign('idCirugia', 'fk_cirugiapersonal_cirugia')
                ->references('idCirugia')->on('Cirugia');
            $table->foreign('idPersonal', 'fk_cirugiapersonal_personal')
                ->references('idPersonal')->on('Personal');
            $table->foreign('idRol', 'fk_cirugiapersonal_rol')
                ->references('idRol')->on('Rol');
        });

        Schema::create('EstadoAutCirugia', function (Blueprint $table) {
            $table->id('idEstadoAutCirugia');
            $table->string('nombreEstadoAutCirugia', 120);
            $table->dateTime('fechaBajaEstadoAutCirugia')->nullable();
        });

        Schema::create('AutCirugia', function (Blueprint $table) {
            $table->id('idAutCirugia');
            $table->unsignedBigInteger('idCirugia');
            $table->unsignedBigInteger('idPlan');
            $table->date('fechaLimiteEnvioAutorizacion')->nullable();
            $table->dateTime('fechaHoraEnvioAutorizacionAutCirugia')->nullable();
            $table->dateTime('fechaHoraVerificacionAutCirugia')->nullable();
            $table->string('nroAprobacionAutCirugia', 60)->nullable();

            $table->foreign('idCirugia', 'fk_autcirugia_cirugia')
                ->references('idCirugia')->on('Cirugia');
            $table->foreign('idPlan', 'fk_autcirugia_plan')
                ->references('idPlan')->on('Plan');
        });

        Schema::create('AutoCirugiaEstado', function (Blueprint $table) {
            $table->id('idAutCirugiaEstado');
            $table->unsignedBigInteger('idAutCirugia');
            $table->unsignedBigInteger('idEstadoAutCirugia');
            $table->dateTime('fechaInicioAutoCirugiaEstado')->nullable();
            $table->dateTime('fechaFinAutoCirugiaEstado')->nullable();
            $table->string('observacionesAutoCirugiaEstado', 255)->nullable();

            $table->foreign('idAutCirugia', 'fk_autocirugiaestado_autcirugia')
                ->references('idAutCirugia')->on('AutCirugia');
            $table->foreign('idEstadoAutCirugia', 'fk_autocirugiaestado_estado')
                ->references('idEstadoAutCirugia')->on('EstadoAutCirugia');
        });

        Schema::create('TipoEstudio', function (Blueprint $table) {
            $table->id('idTipoEstudio');
            $table->string('nombreTipoEstudio', 180);
            $table->dateTime('fechaBajaTipoEstudio')->nullable();
        });

        Schema::create('CirugiaTipoEstudio', function (Blueprint $table) {
            $table->id('idCirugiaTipoEstudio');
            $table->unsignedBigInteger('idCirugia');
            $table->unsignedBigInteger('idTipoEstudio');
            $table->string('urlArchivoCirugiaTipoEstudio', 255)->nullable();
            $table->dateTime('fechaSubidaCirugiaTipoEstudio')->nullable();
            $table->dateTime('fechaAsignacionCirugiaTipoEstudio')->nullable();
            $table->dateTime('fechaFinAsignacionCirugiaTipoEstudio')->nullable();
            $table->dateTime('fechaEsperadaResultadoCirugiaTipoEstudio')->nullable();
            $table->string('resultadoCirugiaTipoEstudio', 255)->nullable();

            $table->foreign('idCirugia', 'fk_cirugiatipoestudio_cirugia')
                ->references('idCirugia')->on('Cirugia');
            $table->foreign('idTipoEstudio', 'fk_cirugiatipoestudio_tipoestudio')
                ->references('idTipoEstudio')->on('TipoEstudio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CirugiaTipoEstudio');
        Schema::dropIfExists('TipoEstudio');
        Schema::dropIfExists('AutoCirugiaEstado');
        Schema::dropIfExists('AutCirugia');
        Schema::dropIfExists('EstadoAutCirugia');
        Schema::dropIfExists('CirugiaPersonal');
        Schema::dropIfExists('CirugiaQuirofano');
        Schema::dropIfExists('CirugiaEstado');
        Schema::dropIfExists('EstadoCirugia');
    }
};
