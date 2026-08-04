@extends('layouts.app')

@section('titulo', 'Proveedores y precios')
@section('subtitulo', 'Administración · Quién vende cada material y a cuánto')

@section('contenido')

    <div class="mb-4">
        <x-boton :href="route('admin.inicio')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a administración
        </x-boton>
    </div>

    <x-tarjeta titulo="Materiales" icono="inventory_2">
        <x-slot:acciones>
            <x-estado tono="info">{{ $materiales->count() }}</x-estado>
        </x-slot:acciones>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-160 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Material</th>
                        <th class="px-3 pb-2 font-semibold">Código</th>
                        <th class="px-3 pb-2 text-right font-semibold">Proveedores</th>
                        <th class="px-3 pb-2 text-right font-semibold">Precio</th>
                        <th class="px-5 pb-2 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($materiales as $material)
                        @php
                            $cuantos = $material->material_proveedores_count;
                            $min = $material->precioMinimo;
                            $max = $material->precioMaximo;
                        @endphp

                        <tr class="align-middle hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3 font-semibold text-hu-azul">{{ $material->nombreMaterial }}</td>

                            <td class="px-3 py-3 text-hu-gris-medio">{{ $material->codMaterial ?? '—' }}</td>

                            <td class="px-3 py-3 text-right">
                                @if ($cuantos === 0)
                                    {{-- Sin proveedor no hay a quién pedírselo ni a qué precio. --}}
                                    <x-estado tono="error" icono="warning">Sin proveedor</x-estado>
                                @else
                                    <span class="tabular-nums">{{ $cuantos }}</span>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-right tabular-nums whitespace-nowrap">
                                @if ($min === null && $cuantos > 0)
                                    {{-- Hay proveedor pero ninguna unidad con precio: no se puede pedir. --}}
                                    <x-estado tono="aviso" icono="warning">Sin precio</x-estado>
                                @elseif ($min === null)
                                    <span class="text-hu-gris-medio">—</span>
                                @elseif ($min == $max)
                                    USD {{ number_format((float) $min, 2, ',', '.') }}
                                @else
                                    <span class="text-hu-gris-medio">desde</span>
                                    USD {{ number_format((float) $min, 2, ',', '.') }}
                                @endif
                            </td>

                            <td class="px-5 py-3 text-right">
                                <x-boton
                                    :href="route('admin.precios.show', $material)"
                                    :variante="$cuantos === 0 ? 'primario' : 'contorno'"
                                    forma="grupo"
                                    icono="inventory_2"
                                >
                                    {{ $cuantos === 0 ? 'Cargar proveedor' : 'Ver proveedores' }}
                                </x-boton>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-hu-gris-medio">
                                No hay materiales activos. Se cargan desde
                                <a href="{{ route('admin.catalogos.index', 'material') }}" class="text-hu-azul underline">
                                    el catálogo de materiales</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tarjeta>

@endsection
