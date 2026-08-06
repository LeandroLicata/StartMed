<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Catalogos;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Indice de los catalogos: la unica puerta a las tablas maestras. Los demas
 * modulos de administracion son secciones propias del menu, no pasan por aca.
 */
class AdminController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.inicio', ['grupos' => $this->gruposConTotales()]);
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
