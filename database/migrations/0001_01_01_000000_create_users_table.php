<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La autenticacion de StartMed usa la tabla `Usuario` (ver
 * 2026_07_31_100200_create_personal_rol_usuario_tables), no la tabla `users`
 * por defecto de Laravel. Aca solo queda `sessions`, que el driver de sesion
 * en base de datos sigue necesitando; su columna `user_id` guarda idUsuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
