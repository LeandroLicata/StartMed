<?php

namespace App\Support\Asistente;

use RuntimeException;
use Throwable;

/**
 * El proveedor no pudo responder. `saturado` distingue el caso esperado
 * —cuota del free tier agotada, se arregla esperando— de una falla real, para
 * que el usuario reciba el mensaje que corresponde.
 *
 * El mensaje es para el log y nunca lleva lo que escribio el usuario.
 */
final class AsistenteNoDisponible extends RuntimeException
{
    private function __construct(string $mensaje, public readonly bool $saturado = false, ?Throwable $previa = null)
    {
        parent::__construct($mensaje, 0, $previa);
    }

    public static function sinClave(): self
    {
        return new self('falta GEMINI_API_KEY');
    }

    public static function saturado(): self
    {
        return new self('Gemini respondió 429 (cuota agotada)', saturado: true);
    }

    public static function fallo(string $detalle, ?Throwable $previa = null): self
    {
        return new self($detalle, previa: $previa);
    }
}
