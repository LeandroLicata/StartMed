<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('EstadoHisopadoSarm', function (Blueprint $table) {
            $table->id('idEstadoHisopadoSarm');
            $table->string('nombreEstadoHisopadoSarm', 120);
            $table->dateTime('fechaBajaEstadoHisopadoSarm')->nullable();
        });

        Schema::create('HisopadoSarm', function (Blueprint $table) {
            $table->id('idHisopadoSarm');
            $table->unsignedBigInteger('idCirugia');
            $table->unsignedBigInteger('idEstablecimiento')->nullable();
            $table->dateTime('fechaSolicitacionHisopadoSarm');
            $table->dateTime('fechaEstimadaResultadosHisopadoSarm')->nullable();
            $table->string('observacionesHisopadoSarm', 255)->nullable();
            // Placeholder sin upload real todavia, mismo patron que
            // CirugiaTipoEstudio.urlArchivoCirugiaTipoEstudio.
            $table->string('urlHisopadoSarm', 255)->nullable();

            $table->foreign('idCirugia', 'fk_hisopadosarm_cirugia')
                ->references('idCirugia')->on('Cirugia');
            $table->foreign('idEstablecimiento', 'fk_hisopadosarm_establecimiento')
                ->references('idEstablecimiento')->on('Establecimiento');
        });

        // Historial por rango de fechas, mismo patron que CirugiaEstado. El
        // resultado (Negativo/Positivo) es tambien un estado, no una columna
        // aparte.
        Schema::create('HisopadoSarmEstado', function (Blueprint $table) {
            $table->id('idHisopadoSarmEstado');
            $table->unsignedBigInteger('idHisopadoSarm');
            $table->unsignedBigInteger('idEstadoHisopadoSarm');
            $table->dateTime('fechaInicioAsignacionHisopadoSarmEstado')->nullable();
            $table->dateTime('fechaFinAsignacionHisopadoSarmEstado')->nullable();

            $table->foreign('idHisopadoSarm', 'fk_hse_hisopadosarm')
                ->references('idHisopadoSarm')->on('HisopadoSarm');
            $table->foreign('idEstadoHisopadoSarm', 'fk_hse_estadohisopadosarm')
                ->references('idEstadoHisopadoSarm')->on('EstadoHisopadoSarm');
        });

        // La profilaxis antibiotica cuelga del hisopado SAMR (no de la
        // cirugia): el protocolo que corresponde depende de su resultado.
        Schema::create('ProfilaxisAtbHisopadoSarm', function (Blueprint $table) {
            $table->id('idProfilaxisAtbHisopadoSarm');
            $table->unsignedBigInteger('idHisopadoSarm');
            $table->string('alertaProfilaxisAtbHisopadoSarm', 255)->nullable();
            $table->string('motivoProfilaxisAtbHisopadoSarm', 255)->nullable();

            $table->foreign('idHisopadoSarm', 'fk_pahs_hisopadosarm')
                ->references('idHisopadoSarm')->on('HisopadoSarm');
        });

        Schema::create('ProfilaxisAtbHisopadoSarmProfilaxis', function (Blueprint $table) {
            $table->id('idProfilaxisAtbHisopadoSarmProfilaxis');
            $table->unsignedBigInteger('idProfilaxisAtbHisopadoSarm');
            $table->unsignedBigInteger('idProfilaxisRol');
            $table->unsignedBigInteger('idProfilaxis');
            $table->string('indicacionesProfilaxisAtbHisopadoSarmProfilaxis', 255)->nullable();

            $table->foreign('idProfilaxisAtbHisopadoSarm', 'fk_pahsp_pahs')
                ->references('idProfilaxisAtbHisopadoSarm')->on('ProfilaxisAtbHisopadoSarm');
            $table->foreign('idProfilaxisRol', 'fk_pahsp_profilaxisrol')
                ->references('idProfilaxisRol')->on('ProfilaxisRol');
            $table->foreign('idProfilaxis', 'fk_pahsp_profilaxis')
                ->references('idProfilaxis')->on('Profilaxis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProfilaxisAtbHisopadoSarmProfilaxis');
        Schema::dropIfExists('ProfilaxisAtbHisopadoSarm');
        Schema::dropIfExists('HisopadoSarmEstado');
        Schema::dropIfExists('HisopadoSarm');
        Schema::dropIfExists('EstadoHisopadoSarm');
    }
};
