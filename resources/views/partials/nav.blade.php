@php
    /*
     * Cada item declara los roles que lo ven. El administrador ve todo
     * (lo resuelve Usuario::tieneRol). Los que todavia no tienen pantalla
     * quedan atenuados y no navegan.
     */
    $secciones = [
        [
            'etiqueta' => 'Tablero',
            'icono' => 'home',
            'ruta' => 'dashboard',
            'roles' => ['Gestor de quirófano', 'Dirección médica'],
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
        ['etiqueta' => 'Quirófanos', 'icono' => 'meeting_room', 'ruta' => null, 'roles' => []],
        ['etiqueta' => 'Pacientes', 'icono' => 'groups', 'ruta' => null, 'roles' => []],
        ['etiqueta' => 'Materiales', 'icono' => 'inventory_2', 'ruta' => null, 'roles' => []],
        ['etiqueta' => 'Hemoderivados', 'icono' => 'bloodtype', 'ruta' => null, 'roles' => []],
        ['etiqueta' => 'Obras sociales', 'icono' => 'shield', 'ruta' => null, 'roles' => []],
        ['etiqueta' => 'Personal', 'icono' => 'badge', 'ruta' => null, 'roles' => []],
    ];

    $usuario = auth()->user();
@endphp

<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Secciones">
    @foreach ($secciones as $seccion)
        @continue($seccion['roles'] !== [] && ! $usuario->tieneRol(...$seccion['roles']))

        @php
            $habilitada = (bool) $seccion['ruta'];
            // El asterisco deja activa la sección en sus subrutas, p. ej. el
            // formulario de evaluación anestésica sigue resaltando "Evaluaciones".
            $activa = $habilitada && request()->routeIs($seccion['ruta'], $seccion['ruta'].'.*');
        @endphp

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
