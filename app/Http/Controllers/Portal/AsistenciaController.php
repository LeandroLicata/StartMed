<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cirugia;
use App\Services\CambiarEstadoCirugiaService;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Confirmacion de asistencia: el paciente responde si viene a una cirugia que
 * es dentro de las proximas 48 horas.
 *
 * Solo cambia el estado. «Necesito reprogramar» no libera el quirofano ni el
 * personal: eso lo hace el gestor cuando reprograma desde el expediente, que
 * es quien sabe si hay otro turno. Tampoco avisa por mail; el gestor lo ve en
 * los paneles por el estado «A reprogramar».
 *
 * La pertenencia de `{cirugia}` la verifica el middleware del grupo de rutas.
 */
class AsistenciaController extends Controller
{
    private const ESTADOS = [
        'confirmar' => 'Asistencia de paciente confirmada',
        'reprogramar' => 'A reprogramar',
    ];

    public function __invoke(Request $request, Cirugia $cirugia, CambiarEstadoCirugiaService $estados): RedirectResponse
    {
        $datos = $request->validate([
            'respuesta' => ['required', 'in:'.implode(',', array_keys(self::ESTADOS))],
        ]);

        // La condicion se vuelve a mirar con la fila bloqueada: dos envios
        // seguidos (doble clic, dos pestañas) no pueden pasar los dos.
        $respondio = DB::transaction(function () use ($cirugia, $datos, $estados) {
            Cirugia::whereKey($cirugia->idCirugia)->lockForUpdate()->first();

            $caso = new ResumenCirugia($cirugia->load(ResumenCirugia::RELACIONES));

            if (! $caso->puedeConfirmarAsistencia()) {
                return false;
            }

            $estados->cambiar($cirugia, self::ESTADOS[$datos['respuesta']]);

            return true;
        });

        $volver = redirect()->route('portal.cirugias.show', $cirugia);

        if (! $respondio) {
            return $volver->with('aviso', 'La asistencia de esta cirugía ya no se puede confirmar desde el portal.');
        }

        return $datos['respuesta'] === 'confirmar'
            ? $volver->with('exito', '¡Gracias! Confirmaste que vas a venir.')
            : $volver->with('info', 'Listo. El equipo del hospital se va a comunicar con vos para coordinar otra fecha.');
    }
}
