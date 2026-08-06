<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Deposito de los archivos clinicos del expediente: resultados de estudios
 * prequirurgicos y de hisopados. Es el "gestor documental externo" del que
 * hablan las pantallas.
 *
 * Es un contrato con implementaciones, y no una clase de metodos estaticos como
 * el resto de app/Support, porque hace I/O de red: los tests y una instalacion
 * sin credenciales tienen que poder reemplazarlo. El binding esta en
 * App\Providers\AppServiceProvider.
 *
 * Un hemograma o un resultado de hisopado SARM son datos de salud
 * identificables, asi que **nunca se guardan como publicos**. De ahi la forma
 * de esta interfaz:
 *
 * - `guardar()` devuelve un **puntero opaco**, no una URL. Es lo que va a la
 *   base (`urlArchivoCirugiaTipoEstudio`, `urlHisopadoSarm`). El nombre de esas
 *   columnas viene del modelo de datos original y quedo: lo que contienen es un
 *   puntero al documento en el gestor, que es para lo que estaban.
 * - la URL de entrega la arma `urlTemporal()`, firmada y con vencimiento,
 *   recien en el momento de mostrarla y con la sesion ya verificada. Por eso no
 *   tiene sentido persistirla: vence.
 *
 * El formato del puntero es asunto de cada implementacion; lleva prefijo para
 * que una base sembrada contra el disco local no se resuelva por error contra
 * Cloudinary (y al reves) sin que nadie se entere.
 */
interface GestorDocumental
{
    /**
     * Guarda el archivo y devuelve el puntero que hay que persistir.
     *
     * @param  string  $carpeta  Ruta relativa dentro del deposito, sin barras al borde.
     *
     * @throws DocumentoNoDisponible si el deposito no acepto el archivo.
     */
    public function guardar(UploadedFile $archivo, string $carpeta): string;

    /**
     * URL firmada y con vencimiento para mostrar un documento ya guardado.
     *
     * @param  int|null  $minutos  Vencimiento; null usa el de configuracion.
     *
     * @throws DocumentoNoDisponible si el puntero no es de este gestor o el archivo no esta.
     */
    public function urlTemporal(string $puntero, ?int $minutos = null): string;
}
