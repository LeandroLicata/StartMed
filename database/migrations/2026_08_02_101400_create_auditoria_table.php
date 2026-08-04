<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Registro de quien escribio que, y cuando.
 *
 * El resto del esquema no tiene created_at ni updated_at, asi que no habia
 * forma de saber quien dio de alta un usuario o quien dio de baja un catalogo.
 * Esta tabla lo resuelve para la seccion de administracion, que es la unica
 * que escribe.
 *
 * `idRegistroAuditoria` apunta a filas de tablas distintas, asi que no puede
 * ser una FK: la tabla de destino va en `tablaAuditoria`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Auditoria', function (Blueprint $table) {
            $table->id('idAuditoria');
            $table->unsignedBigInteger('idUsuario')->nullable();
            $table->dateTime('fechaHoraAuditoria');
            $table->string('accionAuditoria', 30);
            $table->string('tablaAuditoria', 64);
            $table->unsignedBigInteger('idRegistroAuditoria')->nullable();
            $table->string('descripcionAuditoria', 255);

            // { "nombreTipoCirugia": { "antes": "…", "despues": "…" } }
            $table->json('cambiosAuditoria')->nullable();

            // Nullable y sin cascada: si el autor se da de baja, su rastro queda.
            $table->foreign('idUsuario', 'fk_auditoria_usuario')
                ->references('idUsuario')->on('Usuario');

            $table->index('fechaHoraAuditoria', 'idx_auditoria_fecha');
            $table->index(['tablaAuditoria', 'idRegistroAuditoria'], 'idx_auditoria_registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Auditoria');
    }
};
