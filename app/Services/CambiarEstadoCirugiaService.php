<?php

namespace App\Services;

use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\EstadoCirugia;
use Illuminate\Support\Facades\DB;

/**
 * El unico lugar que mueve una cirugia de estado.
 *
 * El estado es historial con rango de fechas: cambiarlo es cerrar el
 * CirugiaEstado vigente (fechaDesasignacion = ahora) y abrir uno nuevo, nunca
 * pisar el registro. La fila de la cirugia se bloquea mientras tanto, para que
 * dos cambios simultaneos (un doble clic, el cron y el paciente a la vez) no
 * dejen dos estados vigentes.
 *
 * Solo cambia el estado. Lo que acompaña a cada transicion (liberar el
 * quirofano al suspender, por ejemplo) lo decide quien la pide.
 */
class CambiarEstadoCirugiaService
{
    /**
     * @param  string  $estado  Nombre exacto en EstadoCirugia. Tiene que estar en
     *                          los protegidos de Catalogos: si no, renombrarlo
     *                          desde el ABM rompe esta llamada.
     */
    public function cambiar(Cirugia $cirugia, string $estado): CirugiaEstado
    {
        $idEstado = EstadoCirugia::where('nombreEstadoCirugia', $estado)->firstOrFail()->idEstadoCirugia;

        return DB::transaction(function () use ($cirugia, $idEstado) {
            Cirugia::whereKey($cirugia->idCirugia)->lockForUpdate()->first();

            $ahora = now();

            CirugiaEstado::where('idCirugia', $cirugia->idCirugia)
                ->whereNull('fechaDesasignacionCirugiaEstado')
                ->update(['fechaDesasignacionCirugiaEstado' => $ahora]);

            $nuevo = CirugiaEstado::create([
                'idCirugia' => $cirugia->idCirugia,
                'idEstadoCirugia' => $idEstado,
                'fechaAsignacionCirugiaEstado' => $ahora,
            ]);

            // Si la relacion ya estaba cargada, quedo vieja: que la proxima
            // lectura la traiga de nuevo en vez de mostrar el estado anterior.
            $cirugia->unsetRelation('cirugiaEstados');

            return $nuevo;
        });
    }
}
