<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Principal, complementaria, etc.
        Schema::create('ProfilaxisRol', function (Blueprint $table) {
            $table->id('idProfilaxisRol');
            $table->string('nombreProfilaxisRol', 120);
            $table->dateTime('fechaBajaProfilaxisRol')->nullable();
        });

        Schema::create('Profilaxis', function (Blueprint $table) {
            $table->id('idProfilaxis');
            $table->string('nombreProfilaxis', 180);
            $table->dateTime('fechaBajaProfilaxis')->nullable();
        });

        Schema::create('ProfilaxisAtbCirugia', function (Blueprint $table) {
            $table->id('idProfilaxisAtbCirugia');
            $table->unsignedBigInteger('idCirugia');
            $table->string('alertaProfilaxisAtbCirugia', 255)->nullable();
            $table->string('motivoProfilaxisAtbCirugia', 255)->nullable();

            $table->foreign('idCirugia', 'fk_profilaxisatbcirugia_cirugia')
                ->references('idCirugia')->on('Cirugia');
        });

        Schema::create('ProfilaxisAtbCirugiaProfilaxis', function (Blueprint $table) {
            $table->id('idProfilaxisAtbCirugiaProfilaxis');
            $table->unsignedBigInteger('idProfilaxisAtbCirugia');
            $table->unsignedBigInteger('idProfilaxisRol');
            $table->unsignedBigInteger('idProfilaxis');
            $table->string('indicacionesProfilaxisAtbCirugiaProfilaxis', 255)->nullable();

            $table->foreign('idProfilaxisAtbCirugia', 'fk_pacp_profilaxisatbcirugia')
                ->references('idProfilaxisAtbCirugia')->on('ProfilaxisAtbCirugia');
            $table->foreign('idProfilaxisRol', 'fk_pacp_profilaxisrol')
                ->references('idProfilaxisRol')->on('ProfilaxisRol');
            $table->foreign('idProfilaxis', 'fk_pacp_profilaxis')
                ->references('idProfilaxis')->on('Profilaxis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProfilaxisAtbCirugiaProfilaxis');
        Schema::dropIfExists('ProfilaxisAtbCirugia');
        Schema::dropIfExists('Profilaxis');
        Schema::dropIfExists('ProfilaxisRol');
    }
};
