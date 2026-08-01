@props([
    'tipo' => 'info',
    'titulo' => null,
])

@php
    $estilos = [
        'exito' => ['bg-emerald-50 text-emerald-900 border-emerald-200', 'check_circle'],
        'error' => ['bg-red-50 text-red-900 border-red-200', 'error'],
        'aviso' => ['bg-hu-dorado-tenue text-hu-gris border-hu-dorado/40', 'warning'],
        'info' => ['bg-hu-azul-tenue text-hu-azul border-hu-azul-suave', 'info'],
    ];

    [$clases, $icono] = $estilos[$tipo] ?? $estilos['info'];
@endphp

<div role="alert" {{ $attributes->class("flex items-start gap-3 rounded-xl border px-4 py-3 text-sm $clases") }}>
    <x-icono :nombre="$icono" class="mt-px text-lg" relleno />

    <div class="space-y-0.5">
        @if ($titulo)
            <p class="font-semibold">{{ $titulo }}</p>
        @endif
        <div>{{ $slot }}</div>
    </div>
</div>
