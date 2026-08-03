<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;
use App\Support\Auditor;
use App\Support\Catalogos;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historial de escrituras de la seccion de administracion, de lo mas reciente
 * a lo mas viejo. Es solo lectura: la auditoria no se edita ni se borra.
 */
class AuditoriaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $autor = $request->integer('autor') ?: null;
        $accion = $request->string('accion')->toString() ?: null;
        $tabla = $request->string('tabla')->toString() ?: null;

        $nombresDeTabla = $this->nombresDeTabla();

        return view('admin.auditoria', [
            'registros' => Auditoria::query()
                ->with('usuario.personal.persona')
                ->when($autor, fn ($q) => $q->where('idUsuario', $autor))
                ->when($accion, fn ($q) => $q->where('accionAuditoria', $accion))
                ->when($tabla, fn ($q) => $q->where('tablaAuditoria', $tabla))
                ->orderByDesc('fechaHoraAuditoria')
                ->orderByDesc('idAuditoria')
                ->paginate(50)
                ->withQueryString(),
            'filtros' => ['autor' => $autor, 'accion' => $accion, 'tabla' => $tabla],
            // Por la persona, no por el alias: en un historial interesa
            // reconocer quién lo hizo, y el alias solo lo desambigua.
            'autores' => Usuario::query()
                ->with('personal.persona')
                ->whereIn('idUsuario', Auditoria::query()->distinct()->pluck('idUsuario')->filter())
                ->get()
                ->sortBy(fn (Usuario $u) => $this->comoSeLlama($u))
                ->mapWithKeys(fn (Usuario $u) => [$u->idUsuario => $this->comoSeLlama($u)])
                ->all(),
            'acciones' => array_combine(
                $acciones = [Auditor::ALTA, Auditor::EDICION, Auditor::BAJA, Auditor::REACTIVACION, Auditor::CLAVE],
                array_map(ucfirst(...), $acciones),
            ),
            // Solo las tablas que ya tienen movimientos: un desplegable con las
            // del esquema no ayudaria a nadie. Y con el nombre que usa la
            // gente, no el de la tabla de la base.
            'tablas' => Auditoria::query()
                ->distinct()
                ->pluck('tablaAuditoria')
                ->mapWithKeys(fn (string $t) => [$t => $nombresDeTabla[$t] ?? $t])
                ->sort()
                ->all(),
        ]);
    }

    /**
     * "González, Romina (gonzalez)". El alias solo, que es lo que guarda
     * Usuario, no alcanza para identificar a nadie.
     */
    private function comoSeLlama(Usuario $usuario): string
    {
        $persona = $usuario->personal?->persona;

        return $persona
            ? $persona->nombre_completo.' ('.$usuario->nombreUsuario.')'
            : $usuario->nombreUsuario;
    }

    /**
     * Nombre de tabla -> como se llama en la interfaz. Los catalogos ya lo
     * declaran en su mapa; Usuario no es un catalogo y va aparte.
     *
     * @return array<string, string>
     */
    private function nombresDeTabla(): array
    {
        $nombres = ['Usuario' => 'Usuarios'];

        foreach (Catalogos::todos() as $config) {
            $nombres[(new $config['modelo'])->getTable()] = $config['plural'];
        }

        return $nombres;
    }
}
