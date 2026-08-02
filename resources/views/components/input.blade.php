@props([
    'nombre',
    'etiqueta' => null,
    'tipo' => 'text',
    'valor' => null,
    'ayuda' => null,
    'placeholder' => null,
    'requerido' => false,
])

@php
    // old() conserva lo que el usuario habia tipeado cuando la validacion falla.
    $valorActual = old($nombre, $valor);
    $hayError = $errors->has($nombre);
@endphp

<div class="space-y-1.5">
    @if ($etiqueta)
        <label for="{{ $nombre }}" class="block text-sm font-semibold text-hu-azul">
            {{ $etiqueta }}
            @if ($requerido)
                <span class="text-red-700" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $nombre }}"
        name="{{ $nombre }}"
        type="{{ $tipo }}"
        value="{{ $valorActual }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($requerido) required @endif
        @if ($hayError) aria-invalid="true" aria-describedby="{{ $nombre }}-error" @endif
        {{ $attributes->class([
            'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris
             placeholder:text-hu-gris-medio focus:border-hu-azul focus:ring-0',
            'border-hu-gris-suave' => ! $hayError,
            'border-red-600' => $hayError,
        ]) }}
    >

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
