<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro de activos / dados de baja, compartido por los listados de la seccion
 * de administracion.
 *
 * Vive aparte porque la columna de baja cambia en cada tabla (fechaBajaX,
 * fechaHoraBajaRol, fechaEstablecimiento) pero la pregunta es siempre la
 * misma, y porque las tres opciones tienen que llamarse igual en las 27
 * pantallas que las ofrecen.
 *
 * Se llama FiltroBaja y no Estado para no confundirlo con los catalogos de
 * estados del dominio (EstadoCirugia, EstadoQuirofano...).
 */
final class FiltroBaja
{
    /** Es el valor por defecto, de ahi que sea la cadena vacia. */
    public const ACTIVOS = '';

    public const BAJAS = 'bajas';

    public const TODOS = 'todos';

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return [
            self::ACTIVOS => 'Activos',
            self::BAJAS => 'Dados de baja',
            self::TODOS => 'Todos',
        ];
    }

    /**
     * Un valor que no venga de las opciones se trata como el por defecto, asi
     * un query string escrito a mano no rompe el listado.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     */
    public static function aplicar(Builder $consulta, string $estado, string $columnaBaja): Builder
    {
        return match ($estado) {
            self::BAJAS => $consulta->whereNotNull($columnaBaja),
            self::TODOS => $consulta,
            default => $consulta->whereNull($columnaBaja),
        };
    }
}
