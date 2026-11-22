@php
    /*
     * Las secciones viven en App\Support\Navegacion (las lee tambien el
     * asistente); ahi estan las reglas de quien ve que. Se filtran con
     * tieneRolPropio: el comodin del administrador es para entrar, no para
     * llenarle el menu con el trabajo de los demas.
     */
    $secciones = \App\Support\Navegacion::SECCIONES;

    $usuario = auth()->user();
@endphp

<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Secciones">
    @foreach ($secciones as $seccion)
        @continue($seccion['roles'] !== [] && ! $usuario->tieneRolPropio(...$seccion['roles']))

        @php
            $habilitada = (bool) $seccion['ruta'];
            
            // Si tiene activaEn se evalúa esa lista/patrón (ej. admin.*).
            // Si no tiene activaEn, exige coincidencia exacta con su nombre de ruta.
            if (isset($seccion['activaEn'])) {
                $patronesActivos = (array) $seccion['activaEn'];
                $activa = $habilitada && request()->routeIs(...$patronesActivos);
            } else {
                $activa = $habilitada && request()->routeIs($seccion['ruta']);
            }
        @endphp

        {{-- El rotulo se dibuja con el item, asi no queda suelto si el rol no lo ve. --}}
        @if ($seccion['titulo'] ?? false)
            <hr class="my-3 border-white/10" aria-hidden="true">

            <p class="px-3 pb-1 text-[0.6875rem] font-semibold uppercase tracking-widest text-white/40">
                {{ $seccion['titulo'] }}
            </p>
        @endif

        @if ($habilitada)
            <a
                href="{{ route($seccion['ruta']) }}"
                @if ($activa) aria-current="page" @endif
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors
                    {{ $activa
                        ? 'bg-white/10 font-semibold text-white'
                        : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
            >
                {{-- Relleno para la seccion activa, trazo para el resto (Manual de Marca). --}}
                <x-icono :nombre="$seccion['icono']" :relleno="$activa" class="text-xl" />
                <span>{{ $seccion['etiqueta'] }}</span>

                @if ($activa)
                    <span class="ml-auto h-5 w-1 rounded-full bg-hu-dorado" aria-hidden="true"></span>
                @endif
            </a>
        @else
            <span
                class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/35"
                title="Todavía no implementado"
            >
                <x-icono :nombre="$seccion['icono']" class="text-xl" />
                <span>{{ $seccion['etiqueta'] }}</span>
            </span>
        @endif
    @endforeach
</nav>