<?php

namespace Database\Seeders;

use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\CirugiaQuirofano;
use App\Models\EstadoCirugia;
use App\Models\GrupoSanguineo;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Quirofano;
use App\Models\TipoCirugia;
use App\Models\TipoDocumento;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seis meses de cirugias cerradas, para que el tablero de Dirección tenga
 * serie historica. Solo carga lo minimo para las metricas: cirugia, estado
 * final y quirofano asignado.
 *
 * La proporcion de suspensiones baja mes a mes a proposito, para que la
 * tendencia se vea en el grafico.
 */
class HistorialSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Volumen y tasa de suspensión por mes, del más viejo al más reciente. */
    private const MESES = [
        6 => ['cirugias' => 34, 'suspensiones' => 4],
        5 => ['cirugias' => 38, 'suspensiones' => 4],
        4 => ['cirugias' => 41, 'suspensiones' => 3],
        3 => ['cirugias' => 45, 'suspensiones' => 3],
        2 => ['cirugias' => 48, 'suspensiones' => 2],
        1 => ['cirugias' => 52, 'suspensiones' => 2],
    ];

    public function run(): void
    {
        $realizada = EstadoCirugia::where('nombreEstadoCirugia', 'Realizada')->value('idEstadoCirugia');
        $suspendida = EstadoCirugia::where('nombreEstadoCirugia', 'Suspendida')->value('idEstadoCirugia');

        $tipos = TipoCirugia::pluck('idTipoCirugia')->all();
        $quirofanos = Quirofano::pluck('idQuirofano')->all();
        $cirujanos = Personal::whereHas('roles', fn ($q) => $q->where('nombreRol', 'Cirujano'))
            ->pluck('idPersonal')->all();
        $anestesistas = Personal::whereHas('roles', fn ($q) => $q->where('nombreRol', 'Anestesista'))
            ->pluck('idPersonal')->all();

        if ($tipos === [] || $quirofanos === [] || $cirujanos === []) {
            return;
        }

        $dni = TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento');
        $gruposSanguineos = GrupoSanguineo::pluck('idGrupoSanguineo')->all();

        $documento = 50_000_000;

        foreach (self::MESES as $mesesAtras => $volumen) {
            $mes = Carbon::today()->subMonthsNoOverflow($mesesAtras)->startOfMonth();

            for ($i = 0; $i < $volumen['cirugias']; $i++) {
                $cuando = $mes->copy()
                    ->addDays(random_int(0, $mes->daysInMonth - 1))
                    ->setTime(random_int(7, 16), [0, 15, 30, 45][random_int(0, 3)]);

                // No se opera los fines de semana.
                if ($cuando->isWeekend()) {
                    $cuando->next(Carbon::MONDAY)->setTime(random_int(7, 16), 0);
                }

                $paciente = Persona::create([
                    'tipo_documento_id' => $dni,
                    'grupo_sanguineo_id' => $gruposSanguineos[array_rand($gruposSanguineos)],
                    'documento' => (string) $documento++,
                    'apellidos' => 'Paciente histórico',
                    'nombres' => 'Nº '.($documento - 50_000_000),
                    'fecha_nacimiento' => Carbon::today()->subYears(random_int(20, 80)),
                ]);

                $cirugia = Cirugia::create([
                    'idPersonaPaciente' => $paciente->idPersona,
                    'idTipoCirugia' => $tipos[array_rand($tipos)],
                    'idPersonalCirujano' => $cirujanos[array_rand($cirujanos)],
                    'idPersonalAnestesista' => $anestesistas ? $anestesistas[array_rand($anestesistas)] : null,
                    'fechaHoraCirugia' => $cuando,
                    'requiereImplante' => random_int(1, 10) <= 2,
                ]);

                CirugiaEstado::create([
                    'idCirugia' => $cirugia->idCirugia,
                    'idEstadoCirugia' => $i < $volumen['suspensiones'] ? $suspendida : $realizada,
                    'fechaAsignacionCirugiaEstado' => $cuando,
                ]);

                CirugiaQuirofano::create([
                    'idCirugia' => $cirugia->idCirugia,
                    'idQuirofano' => $quirofanos[array_rand($quirofanos)],
                    'fechaHoraAsignacion' => $cuando,
                    'fechaHoraDesasignacion' => $cuando->copy()->addHours(2),
                ]);
            }
        }
    }
}
