<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Quirofano', function (Blueprint $table) {
            $table->id('idQuirofano');
            $table->integer('nroQuirofano');
            $table->string('nombreQuirofano', 120);
            $table->dateTime('fechaBajaQuirofano')->nullable();
        });

        Schema::create('EstadoQuirofano', function (Blueprint $table) {
            $table->id('idEstadoQuirofano');
            $table->string('nombreEstadoQuirofano', 120);
            $table->dateTime('fechaBajaEstadoQuirofano')->nullable();
        });

        Schema::create('QuirofanoEstado', function (Blueprint $table) {
            $table->id('idQuirofanoEstado');
            $table->unsignedBigInteger('idQuirofano');
            $table->unsignedBigInteger('idEstadoQuirofano');
            $table->dateTime('fechaInicioQuirofanoEstado')->nullable();
            $table->dateTime('fechaFinQuirofanoEstado')->nullable();

            $table->foreign('idQuirofano', 'fk_quirofanoestado_quirofano')
                ->references('idQuirofano')->on('Quirofano');
            $table->foreign('idEstadoQuirofano', 'fk_quirofanoestado_estado')
                ->references('idEstadoQuirofano')->on('EstadoQuirofano');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('QuirofanoEstado');
        Schema::dropIfExists('EstadoQuirofano');
        Schema::dropIfExists('Quirofano');
    }
};
