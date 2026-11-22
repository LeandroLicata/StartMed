<?php

namespace App\Http\Controllers\Concerns;

use App\Models\GrupoSanguineo;
use App\Models\Persona;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use App\Models\TipoDocumento;
use App\Support\Auditor;
use Illuminate\Support\Collection;

/**
 * Datos de catalogos, coberturas y escritura auditada compartidos entre el
 * padron de pacientes (Gestor de quirofano) y la seccion Personas (Admin).
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
     * @return array{
     *     coberturasVigentes: Collection<int, PlanObraSocial>,
     *     coberturasHistoricas: Collection<int, PlanObraSocial>,
     *     planesDisponibles: Collection<int, string>,
     * }
     */
    private function datosCoberturasPersona(Persona $persona, bool $cargarPlanes): array
    {
        $coberturas = $persona->planObraSociales()
            ->with('plan.obrasocial')
            ->orderByDesc('esPredeterminadaPlanObraSocial')
            ->orderByDesc('idPlanObraSocial')
            ->get();

        return [
            'coberturasVigentes' => $coberturas->filter(fn (PlanObraSocial $c) => $c->estaVigente()),
            'coberturasHistoricas' => $coberturas->reject(fn (PlanObraSocial $c) => $c->estaVigente()),
            'planesDisponibles' => $cargarPlanes ? Plan::disponiblesParaPaciente() : collect(),
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
