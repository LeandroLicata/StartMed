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

            {{-- Unidades de venta: cada una con su código y su precio, porque
                 el proveedor cobra distinto según la presentación. --}}
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">
                Unidades de venta
            </h3>

            <div class="space-y-2">
                @forelse ($vinculo->materialProveedorTipoMedidas as $medida)
                    @php($disponible = (bool) $medida->disponibleMaterialTipoMedida)
                    {{-- Todas las filas repiten los mismos campos: solo la que
                         se envió repuebla y muestra su error. --}}
                    @php($enviada = old('fila') === 'medida-'.$medida->idMaterialProveedorTipoMedida)

                    <div class="flex flex-wrap items-end gap-3 rounded-xl border border-hu-gris-suave/70 p-3
                                {{ $disponible ? '' : 'bg-hu-gris-tenue/50' }}">
                        <div class="w-32 pb-2.5">
                            <p class="text-sm font-semibold text-hu-azul">
                                {{ $medida->tipoMedida?->nombreTipoMedida }}
                            </p>
                            @unless ($disponible)
                                <p class="text-xs text-hu-gris-medio">Sin stock</p>
                            @endunless
                        </div>

                        <form
                            method="POST"
                            action="{{ route('admin.precios.medidas.update', [$material, $vinculo, $medida]) }}"
                            class="flex flex-wrap items-end gap-3"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="fila" value="medida-{{ $medida->idMaterialProveedorTipoMedida }}">

                            <div class="w-44">
                                <x-input
                                    nombre="codExternoMaterialProveedorTipoMedida"
                                    :id="'cod-'.$medida->idMaterialProveedorTipoMedida"
                                    etiqueta="Código del proveedor"
                                    :valor="$medida->codExternoMaterialProveedorTipoMedida"
                                    :enviado="$enviada"
                                />
                            </div>

                            <div class="w-40">
                                <x-input
                                    nombre="precioExternoMaterialProveedorTipoMedida"
                                    :id="'precio-'.$medida->idMaterialProveedorTipoMedida"
                                    etiqueta="Precio (USD)"
                                    tipo="number"
                                    step="0.01"
                                    min="0"
                                    :valor="$medida->precioExternoMaterialProveedorTipoMedida"
                                    :enviado="$enviada"
                                />
                            </div>

                            <x-boton tipo="submit" variante="contorno" forma="grupo" class="mb-0.5">Guardar</x-boton>

                            @if ($medida->fechaActualizacionPrecioMaterialProveedorTipoMedida)
                                <p class="mb-3 text-xs text-hu-gris-medio">
                                    Actualizado el
                                    {{ $medida->fechaActualizacionPrecioMaterialProveedorTipoMedida->format('d/m/Y') }}
                                </p>
                            @endif
                        </form>

                        <div class="mb-0.5 ml-auto flex items-center gap-1">
                            <form
                                method="POST"
                                action="{{ route('admin.precios.medidas.disponibilidad', [$material, $vinculo, $medida]) }}"
                            >
                                @csrf
                                @method('PATCH')
                                <x-boton
                                    tipo="submit"
                                    variante="fantasma"
                                    forma="grupo"
                                    class="px-2 {{ $disponible ? 'text-emerald-700' : 'text-hu-gris-medio' }}"
                                    :title="$disponible ? 'Marcar como no disponible' : 'Marcar como disponible'"
                                >
                                    <x-icono :nombre="$disponible ? 'check_circle' : 'cancel'" class="text-lg" />
                                    <span class="sr-only">Cambiar disponibilidad</span>
                                </x-boton>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.precios.medidas.destroy', [$material, $vinculo, $medida]) }}"
                                data-confirmar-titulo="Dar de baja «{{ $medida->tipoMedida?->nombreTipoMedida }}»"
                                data-confirmar="No se borra: queda en el historial con el precio al que se vendía."
                                data-confirmar-accion="Dar de baja"
                            >
                                @csrf
                                @method('DELETE')
                                <x-boton variante="fantasma" forma="grupo" class="px-2 text-red-700">
                                    <x-icono nombre="close" class="text-lg" />
                                    <span class="sr-only">Dar de baja la unidad</span>
                                </x-boton>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-hu-gris-medio">
                        Todavía no se cargó en qué unidades lo vende. Sin unidad no hay precio,
                        así que no se lo puede pedir.
                    </p>
                @endforelse
            </div>

            <form
                method="POST"
                action="{{ route('admin.precios.medidas.store', [$material, $vinculo]) }}"
                class="mt-4 flex flex-wrap items-end gap-3 border-t border-hu-gris-suave/70 pt-4"
            >
                @csrf
                {{-- Mismo motivo que en las filas: hay un alta por proveedor. --}}
                @php($alta = old('fila') === 'nueva-'.$vinculo->idMaterialProveedor)
                <input type="hidden" name="fila" value="nueva-{{ $vinculo->idMaterialProveedor }}">

                <div class="w-44">
                    <x-select
                        nombre="idTipoMedida"
                        :id="'medida-'.$vinculo->idMaterialProveedor"
                        etiqueta="Unidad"
                        :opciones="$medidas"
                        vacio="Elegí una unidad"
                        requerido
                        :enviado="$alta"
                    />
                </div>

                <div class="w-44">
                    <x-input
                        nombre="codExternoMaterialProveedorTipoMedida"
                        :id="'nuevo-cod-'.$vinculo->idMaterialProveedor"
                        etiqueta="Código del proveedor"
                        :enviado="$alta"
                    />
                </div>

                <div class="w-40">
                    <x-input
                        nombre="precioExternoMaterialProveedorTipoMedida"
                        :id="'nuevo-precio-'.$vinculo->idMaterialProveedor"
                        etiqueta="Precio (USD)"
                        tipo="number"
                        step="0.01"
                        min="0"
                        :enviado="$alta"
                    />
                </div>

                <x-boton tipo="submit" variante="fantasma" forma="grupo" icono="add" class="mb-0.5">
                    Agregar unidad
                </x-boton>
            </form>
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

                <x-boton tipo="submit" icono="add" class="mb-0.5">Agregar</x-boton>
            </form>
        @endif
    </x-tarjeta>

@endsection
