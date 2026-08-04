@props([
    'nombre',
    'etiqueta' => null,
    'valor' => null,
    'ayuda' => null,
    'requerido' => false,
    // Mismo criterio que en input.blade.php: hace falta solo cuando una
    // pagina repite el mismo campo en varios formularios.
    'id' => null,
    'filas' => 3,
])

@php
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

    <textarea
        id="{{ $idCampo }}"
        name="{{ $nombre }}"
        rows="{{ $filas }}"
        @if ($requerido) required @endif
        @if ($hayError) aria-invalid="true" aria-describedby="{{ $nombre }}-error" @endif
        {{ $attributes->class([
            'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris
             placeholder:text-hu-gris-medio focus:border-hu-azul focus:ring-0',
            'border-hu-gris-suave' => ! $hayError,
            'border-red-600' => $hayError,
        ]) }}
    >{{ $valorActual }}</textarea>

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
