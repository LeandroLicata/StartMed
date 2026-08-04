@props([
    'valor',
    'etiqueta',
    'icono' => null,
    'detalle' => null,
    'tono' => 'neutro',
])

@php
    $tonos = [
        'exito' => ['text-emerald-700', 'bg-emerald-50'],
        'error' => ['text-red-700', 'bg-red-50'],
        'aviso' => ['text-hu-dorado-oscuro', 'bg-hu-dorado-tenue'],
        'neutro' => ['text-hu-azul', 'bg-hu-azul-tenue'],
    ];

    [$textoTono, $fondoTono] = $tonos[$tono] ?? $tonos['neutro'];
@endphp

<div {{ $attributes->class('rounded-2xl border border-hu-gris-suave/70 bg-white px-5 py-4 shadow-sm') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-3xl font-black {{ $textoTono }}">{{ $valor }}</p>
            <p class="mt-0.5 truncate text-sm text-hu-gris">{{ $etiqueta }}</p>
        </div>

        @if ($icono)
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $fondoTono }} {{ $textoTono }}">
                <x-icono :nombre="$icono" class="text-xl" relleno />
            </span>
        @endif
    </div>

    @if ($detalle)
        <p class="mt-2 text-xs text-hu-gris-medio">{{ $detalle }}</p>
    @endif
</div>
