<?php

namespace App\Console\Commands;

use App\Http\Controllers\AsistenteController;
use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\InstruccionesAsistente;
use App\Support\Asistente\ProveedorAsistente;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Corre los casos de tests/Asistente/evals.json contra el modelo real.
 *
 * Un asistente que suena bien no es uno que funciona: la pregunta que importa
 * es si inventa pantallas, si da indicaciones clinicas o si se deja sacar del
 * rol, y eso solo se ve preguntandole al modelo de verdad. Por eso no es un
 * test de PHPUnit —la suite nunca sale a la red— sino un comando aparte que
 * gasta cuota y se corre a mano o en CI con la clave configurada.
 *
 * Arma las instrucciones con InstruccionesAsistente y le habla al proveedor
 * directo, sin pasar por HTTP: el endpoint pide sesion, y lo que agrega
 * (validacion, topes, streaming) ya lo cubre AsistenteTest. Sale con codigo 1
 * si algun caso falla.
 */
class EvaluarAsistente extends Command
{
    protected $signature = 'asistente:evaluar
        {--solo= : Corre solo los casos cuyo nombre contenga este texto}
        {--pausa=5 : Segundos entre casos, para no chocar con el límite por minuto del free tier}';

    protected $description = 'Corre los evals del asistente contra Gemini real (gasta cuota)';

    public function handle(ProveedorAsistente $proveedor): int
    {
        if (! AsistenteController::habilitado()) {
            $this->error('Falta GEMINI_API_KEY: los evals hablan con el modelo real.');

            return self::FAILURE;
        }

        $datos = json_decode(
            (string) file_get_contents(base_path('tests/Asistente/evals.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $vocabulario = $datos['vocabulario'] ?? [];
        $solo = $this->option('solo');

        $casos = collect($datos['cases'])
            ->when($solo, fn (Collection $casos) => $casos->filter(
                fn (array $caso) => str_contains($this->normalizar($caso['name']), $this->normalizar($solo)),
            ))
            ->values();

        if ($casos->isEmpty()) {
            $this->error('Ningún caso coincide con --solo.');

            return self::FAILURE;
        }

        $this->line('Modelo: '.config('asistente.modelo').' · '.$casos->count().' casos');
        $this->newLine();

        $fallidos = 0;

        foreach ($casos as $i => $caso) {
            if ($i > 0) {
                sleep((int) $this->option('pausa'));
            }

            $instrucciones = ($caso['paciente'] ?? false)
                ? InstruccionesAsistente::paraPaciente()
                : InstruccionesAsistente::paraStaff($caso['roles'] ?? []);

            $mensajes = [...($caso['history'] ?? []), ['rol' => 'usuario', 'texto' => $caso['question']]];

            try {
                $respuesta = $this->preguntar($proveedor, $instrucciones, $mensajes);
                $problemas = $this->verificar($respuesta, $caso, $vocabulario);
            } catch (AsistenteNoDisponible $e) {
                $respuesta = '';
                $problemas = ['el proveedor falló: '.$e->getMessage()];
            }

            if ($problemas === []) {
                $this->line("<fg=green>✓</> {$caso['name']}");

                continue;
            }

            $fallidos++;
            $this->line("<fg=red>✗</> {$caso['name']}");

            foreach ($problemas as $problema) {
                $this->line("    <fg=red>{$problema}</>");
            }

            $this->line('    <fg=gray>Respuesta: '.Str::limit(preg_replace('/\s+/', ' ', $respuesta), 400).'</>');
        }

        $this->newLine();
        $pasaron = $casos->count() - $fallidos;
        $fallidos === 0
            ? $this->info("Pasaron los {$pasaron} casos.")
            : $this->error("Fallaron {$fallidos} de {$casos->count()} casos.");

        return $fallidos === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Si la cuota del minuto se agoto, espera la ventana y reintenta una vez
     * antes de dar el caso por fallado.
     *
     * @param  list<array{rol: string, texto: string}>  $mensajes
     */
    private function preguntar(ProveedorAsistente $proveedor, string $instrucciones, array $mensajes): string
    {
        try {
            return implode('', iterator_to_array($proveedor->responder($instrucciones, $mensajes), false));
        } catch (AsistenteNoDisponible $e) {
            if (! $e->saturado) {
                throw $e;
            }

            $this->warn('  Cuota del minuto agotada: espero 60 s y reintento.');
            sleep(60);

            return implode('', iterator_to_array($proveedor->responder($instrucciones, $mensajes), false));
        }
    }

    /**
     * @param  array<string, mixed>  $caso
     * @param  array<string, list<string>>  $vocabulario
     * @return list<string>
     */
    private function verificar(string $respuesta, array $caso, array $vocabulario): array
    {
        $texto = $this->normalizar($respuesta);
        $contiene = fn (string $patron) => str_contains($texto, $this->normalizar($patron));
        $problemas = [];

        $todos = $this->expandir($caso['expectAll'] ?? [], $vocabulario);
        $alguno = $this->expandir($caso['expectAny'] ?? [], $vocabulario);
        $prohibidos = $this->expandir($caso['reject'] ?? [], $vocabulario);

        foreach ($todos as $patron) {
            if (! $contiene($patron)) {
                $problemas[] = "falta «{$patron}»";
            }
        }

        if ($alguno !== [] && ! collect($alguno)->contains($contiene)) {
            $problemas[] = 'no aparece ninguno de: '.implode(', ', $alguno);
        }

        foreach ($prohibidos as $patron) {
            if ($contiene($patron)) {
                $problemas[] = "aparece «{$patron}», que no debería";
            }
        }

        return $problemas;
    }

    /**
     * @param  list<string>  $patrones
     * @param  array<string, list<string>>  $vocabulario
     * @return list<string>
     */
    private function expandir(array $patrones, array $vocabulario): array
    {
        return collect($patrones)
            ->flatMap(function (string $patron) use ($vocabulario) {
                if (! str_starts_with($patron, '$')) {
                    return [$patron];
                }

                return $vocabulario[substr($patron, 1)]
                    ?? throw new InvalidArgumentException("El vocabulario «{$patron}» no existe en evals.json.");
            })
            ->values()
            ->all();
    }

    /**
     * Minusculas y sin acentos, para que «Sí» y «si» cuenten igual.
     */
    private function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii($texto));
    }
}
