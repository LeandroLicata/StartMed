<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsistenteRequest;
use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\InstruccionesAsistente;
use App\Support\Asistente\ProveedorAsistente;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * El asistente de uso, para el staff y para el portal del paciente. Responde
 * texto plano en streaming: el widget lo va mostrando a medida que llega.
 *
 * No guarda nada: ni la conversacion ni un registro de lo que se pregunto.
 * Los logs de error nunca llevan lo que escribio el usuario.
 */
class AsistenteController extends Controller
{
    public const GENERICO = 'No pude responder ahora. Probá de nuevo en un rato.';

    public const SATURADO = 'El asistente está recibiendo muchas consultas. Probá de nuevo en un rato.';

    // Lo responde el rate limiter propio (AppServiceProvider), no el proveedor.
    public const DEMASIADAS = 'Hiciste muchas consultas seguidas. Esperá un momento y probá de nuevo.';

    public function __construct(private readonly ProveedorAsistente $proveedor) {}

    public static function habilitado(): bool
    {
        return filled(config('asistente.clave'));
    }

    public function staff(AsistenteRequest $request): Response|StreamedResponse
    {
        return $this->responder(
            InstruccionesAsistente::paraStaff($request->user()->roles()),
            $request->mensajes(),
        );
    }

    public function paciente(AsistenteRequest $request): Response|StreamedResponse
    {
        return $this->responder(InstruccionesAsistente::paraPaciente(), $request->mensajes());
    }

    /**
     * @param  list<array{rol: 'usuario'|'asistente', texto: string}>  $mensajes
     */
    private function responder(string $instrucciones, array $mensajes): Response|StreamedResponse
    {
        abort_unless(self::habilitado(), 404);

        try {
            $trozos = $this->proveedor->responder($instrucciones, $mensajes);
        } catch (AsistenteNoDisponible $e) {
            Log::error('[asistente] '.$e->getMessage());

            return $e->saturado
                ? $this->texto(self::SATURADO, 503)
                : $this->texto(self::GENERICO, 502);
        }

        return response()->stream(function () use ($trozos) {
            $algo = false;

            try {
                foreach ($trozos as $trozo) {
                    $algo = true;

                    yield $trozo;
                }
            } catch (Throwable $e) {
                // El stream ya empezo: no se puede cambiar el status, asi que se
                // cierra con una nota visible en lugar de cortar en seco.
                Log::error('[asistente] la respuesta se cortó: '.$e->getMessage());

                yield ($algo ? "\n\n" : '').'['.self::GENERICO.']';

                return;
            }

            // Respuesta vacia (por ejemplo, la bloqueo un filtro del proveedor).
            if (! $algo) {
                yield self::GENERICO;
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function texto(string $mensaje, int $status): Response
    {
        return response($mensaje, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
