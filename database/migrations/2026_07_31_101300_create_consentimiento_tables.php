<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ConfigConsentimiento', function (Blueprint $table) {
            $table->id('idConfigConsentimiento');
            $table->unsignedBigInteger('idTipoCirugia');
            // Placeholders: {{paciente}}, {{dni}}, {{procedimiento}}, {{cirujano}}
            $table->longText('textoConfigConsentimiento');
            $table->dateTime('fechaInicioConfigConsentimiento')->nullable();
            $table->dateTime('fechaFinConfigConsentimiento')->nullable();

            $table->foreign('idTipoCirugia', 'fk_configconsentimiento_tipocirugia')
                ->references('idTipoCirugia')->on('TipoCirugia');
        });

        Schema::create('ConsentimientoPaciente', function (Blueprint $table) {
            $table->id('idConsentimiento');
            $table->unsignedBigInteger('idCirugia');
            $table->unsignedBigInteger('idConfigConsentimiento');
            // Snapshot con los datos ya completados, inmutable.
            $table->longText('textoRenderizadoConsentimiento');
            // SHA-256 del texto renderizado, calculado antes de firmar.
            $table->string('hashConsentimiento', 64)->nullable();
            $table->dateTime('fechaFirmaConsentimiento')->nullable();
            // Base64 de la firma (canvas) o path a la imagen.
            $table->longText('firmaConsentimiento')->nullable();
            $table->string('ipFirmaConsentimiento', 45)->nullable();
            $table->string('userAgentFirmaConsentimiento', 255)->nullable();
            // PDF sellado post-firma.
            $table->string('urlPdfConsentimiento', 255)->nullable();

            $table->foreign('idCirugia', 'fk_consentimiento_cirugia')
                ->references('idCirugia')->on('Cirugia');
            $table->foreign('idConfigConsentimiento', 'fk_consentimiento_config')
                ->references('idConfigConsentimiento')->on('ConfigConsentimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ConsentimientoPaciente');
        Schema::dropIfExists('ConfigConsentimiento');
    }
};
