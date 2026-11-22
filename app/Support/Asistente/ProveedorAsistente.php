<?php

namespace App\Support\Asistente;

/**
 * El modelo de lenguaje detras del asistente. Es la unica pieza atada a un
 * proveedor: cambiar de Gemini a otro es escribir otra implementacion y
 * cambiar el binding en AppServiceProvider.
 *
 * Es una interfaz resuelta desde el container por la misma razon que
 * GestorDocumental: hace I/O de red, y los tests la reemplazan para no salir
 * nunca a internet.
 */
interface ProveedorAsistente
{
    /**
     * Manda la conversacion y devuelve la respuesta en trozos, a medida que
     * llegan.
     *
     * El pedido se hace antes de devolver: si falla (sin clave, cuota agotada,
     * error del proveedor) tira AsistenteNoDisponible aca, cuando todavia se
     * puede responder con otro codigo HTTP. Lo que falle despues, a mitad de
     * la respuesta, sale de la iteracion.
     *
     * @param  list<array{rol: 'usuario'|'asistente', texto: string}>  $mensajes
     * @return iterable<string>
     *
     * @throws AsistenteNoDisponible
     */
    public function responder(string $instrucciones, array $mensajes): iterable;
}
