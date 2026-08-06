@props(['paginador'])

@if ($paginador->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-hu-gris-suave/70 pt-4" aria-label="Paginación">
        <p class="text-xs text-hu-gris-medio">
            Mostrando {{ $paginador->firstItem() }}–{{ $paginador->lastItem() }} de {{ $paginador->total() }}
        </p>

        <div class="flex items-center gap-1">
            @if ($paginador->onFirstPage())
                <span class="flex size-9 items-center justify-center rounded-full text-hu-gris-suave" aria-hidden="true">
                    <x-icono nombre="arrow_back" class="text-lg" />
                </span>
            @else
                <a
                    href="{{ $paginador->previousPageUrl() }}"
                    class="flex size-9 items-center justify-center rounded-full text-hu-azul transition-colors hover:bg-hu-azul-tenue"
                    aria-label="Página anterior"
                >
                    <x-icono nombre="arrow_back" class="text-lg" />
                </a>
            @endif

            @foreach ($paginador->getUrlRange(max(1, $paginador->currentPage() - 2), min($paginador->lastPage(), $paginador->currentPage() + 2)) as $pagina => $url)
                <a
                    href="{{ $url }}"
                    @if ($pagina === $paginador->currentPage()) aria-current="page" @endif
                    class="flex size-9 items-center justify-center rounded-full text-sm font-semibold transition-colors
                        {{ $pagina === $paginador->currentPage() ? 'bg-hu-azul text-white' : 'text-hu-azul hover:bg-hu-azul-tenue' }}"
                >
                    {{ $pagina }}
                </a>
            @endforeach

            @if ($paginador->hasMorePages())
                <a
                    href="{{ $paginador->nextPageUrl() }}"
                    class="flex size-9 items-center justify-center rounded-full text-hu-azul transition-colors hover:bg-hu-azul-tenue"
                    aria-label="Página siguiente"
                >
                    <x-icono nombre="arrow_back" class="rotate-180 text-lg" />
                </a>
            @else
                <span class="flex size-9 items-center justify-center rounded-full text-hu-gris-suave" aria-hidden="true">
                    <x-icono nombre="arrow_back" class="rotate-180 text-lg" />
                </span>
            @endif
        </div>
    </nav>
@endif
