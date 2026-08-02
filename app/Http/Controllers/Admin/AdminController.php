<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\Catalogos;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Portada de la seccion de administracion: desde donde se entra a cada tabla
 * maestra y a los usuarios.
 */
class AdminController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.inicio', [
            'grupos' => $this->gruposConTotales(),
            'indicadores' => [
                'usuarios' => Usuario::query()->whereNull('fechaBajaUsuario')->count(),
                'dadosDeBaja' => Usuario::query()->whereNotNull('fechaBajaUsuario')->count(),
                'sinRol' => Usuario::query()
                    ->whereNull('fechaBajaUsuario')
                    ->whereDoesntHave('personal.rolesVigentes')
                    ->count(),
            ],
        ]);
    }

    /**
     * Los catalogos agrupados, cada uno con cuantos registros activos tiene.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function gruposConTotales(): Collection
    {
        return Catalogos::porGrupo()->map(fn (array $grupo) => [
            ...$grupo,
            'catalogos' => $grupo['catalogos']->map(fn (array $config) => [
                ...$config,
                'activos' => $config['modelo']::query()->whereNull($config['baja'])->count(),
            ]),
        ]);
    }
}
