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
        $estadoConfirmada = EstadoCirugia::where('nombreEstadoCirugia', 'Listo para la cirugia')->firstOrFail();

        // Obtener cirugías que ocurran en las próximas 24 horas
        $cirugias = Cirugia::with(ResumenCirugia::RELACIONES)
            ->whereBetween('fechaHoraCirugia', [$ahora, $en24hs])
            ->get();

        $countReprogramar = 0;
        $countConfirmar = 0;

        foreach ($cirugias as $cirugia) {
            $resumen = new ResumenCirugia($cirugia);

            // Si ya está "A reprogramar" o "Reprogramada" o "Suspendida" o "Realizada", saltamos.
            $estadoActual = $resumen->estado();
            if (in_array($estadoActual, ['A reprogramar', 'Reprogramada', 'Suspendida', 'Realizada'])) {
                continue;
            }

            $ultimoEstado = $cirugia->cirugiaEstados()->whereNull('fechaDesasignacionCirugiaEstado')->first();

            $pendientes = $resumen->pendientes();
            $pendientesCriticos = $pendientes->filter(fn ($p) => $p !== 'Consentimiento sin firmar');

            // Si faltan requisitos críticos (autorización, estudios, materiales), la pasamos a "A reprogramar"
            if ($pendientesCriticos->isNotEmpty()) {
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

                $countReprogramar++;
            } elseif ($pendientes->isEmpty()) {
                // Si está lista, y el estado actual no es "Listo para la cirugia", la actualizamos.
                if ($estadoActual !== 'Listo para la cirugia') {
                    if ($ultimoEstado) {
                        $ultimoEstado->update(['fechaDesasignacionCirugiaEstado' => $ahora]);
                    }

                    CirugiaEstado::create([
                        'idCirugia' => $cirugia->idCirugia,
                        'idEstadoCirugia' => $estadoConfirmada->idEstadoCirugia,
                        'fechaAsignacionCirugiaEstado' => $ahora,
                    ]);
                    
                    $countConfirmar++;
                }
            }
        }

        $this->info("Se marcaron {$countReprogramar} cirugías para reprogramar y se confirmaron {$countConfirmar} cirugías listas.");
    }
}
