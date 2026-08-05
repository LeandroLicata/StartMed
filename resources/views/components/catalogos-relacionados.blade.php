@props(['slugs'])

@php
    use App\Support\Catalogos;

    /*
     * Atajo a los catálogos que alimentan la pantalla, para el que está
     * trabajando acá y descubre que le falta una fila allá. Es una segunda
     * puerta, no una mudanza: el catálogo sigue viviendo en /admin, que es lo
     * único que garantiza que las 27 tablas sean alcanzables.
     *
     * Las etiquetas salen del mapa, así que no se despegan del catálogo. Y
     * buscar() aborta si el slug no existe: un atajo mal escrito revienta acá
     * y no como un enlace roto en producción.
     */
    $catalogos = collect($slugs)->map(fn (string $slug) => Catalogos::buscar($slug));
@endphp

<p {{ $attributes->class('mb-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-hu-gris-medio') }}>
    <x-icono nombre="folder_open" class="text-base text-hu-gris-suave" />

    <span>Se cargan desde</span>

    @foreach ($catalogos as $catalogo)
        <a
            href="{{ route('admin.catalogos.index', $catalogo['slug']) }}"
            class="font-semibold text-hu-azul underline decoration-hu-azul/30 underline-offset-2
                   transition-colors hover:decoration-hu-dorado hover:text-hu-azul-oscuro"
        >{{ $catalogo['plural'] }}</a>@if (! $loop->last)<span aria-hidden="true">·</span>@endif
    @endforeach
</p>
