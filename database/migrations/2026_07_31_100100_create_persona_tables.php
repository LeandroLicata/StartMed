<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TipoDocumento', function (Blueprint $table) {
            $table->id('idTipoDocumento');
            $table->string('nombreTipoDocumento', 120);
            $table->dateTime('fechaBajaTipoDocumento')->nullable();
        });

        Schema::create('GrupoSanguineo', function (Blueprint $table) {
            $table->id('idGrupoSanguineo');
            $table->string('nombreGrupoSanguineo', 120);
            $table->dateTime('fechaBajaGrupoSanguineo')->nullable();
        });

        Schema::create('Persona', function (Blueprint $table) {
            $table->id('idPersona');
            $table->unsignedBigInteger('tipo_documento_id');
            $table->unsignedBigInteger('grupo_sanguineo_id')->nullable();
            $table->string('documento', 60);
            $table->string('apellidos', 120);
            $table->string('nombres', 120);
            $table->string('apellido_materno', 120)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero', 1)->nullable();
            $table->string('cuil', 60)->nullable();
            $table->boolean('usar_edad_aproximada')->default(false);
            $table->date('fecha_edad_aproximada')->nullable();
            $table->dateTime('fechaHoraBajaPersona')->nullable();
            $table->string('telefono_codigo', 10)->nullable();
            $table->string('telefono_numero', 20)->nullable();
            $table->string('contacto_telefono_codigo', 10)->nullable();
            $table->string('contacto_telefono_numero', 20)->nullable();
            $table->string('contacto_telefono_carrier', 100)->nullable();
            $table->longText('observaciones')->nullable();
            $table->boolean('tiene_incapacidad')->default(false);
            $table->boolean('es_cronico')->default(false);
            $table->boolean('no_acepta_donacion_sanguinea')->default(false);
            $table->string('contacto_telefono_prefijo', 255)->nullable();
            $table->string('contacto_celular_codigo', 10)->nullable();
            $table->string('contacto_celular_numero', 20)->nullable();
            $table->string('contacto_celular_prefijo', 255)->nullable();
            $table->string('contacto_celular_carrier', 100)->nullable();
            $table->string('contacto_email_direccion', 60)->nullable();

            $table->foreign('tipo_documento_id', 'fk_persona_tipodocumento')
                ->references('idTipoDocumento')->on('TipoDocumento');
            $table->foreign('grupo_sanguineo_id', 'fk_persona_gruposanguineo')
                ->references('idGrupoSanguineo')->on('GrupoSanguineo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Persona');
        Schema::dropIfExists('GrupoSanguineo');
        Schema::dropIfExists('TipoDocumento');
    }
};
