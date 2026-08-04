@props([
    'titulo' => null,
    'icono' => null,
])

<section {{ $attributes->class('rounded-2xl border border-hu-gris-suave/70 bg-white shadow-sm') }}>
    @if ($titulo || isset($acciones))
        <header class="flex items-center justify-between gap-4 border-b border-hu-gris-suave/70 px-5 py-4">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-hu-azul">
                @if ($icono)
                    <x-icono :nombre="$icono" class="text-xl text-hu-dorado" relleno />
                @endif
                {{ $titulo }}
            </h2>

            @isset($acciones)
                <div class="flex items-center gap-2">{{ $acciones }}</div>
            @endisset
        </header>
    @endif

    <div class="px-5 py-4">
        {{ $slot }}
    </div>
</section>
