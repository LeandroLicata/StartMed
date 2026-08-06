<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Implementacion sobre el disco `local`, que es la que corre cuando no hay
 * CLOUDINARY_URL configurada. Ver GestorDocumental.
 *
 * No es un mock: guarda y entrega archivos de verdad. Esta para que el proyecto
 * se pueda clonar, sembrar y demostrar completo sin abrir una cuenta en
 * Cloudinary, y para que la suite de tests no salga nunca a la red.
 *
 * Mantiene la misma propiedad que la implementacion real: el disco `local`
 * apunta a storage/app/private, fuera de public/, asi que un archivo no se
 * alcanza por URL directa. La entrega usa las URL temporales firmadas que
 * habilita `'serve' => true` en config/filesystems.php.
 */
final class GestorDocumentalLocal implements GestorDocumental
{
    private const PREFIJO = 'local';

    private const DISCO = 'local';

    public function __construct(private readonly int $minutosPorDefecto) {}

    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        try {
            $ruta = $archivo->store(trim($carpeta, '/'), self::DISCO);
        } catch (Throwable $e) {
            throw new DocumentoNoDisponible('No se pudo escribir el archivo en el disco local.', previous: $e);
        }

        if (! is_string($ruta) || $ruta === '') {
            throw new DocumentoNoDisponible('No se pudo escribir el archivo en el disco local.');
        }

        return self::PREFIJO.':'.$ruta;
    }

    public function urlTemporal(string $puntero, ?int $minutos = null): string
    {
        $ruta = $this->ruta($puntero);
        $disco = Storage::disk(self::DISCO);

        if (! $disco->exists($ruta)) {
            throw new DocumentoNoDisponible("El archivo «{$ruta}» no esta en el disco local.");
        }

        try {
            return $disco->temporaryUrl($ruta, now()->addMinutes($minutos ?? $this->minutosPorDefecto));
        } catch (Throwable $e) {
            throw new DocumentoNoDisponible('No se pudo firmar la descarga del archivo local.', previous: $e);
        }
    }

    private function ruta(string $puntero): string
    {
        $partes = explode(':', $puntero, 2);

        if (count($partes) !== 2 || $partes[0] !== self::PREFIJO || $partes[1] === '') {
            throw new DocumentoNoDisponible("El puntero «{$puntero}» no es del gestor local.");
        }

        return $partes[1];
    }
}
