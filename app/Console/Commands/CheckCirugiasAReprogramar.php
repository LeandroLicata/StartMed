<?php

namespace App\Console\Commands;

use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\EstadoCirugia;
use App\Support\ResumenCirugia;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckCirugiasAReprogramar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cirugias:check-reprogramar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisa cirugías a menos de 24 horas y si no están listas, las pasa a A reprogramar liberando el quirófano.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ahora = Carbon::now();
        $en24hs = $ahora->copy()->addHours(24);

        $estadoAReprogramar = EstadoCirugia::where('nombreEstadoCirugia', 'A reprogramar')->firstOrFail();

        // Obtener cirugías que ocurran en las próximas 24 horas y que no estén ya en un estado final o "A reprogramar"
        $cirugias = Cirugia::with(ResumenCirugia::RELACIONES)
            ->whereBetween('fechaHoraCirugia', [$ahora, $en24hs])
            ->get();

        $count = 0;

        foreach ($cirugias as $cirugia) {
            $resumen = new ResumenCirugia($cirugia);

            // Si ya está "A reprogramar" o "Reprogramada" o "Suspendida" o "Realizada", saltamos.
            $estadoActual = $resumen->estado();
            if (in_array($estadoActual, ['A reprogramar', 'Reprogramada', 'Suspendida', 'Realizada'])) {
                continue;
            }

            // Si no está lista, la pasamos a "A reprogramar"
            if (! $resumen->estaLista()) {
                // Cerrar estado anterior (si aplica)
                $ultimoEstado = $cirugia->cirugiaEstados()->whereNull('fechaDesasignacionCirugiaEstado')->first();
                if ($ultimoEstado) {
                    $ultimoEstado->update(['fechaDesasignacionCirugiaEstado' => $ahora]);
                }

                // Asignar nuevo estado
                CirugiaEstado::create([
                    'idCirugia' => $cirugia->idCirugia,
                    'idEstadoCirugia' => $estadoAReprogramar->idEstadoCirugia,
                    'fechaAsignacionCirugiaEstado' => $ahora,
                ]);

                // Liberar quirófano
                $quirofanoActivo = $cirugia->cirugiaQuirofanos()->whereNull('fechaHoraDesasignacion')->first();
                if ($quirofanoActivo) {
                    $quirofanoActivo->update(['fechaHoraDesasignacion' => $ahora]);
                }

                $count++;
            }
        }

        $this->info("Se marcaron {$count} cirugías para reprogramar.");
    }
}
