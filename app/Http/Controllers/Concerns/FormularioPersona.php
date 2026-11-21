<?php

namespace App\Http\Controllers\Concerns;

use App\Models\GrupoSanguineo;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Support\Auditor;

/**
 * Datos de catalogos y escritura auditada compartidos entre el padron de
 * pacientes (Gestor de quirofano) y la seccion Personas (Administrador).
 */
trait FormularioPersona
{
    /**
     * @return array{
     *     tiposDocumento: array<int, string>,
     *     gruposSanguineos: array<int, string>,
     *     generos: array<string, string>,
     * }
     */
    private function catalogosPersona(): array
    {
        return [
            'tiposDocumento' => TipoDocumento::query()
                ->whereNull('fechaBajaTipoDocumento')
                ->orderBy('nombreTipoDocumento')
                ->pluck('nombreTipoDocumento', 'idTipoDocumento')
                ->all(),
            'gruposSanguineos' => GrupoSanguineo::query()
                ->whereNull('fechaBajaGrupoSanguineo')
                ->orderBy('nombreGrupoSanguineo')
                ->pluck('nombreGrupoSanguineo', 'idGrupoSanguineo')
                ->all(),
            'generos' => [
                'M' => 'Masculino',
                'F' => 'Femenino',
                'X' => 'Otro',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearPersonaAuditada(array $datos): Persona
    {
        $persona = Persona::create($datos);

        Auditor::registrar(Auditor::ALTA, $persona, 'Persona «'.$persona->nombre_completo.'»');

        return $persona;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function actualizarPersonaAuditada(Persona $persona, array $datos): void
    {
        $antes = Auditor::foto($persona);

        $persona->update($datos);

        $cambios = Auditor::diferencia($persona, $antes);

        if ($cambios) {
            Auditor::registrar(Auditor::EDICION, $persona, 'Persona «'.$persona->nombre_completo.'»', $cambios);
        }
    }
}
