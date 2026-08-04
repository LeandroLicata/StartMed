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

        // ProfilaxisAtbHisopadoSarm / ProfilaxisAtbHisopadoSarmProfilaxis, que
        // usan estas dos tablas, se crean en create_hisopado_sarm_tables — la
        // profilaxis antibiotica cuelga del hisopado SAMR, no de la cirugia.
    }

    public function down(): void
    {
        Schema::dropIfExists('Profilaxis');
        Schema::dropIfExists('ProfilaxisRol');
    }
};
