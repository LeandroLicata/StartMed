<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cirugia;
use App\Models\EstadoCirugia;
use App\Models\ObraSocial;
use App\Models\Quirofano;
use App\Support\ResumenCirugia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filtro de cirugias compartido entre el tablero y la agenda: Buscar, Estado,
 * Quirofano y Obra social. Lo que se puede resolver en SQL se filtra ahi
 * (quirofano, texto); estado y obra social dependen de logica que ya vive en
 * ResumenCirugia, asi que se filtran despues sobre la coleccion ya armada.
 * El llamador arma el $query con su propio rango de fechas antes de pasarlo.
 */
trait FiltraCirugias
{
    private function aplicarFiltros(Builder $query, Request $request): Collection
    {
        if ($request->filled('idQuirofano')) {
            $query->whereHas(
                'cirugiaQuirofanos',
                fn ($q) => $q->whereNull('fechaHoraDesasignacion')->where('idQuirofano', $request->query('idQuirofano')),
            );
        }

        if ($request->filled('q')) {
            $texto = $request->query('q');

            $query->whereHas('paciente', function ($q) use ($texto) {
                $q->where('apellidos', 'like', "%{$texto}%")
                    ->orWhere('nombres', 'like', "%{$texto}%")
                    ->orWhere('documento', 'like', "%{$texto}%");
            });
        }

        $resultado = $query->orderBy('fechaHoraCirugia')->get()
            ->map(fn (Cirugia $cirugia) => new ResumenCirugia($cirugia));

        if ($request->filled('estado')) {
            $resultado = $resultado->filter(fn (ResumenCirugia $r) => $r->estado() === $request->query('estado'));
        }

        if ($request->filled('idObraSocial')) {
            $resultado = $resultado->filter(
                fn (ResumenCirugia $r) => (string) $r->plan?->obrasocial?->idObraSocial === $request->query('idObraSocial'),
            );
        }

        return $resultado->values();
    }

    /** @return array{estadosCirugia: Collection, quirofanosCatalogo: Collection, obrasSocialesCatalogo: Collection} */
    private function catalogosFiltro(): array
    {
        return [
            'estadosCirugia' => EstadoCirugia::whereNull('fechaBajaEstadoCirugia')->orderBy('nombreEstadoCirugia')->get(),
            'quirofanosCatalogo' => Quirofano::whereNull('fechaBajaQuirofano')->orderBy('nroQuirofano')->get(),
            'obrasSocialesCatalogo' => ObraSocial::whereNull('fechaBajaObraSocial')->orderBy('nombreObraSocial')->get(),
        ];
    }
}
