<?php

namespace Tests\Fakes;

use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\ProveedorAsistente;
use Generator;
use Throwable;

/**
 * Proveedor del asistente para los tests: responde trozos fijos y anota que
 * recibio, para poder afirmar sobre el prompt y la conversacion sin salir a
 * la red.
 */
final class ProveedorAsistenteFalso implements ProveedorAsistente
{
    public ?string $instrucciones = null;

    /** @var list<array{rol: string, texto: string}> */
    public array $mensajes = [];

    public int $llamadas = 0;

    /**
     * @param  list<string>  $trozos
     */
    public function __construct(
        private readonly array $trozos = ['Hola', ', ¿en qué te ayudo?'],
        private readonly ?AsistenteNoDisponible $falla = null,
        private readonly ?Throwable $fallaAMitad = null,
    ) {}

    public function responder(string $instrucciones, array $mensajes): iterable
    {
        $this->llamadas++;
        $this->instrucciones = $instrucciones;
        $this->mensajes = $mensajes;

        if ($this->falla) {
            throw $this->falla;
        }

        return $this->trozos();
    }

    private function trozos(): Generator
    {
        yield from $this->trozos;

        if ($this->fallaAMitad) {
            throw $this->fallaAMitad;
        }
    }
}
