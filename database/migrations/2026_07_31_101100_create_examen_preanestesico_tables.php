<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las 3 tablas de respuestas del examen pre-anestesico usan nombres acortados
 * respecto del DBML original: MySQL limita identificadores a 64 caracteres y
 * los nombres largos (tabla + columna + constraint) superaban ese limite.
 *
 *   ExamenCirugiaPreAnestesicaConfigTipoExamenPreAnestesico
 *       -> ExamenPreAnestesicoConfig
 *   ExamenCirugiaPreAnestesicaConfigTipoExamenPreAnestesicoPregunta
 *       -> ExamenPreAnestesicoConfigPregunta
 *   ExamenCirugiaPreAnestesicaConfigTipoExamenPreAnestesicoPreguntaRespuesta
 *       -> ExamenPreAnestesicoConfigPreguntaRespuesta
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ConfigTipoExamenPreAnestesico', function (Blueprint $table) {
            $table->id('idConfigTipoExamenPreAnestesico');
            $table->dateTime('fechaInicioVigeConfigTipoExamenPreAnestesico')->nullable();
            $table->dateTime('fechaFinVigeConfigTipoExamenPreAnestesico')->nullable();
        });

        Schema::create('ConfigTipoExamenPreAnestesicoPregunta', function (Blueprint $table) {
            $table->id('idConfigTipoExamenPreAnestesicoPregunta');
            $table->unsignedBigInteger('idConfigTipoExamenPreAnestesico');
            $table->string('nombrePreguntaConfigTipoExamenPreAnestesicoPregunta', 255);
            $table->boolean('requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta')
                ->default(false);

            $table->foreign('idConfigTipoExamenPreAnestesico', 'fk_ctepap_config')
                ->references('idConfigTipoExamenPreAnestesico')->on('ConfigTipoExamenPreAnestesico');
        });

        Schema::create('ConfigTipoExamenPreAnestesicoPreguntaRespuesta', function (Blueprint $table) {
            $table->id('idConfigTipoExamenPreAnestesicoPreguntaRespuesta');
            $table->unsignedBigInteger('idConfigTipoExamenPreAnestesicoPregunta');
            $table->string('nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta', 255);

            $table->foreign('idConfigTipoExamenPreAnestesicoPregunta', 'fk_ctepapr_pregunta')
                ->references('idConfigTipoExamenPreAnestesicoPregunta')
                ->on('ConfigTipoExamenPreAnestesicoPregunta');
        });

        Schema::create('ExamenCirugiaPreAnestesica', function (Blueprint $table) {
            $table->id('idExamenCirugiaPreAnestesica');
            $table->unsignedBigInteger('idCirugia');
            $table->string('observacionesExamenCirugiaPreAnestesica', 255)->nullable();

            $table->foreign('idCirugia', 'fk_examencirugiapreanest_cirugia')
                ->references('idCirugia')->on('Cirugia');
        });

        Schema::create('ExamenPreAnestesicoConfig', function (Blueprint $table) {
            $table->id('idExamenPreAnestesicoConfig');
            $table->unsignedBigInteger('idExamenCirugiaPreAnestesica');
            $table->unsignedBigInteger('idConfigTipoExamenPreAnestesico');

            $table->foreign('idExamenCirugiaPreAnestesica', 'fk_epac_examen')
                ->references('idExamenCirugiaPreAnestesica')->on('ExamenCirugiaPreAnestesica');
            $table->foreign('idConfigTipoExamenPreAnestesico', 'fk_epac_config')
                ->references('idConfigTipoExamenPreAnestesico')->on('ConfigTipoExamenPreAnestesico');
        });

        Schema::create('ExamenPreAnestesicoConfigPregunta', function (Blueprint $table) {
            $table->id('idExamenPreAnestesicoConfigPregunta');
            $table->unsignedBigInteger('idExamenPreAnestesicoConfig');
            $table->unsignedBigInteger('idConfigTipoExamenPreAnestesicoPregunta');
            // Texto libre, se usa cuando la pregunta no requiere opcion de respuesta.
            $table->string('respuestaExamenPreAnestesicoConfigPregunta', 255)->nullable();

            $table->foreign('idExamenPreAnestesicoConfig', 'fk_epacp_examenconfig')
                ->references('idExamenPreAnestesicoConfig')->on('ExamenPreAnestesicoConfig');
            $table->foreign('idConfigTipoExamenPreAnestesicoPregunta', 'fk_epacp_pregunta')
                ->references('idConfigTipoExamenPreAnestesicoPregunta')
                ->on('ConfigTipoExamenPreAnestesicoPregunta');
        });

        Schema::create('ExamenPreAnestesicoConfigPreguntaRespuesta', function (Blueprint $table) {
            $table->id('idExamenPreAnestesicoConfigPreguntaRespuesta');
            $table->unsignedBigInteger('idExamenPreAnestesicoConfigPregunta');
            $table->unsignedBigInteger('idConfigTipoExamenPreAnestesicoPreguntaRespuesta');

            $table->foreign('idExamenPreAnestesicoConfigPregunta', 'fk_epacpr_examenpregunta')
                ->references('idExamenPreAnestesicoConfigPregunta')
                ->on('ExamenPreAnestesicoConfigPregunta');
            $table->foreign('idConfigTipoExamenPreAnestesicoPreguntaRespuesta', 'fk_epacpr_respuesta')
                ->references('idConfigTipoExamenPreAnestesicoPreguntaRespuesta')
                ->on('ConfigTipoExamenPreAnestesicoPreguntaRespuesta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ExamenPreAnestesicoConfigPreguntaRespuesta');
        Schema::dropIfExists('ExamenPreAnestesicoConfigPregunta');
        Schema::dropIfExists('ExamenPreAnestesicoConfig');
        Schema::dropIfExists('ExamenCirugiaPreAnestesica');
        Schema::dropIfExists('ConfigTipoExamenPreAnestesicoPreguntaRespuesta');
        Schema::dropIfExists('ConfigTipoExamenPreAnestesicoPregunta');
        Schema::dropIfExists('ConfigTipoExamenPreAnestesico');
    }
};
