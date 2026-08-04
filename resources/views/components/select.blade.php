@props([
    'nombre',
    'etiqueta' => null,
    'opciones' => [],
    'valor' => null,
    'ayuda' => null,
    'requerido' => false,
    // Texto de la opcion vacia. Pasar false la saca del todo, para los casos
    // en que la propia lista ya trae un valor por defecto (ver x-filtro-baja).
    'vacio' => 'Elegí una opción',
    // Alias de `vacio`: las pantallas de cirugias lo llaman placeholder. Se
    // mantiene para no tener que tocarlas.
    'placeholder' => null,
    // Mismo criterio que en input.blade.php: hace falta solo cuando una
    // pagina repite el mismo campo en varios formularios.
    'id' => null,
    // Idem input.blade.php: solo el formulario que se envio repuebla con old()
    // y muestra el error.
    'enviado' => true,
])

@php
    // Mismo criterio que en input.blade.php: old() gana sobre el valor guardado.
    $valorActual = $enviado ? old($nombre, $valor) : $valor;
    $hayError = $enviado && $errors->has($nombre);
    $idCampo = $id ?? $nombre;
    $textoVacio = $placeholder ?? $vacio;
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
        @if ($textoVacio)
            {{-- Comparado contra null y '' y no con un truthy: un valor "0" es
                 una opcion legitima y no deberia caer en la vacia. --}}
            <option value="" @selected($valorActual === null || $valorActual === '')>{{ $textoVacio }}</option>
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

    {{-- Mismo motivo que en input.blade.php: @error mira el bag global. --}}
    @if ($hayError)
        <p id="{{ $nombre }}-error" class="flex items-center gap-1 text-xs font-semibold text-red-700">
            <x-icono nombre="error" class="text-sm" relleno />
            {{ $errors->first($nombre) }}
        </p>
    @endif
</div>
