<?php

namespace App\Console\Commands;

use App\Models\CirugiaTipoEstudio;
use App\Models\HisopadoSarm;
use App\Support\GestorDocumental;
use App\Support\GestorDocumentalCloudinary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Barre la cuenta de Cloudinary buscando adjuntos que ningún
 * CirugiaTipoEstudio ni HisopadoSarm referencia, y opcionalmente los borra.
 *
 * Reemplazar un archivo (subir uno nuevo sobre un estudio o un hisopado que ya
 * tenía adjunto) sobreescribe el puntero en la base: el anterior queda en
 * Cloudinary sin que nada vuelva a apuntarlo. El resto del esquema nunca hace
 * un DELETE de verdad —todo es fechaBaja*—, así que este comando sigue esa
 * misma convención por default: reporta, no borra. Borrar es un --borrar
 * explícito y una corrida manual; ver routes/console.php, donde sólo se
 * programa el modo reporte. Un cron que borra archivos clínicos sin que nadie
 * lo mire es exactamente el tipo de acción difícil de revertir que no debería
 * correr sola.
 *
 * Sólo actúa si el gestor resuelto es GestorDocumentalCloudinary. Contra el
 * disco local no hay cuenta que limpiar, y storage/app/private no tiene el
 * problema de cuota/costo que tiene esto en Cloudinary.
 *
 * El barrido queda acotado a la carpeta configurada (`config('cloudinary.carpeta')`,
 * que por default ya incluye el entorno: startmed-local, startmed-production).
 * Si dos entornos comparten la misma cuenta de Cloudinary pero cada uno tiene
 * su propia base de datos, cruzar TODA la cuenta contra "lo que referencia
 * ESTA base" borraría archivos que el otro entorno todavía usa. Acotar por
 * carpeta lo vuelve imposible en vez de "hay que tener cuidado".
 */
class LimpiarDocumentosHuerfanos extends Command
{
    protected $signature = 'documentos:limpiar-huerfanos
        {--borrar : Borra los huérfanos encontrados. Sin esta opción sólo los reporta.}
        {--dias=30 : Antigüedad mínima, en días, para considerar huérfano un archivo.}';

    protected $description = 'Reporta (u opcionalmente borra) los adjuntos de Cloudinary que ningún estudio ni hisopado referencia.';

    /**
     * Los archivos se suben con resource_type: auto (ver GestorDocumentalCloudinary::guardar).
     * Para los mimes que la app acepta —pdf, jpg, jpeg, png— Cloudinary siempre
     * clasificó 'image' en la práctica; 'raw' queda como red de seguridad para
     * el caso en que alguna vez no pueda clasificar el contenido.
     */
    private const TIPOS_DE_RECURSO = ['image', 'raw'];

    public function handle(): int
    {
        $gestor = app(GestorDocumental::class);

        if (! $gestor instanceof GestorDocumentalCloudinary) {
            $this->info('El gestor documental activo es el disco local: no hay cuenta de Cloudinary que limpiar.');

            return self::SUCCESS;
        }

        $referenciados = $this->punterosReferenciados();
        $carpeta = $gestor->carpetaRaiz();
        $dias = (int) $this->option('dias');
        $limite = Carbon::now()->subDays($dias);

        $this->info("Barriendo Cloudinary bajo «{$carpeta}/» (archivos de más de {$dias} días)...");

        $huerfanos = collect();

        foreach (self::TIPOS_DE_RECURSO as $tipoRecurso) {
            foreach ($this->listarAssets($gestor, $carpeta, $tipoRecurso) as $asset) {
                $clave = $tipoRecurso.':'.$asset['public_id'];

                if ($referenciados->has($clave)) {
                    continue;
                }

                $subido = Carbon::parse($asset['created_at']);

                if ($subido->greaterThan($limite)) {
                    continue; // Muy reciente: puede ser una subida recién hecha.
                }

                $huerfanos->push([
                    'resource_type' => $tipoRecurso,
                    'public_id' => $asset['public_id'],
                    'bytes' => $asset['bytes'] ?? 0,
                    'subido' => $subido,
                ]);
            }
        }

        if ($huerfanos->isEmpty()) {
            $this->info('No se encontraron huérfanos.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tipo', 'Public ID', 'Peso', 'Subido'],
            $huerfanos->map(fn (array $h) => [
                $h['resource_type'],
                $h['public_id'],
                number_format($h['bytes'] / 1024, 1).' KB',
                $h['subido']->toDateString(),
            ]),
        );

        $this->warn(sprintf('%d huérfanos, %.1f MB en total.', $huerfanos->count(), $huerfanos->sum('bytes') / 1024 / 1024));

        if (! $this->option('borrar')) {
            $this->comment('Modo reporte: no se borró nada. Repetí el comando con --borrar para eliminarlos.');

            return self::SUCCESS;
        }

        foreach ($huerfanos->groupBy('resource_type') as $tipoRecurso => $grupo) {
            // deleteAssets acepta hasta 100 public_ids por llamada.
            foreach ($grupo->pluck('public_id')->chunk(100) as $lote) {
                $gestor->adminApi()->deleteAssets($lote->all(), [
                    'resource_type' => $tipoRecurso,
                    'type' => 'authenticated',
                ]);
            }
        }

        Log::info('documentos:limpiar-huerfanos borró archivos de Cloudinary.', [
            'carpeta' => $carpeta,
            'cantidad' => $huerfanos->count(),
            'public_ids' => $huerfanos->pluck('public_id')->all(),
        ]);

        $this->info("Se borraron {$huerfanos->count()} archivos.");

        return self::SUCCESS;
    }

    /**
     * Claves "resource_type:public_id" de todo lo que sigue vigente. Punteros
     * del disco local (u otro formato) se descartan en vez de romper el
     * barrido: referenciaDe() no lanza para eso.
     *
     * @return Collection<string, true>
     */
    public function punterosReferenciados(): Collection
    {
        return CirugiaTipoEstudio::query()
            ->whereNotNull('urlArchivoCirugiaTipoEstudio')
            ->pluck('urlArchivoCirugiaTipoEstudio')
            ->merge(
                HisopadoSarm::query()->whereNotNull('urlHisopadoSarm')->pluck('urlHisopadoSarm'),
            )
            ->map(fn (string $puntero) => GestorDocumentalCloudinary::referenciaDe($puntero))
            ->filter()
            ->mapWithKeys(fn (array $r) => [$r['resource_type'].':'.$r['public_id'] => true]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function listarAssets(GestorDocumentalCloudinary $gestor, string $carpeta, string $tipoRecurso): Collection
    {
        $items = collect();
        $cursor = null;

        do {
            $respuesta = $gestor->adminApi()->assets(array_filter([
                'resource_type' => $tipoRecurso,
                'type' => 'authenticated',
                'prefix' => $carpeta.'/',
                'max_results' => 500,
                'next_cursor' => $cursor,
            ]));

            $items = $items->merge($respuesta['resources'] ?? []);
            $cursor = $respuesta['next_cursor'] ?? null;
        } while ($cursor);

        return $items;
    }
}
