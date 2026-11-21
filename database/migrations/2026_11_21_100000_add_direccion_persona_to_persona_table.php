<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Direccion fisica de la persona, necesaria para la gestion de pacientes y
 * el alta completa desde administracion.
 *
 * Migracion aparte porque produccion ya esta migrada. Queda nullable en BD
 * para no romper registros historicos, pero es obligatoria en los formularios
 * de alta y edicion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Persona', function (Blueprint $table) {
            $table->string('direccionPersona', 255)->nullable()->after('contacto_email_direccion');
        });
    }

    public function down(): void
    {
        Schema::table('Persona', function (Blueprint $table) {
            $table->dropColumn('direccionPersona');
        });
    }
};
