@extends('layouts.app')

@section('titulo', $material->nombreMaterial)
@section('subtitulo', 'Administración · Proveedores y precios')

@section('contenido')

    <div class="mb-4">
        <x-boton :href="route('admin.precios.index')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a materiales
        </x-boton>
    </div>

    @forelse ($vinculos as $vinculo)
        <x-tarjeta :titulo="$vinculo->proveedor?->nombreProveedor ?? 'Proveedor dado de baja'" icono="inventory_2" class="mb-4">
            <x-slot:acciones>
                <form
                    method="POST"
                    action="{{ route('admin.precios.proveedores.destroy', [$material, $vinculo]) }}"
                    data-confirmar-titulo="Quitar a {{ $vinculo->proveedor?->nombreProveedor }}"
                    data-confirmar="Esta tabla no tiene baja lógica, así que se borra. Los pedidos ya hechos guardan su propio precio y no se tocan."
                    data-confirmar-accion="Quitar"
                >
                    @csrf
                    @method('DELETE')
                    <x-boton variante="fantasma" forma="grupo" class="px-2 text-red-700">
                        <x-icono nombre="delete" class="text-lg" />
                        <span class="sr-only">Quitar proveedor</span>
                    </x-boton>
                </form>
            </x-slot:acciones>

            {{-- Precio y código --}}
            <form
                method="POST"
                action="{{ route('admin.precios.proveedores.update', [$material, $vinculo]) }}"
                class="flex flex-wrap items-end gap-3"
            >
                @csrf
                @method('PUT')

                <div class="w-44">
                    <x-input
                        nombre="codExternoMaterialProveedor"
                        :id="'cod-'.$vinculo->idMaterialProveedor"
                        etiqueta="Código del proveedor"
                        :valor="$vinculo->codExternoMaterialProveedor"
                    />
                </div>

                <div class="w-40">
                    <x-input
                        nombre="precioExternoMaterialProveedor"
                        :id="'precio-'.$vinculo->idMaterialProveedor"
                        etiqueta="Precio (USD)"
                        tipo="number"
                        step="0.01"
                        min="0"
                        :valor="$vinculo->precioExternoMaterialProveedor"
                    />
                </div>

                <x-boton tipo="submit" variante="contorno" forma="grupo" class="mb-0.5">Guardar</x-boton>

                @if ($vinculo->fechaActualizacionPrecio)
                    <p class="mb-3 text-xs text-hu-gris-medio">
                        Precio actualizado el {{ $vinculo->fechaActualizacionPrecio->format('d/m/Y') }}
                    </p>
                @endif
            </form>

            {{-- Unidades en que lo vende --}}
            <div class="mt-5 border-t border-hu-gris-suave/70 pt-4">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">
                    Unidades de venta
                </h3>

                <div class="flex flex-wrap items-center gap-2">
                    @forelse ($vinculo->materialProveedorTipoMedidas as $medida)
                        @php($disponible = (bool) $medida->disponibleMaterialTipoMedida)

                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold
                                     {{ $disponible
                                         ? 'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-600/20'
                                         : 'bg-hu-gris-tenue text-hu-gris ring-1 ring-inset ring-hu-gris/20' }}">
                            {{ $medida->tipoMedida?->nombreTipoMedida }}

                            <form
                                method="POST"
                                action="{{ route('admin.precios.medidas.update', [$material, $vinculo, $medida]) }}"
                                class="inline"
                            >
                                @csrf
                                @method('PUT')
                                <button
                                    type="submit"
                                    class="opacity-60 hover:opacity-100"
                                    title="{{ $disponible ? 'Marcar como no disponible' : 'Marcar como disponible' }}"
                                >
                                    <x-icono :nombre="$disponible ? 'check_circle' : 'cancel'" class="text-sm" />
                                    <span class="sr-only">Cambiar disponibilidad</span>
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.precios.medidas.destroy', [$material, $vinculo, $medida]) }}"
                                class="inline"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="opacity-60 hover:text-red-700 hover:opacity-100">
                                    <x-icono nombre="close" class="text-sm" />
                                    <span class="sr-only">Dar de baja la unidad</span>
                                </button>
                            </form>
                        </span>
                    @empty
                        <p class="text-xs text-hu-gris-medio">
                            Todavía no se cargó en qué unidades lo vende.
                        </p>
                    @endforelse
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.precios.medidas.store', [$material, $vinculo]) }}"
                    class="mt-3 flex flex-wrap items-end gap-2"
                >
                    @csrf

                    <div class="w-44">
                        <x-select
                            nombre="idTipoMedida"
                            :id="'medida-'.$vinculo->idMaterialProveedor"
                            :opciones="$medidas"
                            vacio="Elegí una unidad"
                        />
                    </div>

                    <x-boton tipo="submit" variante="fantasma" forma="grupo" icono="add" class="mb-0.5">
                        Agregar
                    </x-boton>
                </form>
            </div>
        </x-tarjeta>
    @empty
        <x-tarjeta>
            <p class="py-8 text-center text-hu-gris-medio">
                Ningún proveedor tiene cargado este material todavía.
            </p>
        </x-tarjeta>
    @endforelse

    <x-tarjeta titulo="Agregar un proveedor" icono="add" class="mt-5">
        @if ($proveedores === [])
            <p class="text-sm text-hu-gris-medio">
                Ya están cargados todos los proveedores activos. Se dan de alta desde
                <a href="{{ route('admin.catalogos.index', 'proveedor') }}" class="text-hu-azul underline">
                    el catálogo de proveedores</a>.
            </p>
        @else
            <form
                method="POST"
                action="{{ route('admin.precios.proveedores.store', $material) }}"
                class="flex flex-wrap items-end gap-3"
            >
                @csrf

                <div class="min-w-56 flex-1">
                    <x-select
                        nombre="idProveedor"
                        etiqueta="Proveedor"
                        :opciones="$proveedores"
                        vacio="Elegí un proveedor"
                        requerido
                    />
                </div>

                <div class="w-44">
                    <x-input nombre="codExternoMaterialProveedor" etiqueta="Código del proveedor" />
                </div>

                <div class="w-40">
                    <x-input
                        nombre="precioExternoMaterialProveedor"
                        etiqueta="Precio (USD)"
                        tipo="number"
                        step="0.01"
                        min="0"
                    />
                </div>

                <x-boton tipo="submit" icono="add" class="mb-0.5">Agregar</x-boton>
            </form>
        @endif
    </x-tarjeta>

@endsection
