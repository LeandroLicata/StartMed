@props([
    'nombre',
    'etiqueta' => null,
    'tipo' => 'text',
    'valor' => null,
    'ayuda' => null,
    'placeholder' => null,
    'requerido' => false,
    // Solo hace falta cuando una pagina repite el mismo campo en varios
    // formularios: sin esto los id se duplicarian y el label apuntaria al
    // primero. El name siempre sale de $nombre.
    'id' => null,
    // Para esa misma pagina: old() y los errores son globales, no del
    // formulario que fallo, asi que sin esto un precio rechazado en una fila
    // reaparece marcado en rojo en todas las demas. El formulario dice si fue
    // el que se envio; los que no, se dibujan con su valor guardado.
    'enviado' => true,
])

@php
    // old() conserva lo que el usuario habia tipeado cuando la validacion falla.
    $valorActual = $enviado ? old($nombre, $valor) : $valor;
    $hayError = $enviado && $errors->has($nombre);
    $idCampo = $id ?? $nombre;

    /*
     * Un input file no lleva value: el navegador lo ignora y el atributo es
     * invalido. Tampoco hay old() que recuperar --el archivo no vuelve en el
     * request fallido--, asi que el usuario tiene que elegirlo de nuevo.
     */
    $llevaValor = $tipo !== 'file';
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

    <input
        id="{{ $idCampo }}"
        name="{{ $nombre }}"
        type="{{ $tipo }}"
        @if ($llevaValor) value="{{ $valorActual }}" @endif
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

    {{-- No es @error: ese directive mira el bag global y se dibujaria tambien
         en los formularios que no se enviaron. --}}
    @if ($hayError)
        <p id="{{ $nombre }}-error" class="flex items-center gap-1 text-xs font-semibold text-red-700">
            <x-icono nombre="error" class="text-sm" relleno />
            {{ $errors->first($nombre) }}
        </p>
    @endif
</div>
