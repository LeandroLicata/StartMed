<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Rol', function (Blueprint $table) {
            $table->id('idRol');
            $table->string('nombreRol', 120);
            $table->dateTime('fechaHoraBajaRol')->nullable();
        });

        Schema::create('Personal', function (Blueprint $table) {
            $table->id('idPersonal');
            $table->unsignedBigInteger('idPersona');
            $table->string('matriculaProvincial', 60)->nullable();
            $table->string('matriculaNacional', 60)->nullable();
            $table->string('legajoPersonal', 60)->nullable();
            $table->string('mailInstitucional', 120)->nullable();

            $table->foreign('idPersona', 'fk_personal_persona')
                ->references('idPersona')->on('Persona');
        });

        Schema::create('Usuario', function (Blueprint $table) {
            $table->id('idUsuario');
            $table->unsignedBigInteger('idPersonal');
            $table->string('nombreUsuario', 120)->unique('uq_usuario_nombre');
            $table->string('passwordUsuario', 255);
            $table->dateTime('fechaBajaUsuario')->nullable();

            $table->foreign('idPersonal', 'fk_usuario_personal')
                ->references('idPersonal')->on('Personal');
        });

        Schema::create('RolPersonal', function (Blueprint $table) {
            $table->id('idRolPersonal');
            $table->unsignedBigInteger('idPersonal');
            $table->unsignedBigInteger('idRol');
            $table->dateTime('fechaHoraAsignacionRolPersonal')->nullable();
            $table->dateTime('fechaHoraBajaAsignacionRolPersonal')->nullable();

            $table->foreign('idPersonal', 'fk_rolpersonal_personal')
                ->references('idPersonal')->on('Personal');
            $table->foreign('idRol', 'fk_rolpersonal_rol')
                ->references('idRol')->on('Rol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RolPersonal');
        Schema::dropIfExists('Usuario');
        Schema::dropIfExists('Personal');
        Schema::dropIfExists('Rol');
    }
};
