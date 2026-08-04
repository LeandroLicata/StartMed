@extends('layouts.app')

@section('titulo', $config['plural'])
@section('subtitulo', 'Administración · '.$config['grupo'])

@section('contenido')

    @php
        use App\Support\Catalogos;

        // Las descripciones largas no entran en la tabla; se ven al editar.
        $columnas = collect($config['campos'])->reject(fn ($campo) => ($campo['tipo'] ?? 'texto') === 'texto-largo');

        // Resueltas una sola vez: dentro del @foreach seria una consulta por fila.
        $opciones = $columnas
            ->filter(fn ($campo) => ($campo['tipo'] ?? 'texto') === 'select')
            ->map(fn ($campo) => Catalogos::opciones($campo));

        $columnaTitulo = Catalogos::columnaTitulo($config);
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-boton :href="route('admin.inicio')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a administración
        </x-boton>

        {{-- Acá arriba van solo acciones; el filtro vive dentro de la tarjeta. --}}
        <x-boton :href="route('admin.catalogos.create', $config['slug'])" icono="add">
            Nuevo
        </x-boton>
    </div>

    <x-tarjeta :titulo="$config['plural']" :icono="Catalogos::GRUPOS[$config['grupo']]">
        <x-slot:acciones>
            <x-estado tono="info">{{ $registros->total() }}</x-estado>
        </x-slot:acciones>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-44"><x-filtro-baja :valor="$estado" /></div>

            <x-boton tipo="submit" variante="contorno" forma="grupo">Filtrar</x-boton>
        </form>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-160 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        @foreach ($columnas as $campo)
                            <th class="px-3 pb-2 font-semibold first:px-5">{{ $campo['etiqueta'] }}</th>
                        @endforeach
                        <th class="px-3 pb-2 text-right font-semibold" title="Cuántos registros del sistema referencian cada fila">
                            En uso
                        </th>
                        <th class="px-3 pb-2 font-semibold">Estado</th>
                        <th class="px-5 pb-2 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($registros as $registro)
                        @php
                            $deBaja = $registro->{$config['baja']} !== null;
                            // Filas de las que depende el código: se muestran, no se tocan.
                            $protegido = Catalogos::estaProtegido($config, $registro);
                            $usos = Catalogos::usos($config, $registro);

                            // Se arma acá y no dentro del atributo: un @if
                            // pegado a texto rompe la compilación de Blade.
                            $avisoDeUso = $usos > 0
                                ? 'Lo referencian '.$usos.' '.str('registro')->plural($usos).', que no se van a tocar. '
                                : '';
                        @endphp

                        <tr class="align-middle hover:bg-hu-azul-tenue/40">
                            @foreach ($columnas as $columna => $campo)
                                <td class="px-3 py-3 first:px-5 first:font-semibold first:text-hu-azul">
                                    @if (($campo['tipo'] ?? 'texto') === 'booleano')
                                        {{ $registro->$columna ? 'Sí' : 'No' }}
                                    @elseif (($campo['tipo'] ?? 'texto') === 'select')
                                        {{ $opciones[$columna][$registro->$columna] ?? '—' }}
                                    @else
                                        {{ $registro->$columna ?? '—' }}
                                    @endif
                                </td>
                            @endforeach

                            <td class="px-3 py-3 text-right tabular-nums {{ $usos === 0 ? 'text-hu-gris-medio' : 'font-semibold text-hu-azul' }}">
                                {{ $usos === 0 ? '—' : $usos }}
                            </td>

                            <td class="px-3 py-3">
                                @if ($protegido)
                                    <x-estado tono="info" icono="lock" title="{{ $config['motivoProteccion'] }}">
                                        Del sistema
                                    </x-estado>
                                @else
                                    <x-estado :tono="$deBaja ? 'neutro' : 'exito'">
                                        {{ $deBaja ? 'Dado de baja' : 'Activo' }}
                                    </x-estado>
                                @endif
                            </td>

                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <x-boton
                                        :href="route('admin.catalogos.edit', [$config['slug'], $registro->getKey()])"
                                        variante="fantasma"
                                        icono="edit"
                                        forma="grupo"
                                        class="px-3"
                                    >
                                        <span class="sr-only sm:not-sr-only">Editar</span>
                                    </x-boton>

                                    @if ($protegido)
                                        {{-- Sin botón de baja: rebotaría igual, y ofrecerlo confunde. --}}
                                    @elseif ($deBaja)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.catalogos.restore', [$config['slug'], $registro->getKey()]) }}"
                                        >
                                            @csrf
                                            <x-boton variante="contorno" icono="restore" forma="grupo" class="px-3">
                                                <span class="sr-only sm:not-sr-only">Reactivar</span>
                                            </x-boton>
                                        </form>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.catalogos.destroy', [$config['slug'], $registro->getKey()]) }}"
                                            data-confirmar-titulo="Dar de baja «{{ $registro->$columnaTitulo }}»"
                                            data-confirmar="{{ $avisoDeUso }}Deja de ofrecerse de acá en adelante, pero no se borra: se puede reactivar cuando haga falta."
                                            data-confirmar-accion="Dar de baja"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-boton variante="peligro" icono="delete" forma="grupo" class="px-3">
                                                <span class="sr-only sm:not-sr-only">Dar de baja</span>
                                            </x-boton>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $columnas->count() + 3 }}" class="px-5 py-10 text-center text-hu-gris-medio">
                                @if ($estado === \App\Support\FiltroBaja::BAJAS)
                                    No hay {{ mb_strtolower($config['plural']) }} dados de baja.
                                @else
                                    Todavía no hay {{ mb_strtolower($config['plural']) }} cargados.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($registros->hasPages())
            <div class="pt-4">{{ $registros->links() }}</div>
        @endif
    </x-tarjeta>

@endsection
