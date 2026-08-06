<?php

namespace App\Support;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Implementacion contra Cloudinary. Ver GestorDocumental para el porque de la
 * forma del contrato.
 *
 * Dos decisiones que no son de comodidad sino de privacidad:
 *
 * - se sube con `type: authenticated`. Un asset publico de Cloudinary se sirve
 *   a cualquiera que tenga el link, sin sesion; para uno autenticado hace falta
 *   una firma. Es la diferencia entre un resultado de laboratorio que se filtra
 *   con copiar y pegar una URL y uno que no.
 * - la entrega va por `privateDownloadUrl()`, que es la API de descarga firmada
 *   y con vencimiento. Ademas de vencer, no pasa por el CDN de entrega, asi que
 *   no la afecta la opcion de cuenta que bloquea la entrega de PDF (los
 *   resultados de estudios son casi todos PDF).
 *
 * `resource_type: auto` deja que Cloudinary clasifique el archivo, y guardamos
 * su respuesta en el puntero porque `privateDownloadUrl()` necesita despues el
 * mismo tipo y formato con los que quedo guardado.
 */
final class GestorDocumentalCloudinary implements GestorDocumental
{
    private const PREFIJO = 'cloudinary';

    public function __construct(
        private readonly Cloudinary $cloudinary,
        private readonly string $carpetaRaiz,
        private readonly int $minutosPorDefecto,
    ) {}

    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        try {
            $respuesta = $this->cloudinary->uploadApi()->upload($archivo->getRealPath(), [
                'folder' => trim($this->carpetaRaiz.'/'.$carpeta, '/'),
                'resource_type' => 'auto',
                'type' => 'authenticated',
                // El nombre original puede llevar el apellido del paciente; no
                // tiene por que quedar en la URL del asset.
                'use_filename' => false,
                'unique_filename' => true,
            ]);
        } catch (Throwable $e) {
            throw new DocumentoNoDisponible('Cloudinary rechazo la subida del archivo.', previous: $e);
        }

        $publicId = $respuesta['public_id'] ?? null;

        if (blank($publicId)) {
            throw new DocumentoNoDisponible('Cloudinary no devolvio el identificador del archivo subido.');
        }

        // Los ':' separan; un public_id de Cloudinary no los usa (si usa '/'
        // para las carpetas, que por eso queda en el tercer segmento).
        return implode(':', [
            self::PREFIJO,
            $respuesta['resource_type'] ?? 'image',
            $publicId,
            $respuesta['format'] ?? '',
        ]);
    }

    public function urlTemporal(string $puntero, ?int $minutos = null): string
    {
        [$tipoRecurso, $publicId, $formato] = $this->descomponer($puntero);

        try {
            return $this->cloudinary->uploadApi()->privateDownloadUrl($publicId, $formato, [
                'resource_type' => $tipoRecurso,
                'type' => 'authenticated',
                'expires_at' => now()->addMinutes($minutos ?? $this->minutosPorDefecto)->timestamp,
            ]);
        } catch (Throwable $e) {
            throw new DocumentoNoDisponible('No se pudo firmar la descarga del archivo.', previous: $e);
        }
    }

    /**
     * Carpeta raiz configurada (ya con el sufijo de entorno si corresponde). La
     * usa LimpiarDocumentosHuerfanos para no barrer mas alla de lo que esta
     * aplicacion pudo haber escrito.
     */
    public function carpetaRaiz(): string
    {
        return $this->carpetaRaiz;
    }

    /**
     * Acceso al Admin API para el comando de limpieza. El resto de la clase no
     * lo necesita: subir y firmar son operaciones del Upload API.
     */
    public function adminApi(): AdminApi
    {
        return $this->cloudinary->adminApi();
    }

    /**
     * Igual que descomponer(), pero sin lanzar: la usa el comando de limpieza
     * para recorrer los punteros de las dos tablas sin que uno con formato
     * viejo o de otro gestor tire abajo el resto del barrido.
     *
     * @return array{resource_type: string, public_id: string}|null
     */
    public static function referenciaDe(string $puntero): ?array
    {
        $partes = explode(':', $puntero, 4);

        if (count($partes) !== 4 || $partes[0] !== self::PREFIJO) {
            return null;
        }

        return ['resource_type' => $partes[1], 'public_id' => $partes[2]];
    }

    /**
     * El puntero se arma en guardar() como prefijo:resource_type:public_id:format,
     * y sale en ese mismo orden. Ojo con invertir public_id y formato: Cloudinary
     * no rechaza la firma, contesta «Invalid extension in transformation» porque
     * toma el public_id como si fuera la extensión.
     *
     * @return array{0: string, 1: string, 2: string} tipo de recurso, public_id y formato
     */
    private function descomponer(string $puntero): array
    {
        $partes = explode(':', $puntero, 4);

        if (count($partes) !== 4 || $partes[0] !== self::PREFIJO) {
            throw new DocumentoNoDisponible("El puntero «{$puntero}» no es de Cloudinary.");
        }

        return [$partes[1], $partes[2], $partes[3]];
    }
}
