<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ObraSocial', function (Blueprint $table) {
            $table->id('idObraSocial');
            $table->string('nombreObraSocial', 180);
            $table->string('telefonoObraSocial', 30)->nullable();
            $table->string('emailObraSocial', 120)->nullable();
            $table->integer('diasVigenciaOrden')->nullable();
            $table->dateTime('fechaBajaObraSocial')->nullable();
        });

        Schema::create('Plan', function (Blueprint $table) {
            $table->id('idPlan');
            $table->unsignedBigInteger('idobrasocial');
            $table->string('nombrePlan', 180);
            $table->boolean('es_sin_cobertura')->default(false);
            $table->boolean('habilitado_autorizaciones')->default(true);
            $table->string('observacion_alerta', 255)->nullable();
            $table->dateTime('fechaBajaPlan')->nullable();

            $table->foreign('idobrasocial', 'fk_plan_obrasocial')
                ->references('idObraSocial')->on('ObraSocial');
        });

        Schema::create('PlanObraSocial', function (Blueprint $table) {
            $table->id('idPlanObraSocial');
            $table->unsignedBigInteger('idPersona');
            $table->unsignedBigInteger('idPlan');
            $table->string('nroBeneficiaroPlanObraSocial', 60)->nullable();
            $table->dateTime('fechaInicioPlanObraSocial')->nullable();
            $table->dateTime('fechaFinPlanObraSocial')->nullable();

            $table->foreign('idPersona', 'fk_planobrasocial_persona')
                ->references('idPersona')->on('Persona');
            $table->foreign('idPlan', 'fk_planobrasocial_plan')
                ->references('idPlan')->on('Plan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PlanObraSocial');
        Schema::dropIfExists('Plan');
        Schema::dropIfExists('ObraSocial');
    }
};
