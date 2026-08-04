<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TipoASA', function (Blueprint $table) {
            $table->id('idTipoAsa');
            $table->string('nombreTipoAsa', 120);
            $table->string('aliasTipoAsa', 60)->nullable();
            $table->string('descripcionTipoAsa', 255)->nullable();
            $table->dateTime('fechaBajaTipoAsa')->nullable();
        });

        Schema::create('TipoAnestesia', function (Blueprint $table) {
            $table->id('idTipoAnestesia');
            $table->string('nombreTipoAnestesia', 120);
            $table->dateTime('fechaBajaTipoAnestesia')->nullable();
        });

        Schema::create('EstadoEvaluacionAnestesica', function (Blueprint $table) {
            $table->id('idEstadoEvaluacionAnestesica');
            $table->string('nombreEstadoEvaluacionAnestesica', 120);
            $table->dateTime('fechaBajaEstadoEvaluacionAnestesica')->nullable();
        });

        Schema::create('EvaluacionAnestesica', function (Blueprint $table) {
            $table->id('idEvaluacionAnestesica');
            $table->unsignedBigInteger('idCirugia');
            $table->string('observacionesEquipoEvaluacion', 255)->nullable();
            $table->string('observacionesPacienteEvaluacion', 255)->nullable();

            $table->foreign('idCirugia', 'fk_evaluacionanestesica_cirugia')
                ->references('idCirugia')->on('Cirugia');
        });

        Schema::create('EvaluacionTipoAsa', function (Blueprint $table) {
            $table->id('idEvaluacionTipoAsa');
            $table->unsignedBigInteger('idEvaluacionAnestesica');
            $table->unsignedBigInteger('idTipoAsa');
            $table->dateTime('fechaInicioTipoAsa')->nullable();
            $table->dateTime('fechaFinTipoAsa')->nullable();

            $table->foreign('idEvaluacionAnestesica', 'fk_evaltipoasa_evaluacion')
                ->references('idEvaluacionAnestesica')->on('EvaluacionAnestesica');
            $table->foreign('idTipoAsa', 'fk_evaltipoasa_tipoasa')
                ->references('idTipoAsa')->on('TipoASA');
        });

        Schema::create('EvaluacionTipoAnestesia', function (Blueprint $table) {
            $table->id('idEvaluacionTipoAnestesia');
            $table->unsignedBigInteger('idEvaluacionAnestesica');
            $table->unsignedBigInteger('idTipoAnestesia');
            $table->dateTime('fechaInicioTipoAnestesia')->nullable();
            $table->dateTime('fechaFinTipoAnestesia')->nullable();

            $table->foreign('idEvaluacionAnestesica', 'fk_evaltipoanest_evaluacion')
                ->references('idEvaluacionAnestesica')->on('EvaluacionAnestesica');
            $table->foreign('idTipoAnestesia', 'fk_evaltipoanest_tipoanestesia')
                ->references('idTipoAnestesia')->on('TipoAnestesia');
        });

        Schema::create('EvaluacionAnestesicaEstado', function (Blueprint $table) {
            $table->id('idEvaluacionAnestesicaEstado');
            $table->unsignedBigInteger('idEvaluacionAnestesica');
            $table->unsignedBigInteger('idEstadoEvaluacionAnestesica');
            $table->dateTime('fechaInicioEvaluacionAnestesicaEstado')->nullable();
            $table->dateTime('fechaFinEvaluacionAnestesicaEstado')->nullable();

            $table->foreign('idEvaluacionAnestesica', 'fk_evalanestestado_evaluacion')
                ->references('idEvaluacionAnestesica')->on('EvaluacionAnestesica');
            $table->foreign('idEstadoEvaluacionAnestesica', 'fk_evalanestestado_estado')
                ->references('idEstadoEvaluacionAnestesica')->on('EstadoEvaluacionAnestesica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('EvaluacionAnestesicaEstado');
        Schema::dropIfExists('EvaluacionTipoAnestesia');
        Schema::dropIfExists('EvaluacionTipoAsa');
        Schema::dropIfExists('EvaluacionAnestesica');
        Schema::dropIfExists('EstadoEvaluacionAnestesica');
        Schema::dropIfExists('TipoAnestesia');
        Schema::dropIfExists('TipoASA');
    }
};
