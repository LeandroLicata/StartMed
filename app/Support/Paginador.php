<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Pagina una coleccion que ya esta armada en memoria.
 *
 * Los listados de cirugias no se pueden paginar en SQL: el estado, la obra
 * social y "que le falta" salen de ResumenCirugia, que se resuelve en PHP
 * sobre las relaciones ya cargadas. Recortar en la consulta devolveria
 * paginas incompletas, porque el filtro corre despues.
 *
 * Lo que se recorta es lo que se dibuja, no lo que se cuenta: los indicadores
 * de los paneles se siguen calculando sobre la coleccion entera.
 *
 * El nombre de pagina se puede cambiar para tener dos listados paginados en
 * la misma pantalla sin que uno mueva al otro; como la query original viaja
 * en las opciones, cada enlace conserva la pagina del otro listado.
 */
class Paginador
{
    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return LengthAwarePaginator<int, T>
     */
    public static function deColeccion(
        Collection $items,
        Request $request,
        int $porPagina,
        string $nombrePagina = 'page',
    ): LengthAwarePaginator {
        $pagina = max(1, (int) $request->query($nombrePagina, 1));

        return new LengthAwarePaginator(
            $items->forPage($pagina, $porPagina)->values(),
            $items->count(),
            $porPagina,
            $pagina,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => $nombrePagina,
            ],
        );
    }
}
