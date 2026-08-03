@props([
    'nombre',
    'etiqueta' => null,
    'opciones' => [],
    'valor' => null,
    'ayuda' => null,
    'requerido' => false,
    'vacio' => 'Elegí una opción',
    // Mismo criterio que en input.blade.php: hace falta solo cuando una
    // pagina repite el mismo campo en varios formularios.
    'id' => null,
])

@php
    // Mismo criterio que <x-input>: old() gana sobre el valor guardado.
    $valorActual = old($nombre, $valor);
    $hayError = $errors->has($nombre);
    $idCampo = $id ?? $nombre;
@endphp

<div class="space-y-1.5">
    @if ($etiqueta)
        <label for="{{ $idCampo }}" class="block text-sm font-semibold text-hu-azul">
            {{ $etiqueta }}
            @if ($requerido)
                <span class="text-red-700" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $idCampo }}"
        name="{{ $nombre }}"
        @if ($requerido) required @endif
        @if ($hayError) aria-invalid="true" aria-describedby="{{ $nombre }}-error" @endif
        {{ $attributes->class([
            'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris
             focus:border-hu-azul focus:ring-0',
            'border-hu-gris-suave' => ! $hayError,
            'border-red-600' => $hayError,
        ]) }}
    >
        @if ($vacio)
            <option value="">{{ $vacio }}</option>
        @endif

        @foreach ($opciones as $clave => $texto)
            <option value="{{ $clave }}" @selected((string) $clave === (string) $valorActual)>
                {{ $texto }}
            </option>
        @endforeach
    </select>

    @if ($ayuda && ! $hayError)
        <p class="text-xs text-hu-gris-medio">{{ $ayuda }}</p>
    @endif

    @error($nombre)
        <p id="{{ $nombre }}-error" class="flex items-center gap-1 text-xs font-semibold text-red-700">
            <x-icono nombre="error" class="text-sm" relleno />
            {{ $message }}
        </p>
    @enderror
</div>
