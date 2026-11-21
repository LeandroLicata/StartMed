<?php

namespace App\Support;

/**
 * Identificador corto de la version desplegada segun el repositorio.
 *
 * En Render `.dockerignore` excluye `.git`, asi que el deploy lee
 * `RENDER_GIT_COMMIT` (cacheado en `config('app.commit')` durante
 * `php artisan config:cache`). En desarrollo local lee `.git/HEAD`.
 */
final class VersionApp
{
    public static function actual(): ?string
    {
        $version = trim((string) config('app.version', ''));
        $commit = self::commitCorto();

        return match (true) {
            $version !== '' && $commit !== null => "{$version} ({$commit})",
            $version !== '' => $version,
            $commit !== null => "rev. {$commit}",
            default => null,
        };
    }

    private static function commitCorto(): ?string
    {
        $configurado = trim((string) config('app.commit', ''));

        if ($configurado !== '') {
            return substr($configurado, 0, 7);
        }

        return self::commitDesdeGit();
    }

    private static function commitDesdeGit(): ?string
    {
        $headPath = base_path('.git/HEAD');

        if (! is_file($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));

        if ($head === '') {
            return null;
        }

        if (str_starts_with($head, 'ref: ')) {
            $ref = trim(substr($head, 5));
            $refPath = base_path('.git/'.$ref);

            if (is_file($refPath)) {
                return self::extraerHash((string) file_get_contents($refPath));
            }

            return self::buscarEnPackedRefs($ref);
        }

        return self::extraerHash($head);
    }

    private static function buscarEnPackedRefs(string $ref): ?string
    {
        $packedPath = base_path('.git/packed-refs');

        if (! is_file($packedPath)) {
            return null;
        }

        $lineas = file($packedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lineas as $linea) {
            if (str_starts_with($linea, '#') || str_starts_with($linea, '^')) {
                continue;
            }

            $partes = preg_split('/\s+/', trim($linea), 2);

            if (count($partes) === 2 && $partes[1] === $ref) {
                return self::extraerHash($partes[0]);
            }
        }

        return null;
    }

    private static function extraerHash(string $contenido): ?string
    {
        $hash = trim($contenido);

        if (! preg_match('/^[0-9a-f]{7,40}$/i', $hash)) {
            return null;
        }

        return substr($hash, 0, 7);
    }
}
