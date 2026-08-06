<?php

/*
 * Gestor documental externo (Cloudinary), donde viven los resultados de
 * estudios y de hisopados. Ver App\Support\GestorDocumental.
 */

return [

    /*
     * Credenciales en el formato que entrega el panel de Cloudinary:
     * cloudinary://<api_key>:<api_secret>@<cloud_name>
     *
     * Vacio a proposito en .env.example: sin credenciales la aplicacion usa el
     * gestor local y sigue funcionando entera, asi que clonar el repo y correr
     * migrate:fresh --seed no obliga a abrir una cuenta.
     */
    'url' => env('CLOUDINARY_URL'),

    /*
     * Carpeta raiz dentro de la cuenta. El default incluye el entorno
     * (startmed-local, startmed-production, ...) a proposito: si alguna vez se
     * reusan las mismas credenciales en mas de un entorno (por ejemplo, para
     * probar el deploy con la cuenta personal de quien lo armo), cada uno cae
     * en su propia carpeta aunque nadie haya seteado CLOUDINARY_CARPETA a mano.
     * Es lo que hace posible al comando `documentos:limpiar-huerfanos`: si
     * compartieran carpeta pero tuvieran bases de datos distintas, un barrido
     * de huerfanos corrido desde un entorno borraria archivos que el otro
     * todavia usa. Ver App\Console\Commands\LimpiarDocumentosHuerfanos.
     */
    'carpeta' => env('CLOUDINARY_CARPETA', 'startmed-'.env('APP_ENV', 'local')),

    /*
     * Minutos que vive la URL firmada con la que se muestra un documento.
     * Corto a proposito: la URL es la unica llave del archivo mientras dura.
     */
    'minutos_url' => (int) env('CLOUDINARY_MINUTOS_URL', 10),

];
