<?php

namespace App\Support;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Deja registro de las escrituras de la seccion de administracion.
 *
 * El esquema no tiene created_at ni updated_at en ninguna tabla, asi que sin
 * esto no queda rastro de quien creo un usuario o quien dio de baja un
 * catalogo. Se llama a mano desde los controladores en vez de engancharse a
 * los eventos de Eloquent: asi audita lo que hace un administrador y no
 * tambien lo que hacen los seeders o los tests.
 */
final class Auditor
{
    public const ALTA = 'alta';

    public const EDICION = 'edición';

    public const BAJA = 'baja';

    public const REACTIVACION = 'reactivación';

    public const CLAVE = 'cambio de contraseña';

    /**
     * Nunca se guardan, ni siquiera hasheados: un registro de auditoria no es
     * lugar para credenciales.
     */
    private const RESERVADOS = ['passwordUsuario', 'password', 'password_confirmation'];

    /**
     * @param  array<string, array{antes: mixed, despues: mixed}>  $cambios
     */
    public static function registrar(string $accion, Model $registro, string $descripcion, array $cambios = []): void
    {
        Auditoria::create([
            'idUsuario' => auth()->id(),
            'fechaHoraAuditoria' => now(),
            'accionAuditoria' => $accion,
            'tablaAuditoria' => $registro->getTable(),
            'idRegistroAuditoria' => $registro->getKey(),
            'descripcionAuditoria' => $descripcion,
            'cambiosAuditoria' => $cambios === [] ? null : $cambios,
        ]);
    }

    /**
     * Que cambio de verdad al guardar. `getChanges()` trae solo las columnas
     * que Eloquent termino escribiendo, asi que guardar sin tocar nada no
     * ensucia el registro.
     *
     * `$antes` tiene que capturarse ANTES del save: al guardar, el modelo
     * sincroniza sus valores originales con los nuevos.
     *
     * @param  array<string, mixed>  $antes
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    public static function diferencia(Model $registro, array $antes): array
    {
        return collect($registro->getChanges())
            ->except(self::RESERVADOS)
            ->map(fn ($despues, $columna) => [
                'antes' => self::legible($antes[$columna] ?? null),
                'despues' => self::legible($despues),
            ])
            ->all();
    }

    /**
     * Una foto de las columnas del registro, para pasarle a diferencia().
     *
     * @return array<string, mixed>
     */
    public static function foto(Model $registro): array
    {
        return $registro->getAttributes();
    }

    /**
     * Un cambio que no sale de una columna, como el juego de roles vigentes
     * de un usuario.
     *
     * @param  list<string>  $antes
     * @param  list<string>  $despues
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    public static function listaCambiada(string $campo, array $antes, array $despues): array
    {
        sort($antes);
        sort($despues);

        if ($antes === $despues) {
            return [];
        }

        return [$campo => [
            'antes' => implode(', ', $antes) ?: '—',
            'despues' => implode(', ', $despues) ?: '—',
        ]];
    }

    /**
     * Los valores van a JSON y despues a una pantalla, asi que se guardan como
     * texto plano. Las fechas quedan legibles y los booleanos no quedan como
     * "1" y "".
     */
    private static function legible(mixed $valor): ?string
    {
        return match (true) {
            $valor === null || $valor === '' => null,
            is_bool($valor) => $valor ? 'Sí' : 'No',
            $valor instanceof \DateTimeInterface => $valor->format('d/m/Y H:i'),
            default => (string) $valor,
        };
    }
}
