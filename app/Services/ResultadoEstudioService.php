<?php

namespace App\Services;

use App\Models\CirugiaTipoEstudio;
use App\Support\DocumentoNoDisponible;
use App\Support\GestorDocumental;
use Illuminate\Http\UploadedFile;

/**
 * El resultado de un estudio prequirurgico en el gestor documental: subirlo y
 * entregarlo. Lo usan el expediente del staff y el portal del paciente, asi que
 * las dos reglas que importan viven aca y no en cada controlador:
 *
 * - El puntero y `fechaSubidaCirugiaTipoEstudio` se escriben juntos y solo
 *   despues de que el gestor acepto el archivo. Un estudio marcado «Subido» sin
 *   archivo detras es lo que hacia la maqueta.
 * - Lo que sale es una URL firmada que vence, nunca el puntero.
 */
class ResultadoEstudioService
{
    public function __construct(private GestorDocumental $gestor) {}

    /**
     * @throws DocumentoNoDisponible si el gestor no acepto el archivo; el estudio queda como estaba.
     */
    public function subir(CirugiaTipoEstudio $estudio, UploadedFile $archivo): void
    {
        $puntero = $this->gestor->guardar($archivo, 'estudios/'.$estudio->idCirugia);

        $estudio->update([
            'urlArchivoCirugiaTipoEstudio' => $puntero,
            'fechaSubidaCirugiaTipoEstudio' => now(),
        ]);
    }

    /**
     * Quien llama ya verifico que el estudio tiene archivo y que quien lo pide
     * puede verlo.
     *
     * @throws DocumentoNoDisponible si el puntero no es de este gestor o el archivo no esta.
     */
    public function urlTemporal(CirugiaTipoEstudio $estudio): string
    {
        return $this->gestor->urlTemporal($estudio->urlArchivoCirugiaTipoEstudio);
    }
}
