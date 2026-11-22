<?php

namespace Tests\Feature;

use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\ProveedorAsistente;
use App\Support\Asistente\ProveedorAsistenteGemini;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El proveedor de Gemini contra HTTP falseado: que arme bien el pedido, que
 * lea bien el stream SSE y que traduzca cada falla. Nunca sale a la red.
 */
class AsistenteGeminiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function proveedor(?string $clave = 'clave-de-prueba'): ProveedorAsistenteGemini
    {
        return new ProveedorAsistenteGemini($clave, 'gemini-de-prueba', 'MINIMAL', 0.3, 700, 10);
    }

    /**
     * @param  list<array<string, mixed>>  $eventos
     */
    private function sse(array $eventos): string
    {
        return collect($eventos)
            ->map(fn (array $evento) => 'data: '.json_encode($evento)."\r\n\r\n")
            ->implode('');
    }

    /**
     * @param  list<array<string, mixed>>  $partes
     * @return array<string, mixed>
     */
    private function evento(array $partes): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => $partes]]]];
    }

    private function responder(ProveedorAsistenteGemini $proveedor, array $mensajes = [['rol' => 'usuario', 'texto' => 'Hola']]): string
    {
        return implode('', iterator_to_array($proveedor->responder('Instrucciones', $mensajes), false));
    }

    public function test_arma_el_pedido_y_junta_los_trozos(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->sse([
            $this->evento([['text' => 'Andá a ']]),
            $this->evento([['text' => 'Agenda.']]),
        ]), 200, ['Content-Type' => 'text/event-stream'])]);

        $texto = $this->responder($this->proveedor(), [
            ['rol' => 'usuario', 'texto' => '¿Dónde veo la agenda?'],
            ['rol' => 'asistente', 'texto' => 'En el menú.'],
            ['rol' => 'usuario', 'texto' => '¿Y por día?'],
        ]);

        $this->assertSame('Andá a Agenda.', $texto);

        Http::assertSent(fn (Request $pedido) => $pedido->hasHeader('x-goog-api-key', 'clave-de-prueba')
            && str_contains($pedido->url(), 'models/gemini-de-prueba:streamGenerateContent?alt=sse')
            && $pedido['systemInstruction']['parts'][0]['text'] === 'Instrucciones'
            && array_column($pedido['contents'], 'role') === ['user', 'model', 'user']
            && $pedido['contents'][2]['parts'][0]['text'] === '¿Y por día?'
            && $pedido['generationConfig']['thinkingConfig']['thinkingLevel'] === 'MINIMAL'
            && $pedido['generationConfig']['maxOutputTokens'] === 700);
    }

    public function test_descarta_el_razonamiento_y_los_eventos_que_no_son_texto(): void
    {
        Http::fake(['*' => Http::response(
            ": comentario SSE\r\n\r\n"
            .$this->sse([
                $this->evento([['text' => 'pensando...', 'thought' => true], ['text' => 'Respuesta']]),
                ['usageMetadata' => ['totalTokenCount' => 10]],
            ])
            ."data: esto no es json\r\n\r\n",
        )]);

        $this->assertSame('Respuesta', $this->responder($this->proveedor()));
    }

    /**
     * El cuerpo se lee de a 1024 bytes: un evento mas largo queda partido
     * entre lecturas y tiene que llegar entero igual.
     */
    public function test_un_evento_partido_entre_lecturas_llega_entero(): void
    {
        $largo = str_repeat('áéíóú ', 600);

        Http::fake(['*' => Http::response($this->sse([$this->evento([['text' => $largo]])]))]);

        $this->assertSame($largo, $this->responder($this->proveedor()));
    }

    public function test_sin_clave_no_sale_a_la_red(): void
    {
        Http::fake();

        try {
            $this->proveedor(null)->responder('Instrucciones', [['rol' => 'usuario', 'texto' => 'Hola']]);
            $this->fail('Sin clave tendría que fallar.');
        } catch (AsistenteNoDisponible $e) {
            $this->assertFalse($e->saturado);
        }

        Http::assertNothingSent();
    }

    public function test_un_429_es_cuota_agotada(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Resource exhausted']], 429)]);

        try {
            $this->proveedor()->responder('Instrucciones', [['rol' => 'usuario', 'texto' => 'Hola']]);
            $this->fail('Un 429 tendría que fallar.');
        } catch (AsistenteNoDisponible $e) {
            $this->assertTrue($e->saturado);
        }
    }

    public function test_otro_error_es_una_falla_con_el_detalle_de_gemini(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'thinking level not supported']], 400)]);

        try {
            $this->proveedor()->responder('Instrucciones', [['rol' => 'usuario', 'texto' => 'Hola']]);
            $this->fail('Un 400 tendría que fallar.');
        } catch (AsistenteNoDisponible $e) {
            $this->assertFalse($e->saturado);
            $this->assertStringContainsString('400', $e->getMessage());
            $this->assertStringContainsString('thinking level not supported', $e->getMessage());
        }
    }

    public function test_sin_conexion_es_una_falla(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(AsistenteNoDisponible::class);

        $this->proveedor()->responder('Instrucciones', [['rol' => 'usuario', 'texto' => 'Hola']]);
    }

    public function test_un_error_a_mitad_del_stream_corta_la_iteracion(): void
    {
        Http::fake(['*' => Http::response($this->sse([
            $this->evento([['text' => 'Primero ']]),
            ['error' => ['code' => 500, 'message' => 'internal']],
        ]))]);

        $trozos = $this->proveedor()->responder('Instrucciones', [['rol' => 'usuario', 'texto' => 'Hola']]);

        $recibido = '';

        try {
            foreach ($trozos as $trozo) {
                $recibido .= $trozo;
            }
            $this->fail('El error del stream tendría que cortar la iteración.');
        } catch (AsistenteNoDisponible) {
            $this->assertSame('Primero ', $recibido);
        }
    }

    public function test_el_container_resuelve_gemini(): void
    {
        $this->assertInstanceOf(ProveedorAsistenteGemini::class, app(ProveedorAsistente::class));
    }
}
