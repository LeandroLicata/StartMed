@php
    /*
     * Cada item declara los roles que lo ven. El administrador ve todo
     * (lo resuelve Usuario::tieneRol). Los que todavia no tienen pantalla
     * quedan atenuados y no navegan.
     *
     * El menu es lo que el usuario *hace*; la carga de datos maestros entra
     * toda por Administracion, que es su propio indice. Por eso no hay atajos
     * a catalogos sueltos aca: duplicarian lo que ya agrupa /admin.
     *
     * 'activaEn' permite marcar el item con un patron de rutas (admin.*)
     * cuando la seccion abarca mas de una pantalla; por defecto es su ruta.
     * 'separar' dibuja una linea encima, para cortar entre grupos de items.
     */
    $secciones = [
        [
            'etiqueta' => 'Tablero',
            'icono' => 'home',
            'ruta' => 'dashboard',
            'roles' => ['Gestor de quirófano', 'Dirección médica'],
        ],
        [
            'etiqueta' => 'Cirugías',
            'icono' => 'event',
            'ruta' => 'cirugias.index',
            'roles' => ['Gestor de quirófano'],
        ],
        [
            'etiqueta' => 'Agenda',
            'icono' => 'schedule',
            'ruta' => 'agenda',
            'roles' => ['Gestor de quirófano'],
        ],
        [
            'etiqueta' => 'Mis cirugías',
            'icono' => 'personal_injury',
            'ruta' => 'cirujano',
            'roles' => ['Cirujano'],
        ],
        [
            'etiqueta' => 'Evaluaciones',
            'icono' => 'stethoscope',
            'ruta' => 'anestesista',
            'roles' => ['Anestesista'],
        ],
        [
            'etiqueta' => 'Dirección',
            'icono' => 'monitoring',
            'ruta' => 'direccion',
            'roles' => ['Dirección médica'],
        ],
        // Todavia sin pantalla: un paciente es una Persona sin legajo.
        ['etiqueta' => 'Pacientes', 'icono' => 'groups', 'ruta' => null, 'roles' => []],

        /*
         * Unica puerta a los datos maestros: catalogos y usuarios. Queda
         * marcada en cualquier pantalla de la seccion, no solo en su indice.
         */
        [
            'etiqueta' => 'Administración',
            'icono' => 'settings',
            'ruta' => 'admin.inicio',
            'activaEn' => 'admin.*',
            'separar' => true,
            'roles' => ['Administrador'],
        ],
    ];

    $usuario = auth()->user();
@endphp

<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Secciones">
    @foreach ($secciones as $seccion)
        @continue($seccion['roles'] !== [] && ! $usuario->tieneRol(...$seccion['roles']))

        @php
            $habilitada = (bool) $seccion['ruta'];
            $activa = $habilitada && request()->routeIs($seccion['activaEn'] ?? $seccion['ruta']);
        @endphp

        {{-- La linea se dibuja con el item, asi no queda suelta si el rol no lo ve. --}}
        @if ($seccion['separar'] ?? false)
            <hr class="my-3 border-white/10" aria-hidden="true">
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
