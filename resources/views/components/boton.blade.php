@props([
    'variante' => 'primario',
    'href' => null,
    'tipo' => 'submit',
    'icono' => null,
    /*
     * El manual distingue dos redondeos:
     *   'confirmacion' (por defecto) → el maximo posible, para acciones
     *   'grupo'                      → ~12px, para conjuntos de botones
     */
    'forma' => 'confirmacion',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold
             transition-colors disabled:cursor-not-allowed disabled:opacity-50';

    $formas = [
        'confirmacion' => 'rounded-full',
        'grupo' => 'rounded-xl',
    ];

    $variantes = [
        'primario' => 'bg-hu-azul text-white hover:bg-hu-azul-claro',
        'secundario' => 'bg-hu-dorado text-white hover:bg-hu-dorado-oscuro',
        'contorno' => 'border border-hu-azul text-hu-azul hover:bg-hu-azul-tenue',
        'fantasma' => 'text-hu-gris hover:bg-hu-gris-suave/50',
        'peligro' => 'bg-red-700 text-white hover:bg-red-800',
    ];

    $clases = trim("$base {$formas[$forma]} {$variantes[$variante]}");
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($clases) }}>
        @if ($icono)
            <x-icono :nombre="$icono" class="text-lg" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $tipo }}" {{ $attributes->class($clases) }}>
        @if ($icono)
            <x-icono :nombre="$icono" class="text-lg" />
        @endif
        {{ $slot }}
    </button>
@endif
