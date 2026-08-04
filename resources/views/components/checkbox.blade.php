@props([
    'nombre',
    'etiqueta' => null,
    'valor' => false,
    'ayuda' => null,
    // Mismo criterio que en input.blade.php: hace falta solo cuando una
    // pagina repite el mismo campo en varios formularios.
    'id' => null,
])

@php
    $marcado = (bool) old($nombre, $valor);
    $hayError = $errors->has($nombre);
    $idCampo = $id ?? $nombre;
@endphp

<div class="space-y-1.5">
    <label for="{{ $idCampo }}" class="flex items-start gap-2.5 text-sm text-hu-gris">
        {{--
            Un checkbox sin marcar no viaja en el POST. El hidden previo hace
            que igual llegue como 0 y la validacion booleana no se rompa.
        --}}
        <input type="hidden" name="{{ $nombre }}" value="0">

        <input
            id="{{ $idCampo }}"
            name="{{ $nombre }}"
            type="checkbox"
            value="1"
            @checked($marcado)
            @if ($hayError) aria-invalid="true" aria-describedby="{{ $nombre }}-error" @endif
            {{ $attributes->class([
                'mt-0.5 size-4 shrink-0 rounded border bg-white text-hu-azul focus:ring-hu-azul',
                'border-hu-gris-suave' => ! $hayError,
                'border-red-600' => $hayError,
            ]) }}
        >

        <span class="font-semibold text-hu-azul">{{ $etiqueta }}</span>
    </label>

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
