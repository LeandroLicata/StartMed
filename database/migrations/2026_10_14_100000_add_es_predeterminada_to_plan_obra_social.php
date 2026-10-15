<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * La cobertura que el paciente elige como predeterminada: es la que el gestor
 * ve primero al cargar una cirugia o una autorizacion.
 *
 * Va como columna y no derivada ("la vigente mas reciente") porque esa
 * alternativa no deja elegir. A lo sumo una por paciente; lo garantiza quien
 * la marca, desmarcando las demas en la misma transaccion.
 *
 * Migracion aparte y no dentro de la 100300: produccion ya esta migrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PlanObraSocial', function (Blueprint $table) {
            $table->boolean('esPredeterminadaPlanObraSocial')->default(false)->after('fechaFinPlanObraSocial');
        });
    }

    public function down(): void
    {
        Schema::table('PlanObraSocial', function (Blueprint $table) {
            $table->dropColumn('esPredeterminadaPlanObraSocial');
        });
    }
};
