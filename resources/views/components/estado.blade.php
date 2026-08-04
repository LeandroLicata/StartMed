@props([
    'tono' => 'neutro',
    'icono' => null,
])

@php
    $tonos = [
        'exito' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        'error' => 'bg-red-50 text-red-800 ring-red-600/20',
        'aviso' => 'bg-hu-dorado-tenue text-hu-dorado-oscuro ring-hu-dorado/30',
        'info' => 'bg-hu-azul-tenue text-hu-azul ring-hu-azul/20',
        'neutro' => 'bg-hu-gris-tenue text-hu-gris ring-hu-gris/20',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
    $tonos[$tono] ?? $tonos['neutro'],
]) }}>
    @if ($icono)
        <x-icono :nombre="$icono" class="text-sm" relleno />
    @endif
    {{ $slot }}
</span>
