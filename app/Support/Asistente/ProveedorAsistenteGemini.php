<?php

namespace App\Support\Asistente;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Gemini por su API REST, sin SDK: Google no publica uno oficial para PHP y
 * el endpoint con Server-Sent Events se resuelve con el cliente HTTP de
 * Laravel, que ademas se puede falsear en los tests.
 */
final class ProveedorAsistenteGemini implements ProveedorAsistente
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:streamGenerateContent?alt=sse';

    public function __construct(
        private readonly ?string $clave,
        private readonly string $modelo,
        private readonly string $nivelRazonamiento,
        private readonly float $temperatura,
        private readonly int $maxTokens,
        private readonly int $timeout,
    ) {}

    public function responder(string $instrucciones, array $mensajes): iterable
    {
        if (blank($this->clave)) {
            throw AsistenteNoDisponible::sinClave();
        }

        try {
            $respuesta = Http::withHeaders(['x-goog-api-key' => $this->clave])
                ->withOptions(['stream' => true])
                ->connectTimeout(5)
                ->timeout($this->timeout)
                ->post(sprintf(self::URL, rawurlencode($this->modelo)), [
                    'systemInstruction' => ['parts' => [['text' => $instrucciones]]],
                    'contents' => array_map(fn (array $mensaje) => [
                        'role' => $mensaje['rol'] === 'asistente' ? 'model' : 'user',
                        'parts' => [['text' => $mensaje['texto']]],
                    ], $mensajes),
                    'generationConfig' => [
                        'temperature' => $this->temperatura,
                        'maxOutputTokens' => $this->maxTokens,
                        // Los Gemini 3.x razonan por defecto. Para responder sobre
                        // una guia que ya esta entera en el prompt no aporta y
                        // consume cuota.
                        'thinkingConfig' => ['thinkingLevel' => $this->nivelRazonamiento],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw AsistenteNoDisponible::fallo('sin conexión con Gemini', $e);
        }

        // 429 = cuota del free tier agotada. Es el modo de falla esperado.
        if ($respuesta->status() === 429) {
            throw AsistenteNoDisponible::saturado();
        }

        if ($respuesta->failed()) {
            // El cuerpo del error de Gemini describe el pedido (modelo, campo
            // invalido), no la conversacion: se puede loguear.
            $detalle = mb_substr((string) ($respuesta->json('error.message') ?? ''), 0, 300);

            throw AsistenteNoDisponible::fallo("Gemini respondió {$respuesta->status()}: {$detalle}");
        }

        return $this->trozos($respuesta->toPsrResponse()->getBody());
    }

    /**
     * Lee los eventos SSE a medida que llegan. Cada uno es una linea
     * `data: {json}`; una lectura puede cortar una linea al medio, asi que
     * se acumula hasta el salto.
     *
     * @return Generator<int, string>
     */
    private function trozos(StreamInterface $cuerpo): Generator
    {
        $pendiente = '';

        while (! $cuerpo->eof()) {
            $pendiente .= $cuerpo->read(1024);

            while (($fin = strpos($pendiente, "\n")) !== false) {
                $texto = $this->textoDe(substr($pendiente, 0, $fin));
                $pendiente = substr($pendiente, $fin + 1);

                if ($texto !== '') {
                    yield $texto;
                }
            }
        }

        $texto = $this->textoDe($pendiente);

        if ($texto !== '') {
            yield $texto;
        }
    }

    private function textoDe(string $linea): string
    {
        $linea = trim($linea);

        if (! str_starts_with($linea, 'data:')) {
            return '';
        }

        $evento = json_decode(trim(substr($linea, 5)), true);

        if (! is_array($evento)) {
            return '';
        }

        if (isset($evento['error'])) {
            throw AsistenteNoDisponible::fallo('Gemini cortó la respuesta: '.mb_substr((string) ($evento['error']['message'] ?? ''), 0, 300));
        }

        // Las partes de razonamiento (thought) no son para el usuario.
        return collect($evento['candidates'][0]['content']['parts'] ?? [])
            ->reject(fn ($parte) => ! is_array($parte) || ($parte['thought'] ?? false))
            ->pluck('text')
            ->filter(fn ($texto) => is_string($texto))
            ->implode('');
    }
}
