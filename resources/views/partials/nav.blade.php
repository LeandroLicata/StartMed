@php
    /*
     * Cada item declara los roles que lo ven, y se resuelven con
     * Usuario::tieneRolPropio: el comodin del administrador es para *entrar*
     * a una ruta, no para llenarle el menu con el trabajo de los demas. Un
     * administrador ve su bloque; si ademas es gestor, ve tambien el de
     * gestor. Los items sin pantalla todavia quedan atenuados y no navegan.
     *
     * El menu es lo que el usuario *hace*, y administrar tambien es hacer:
     * cada modulo de administracion es su propia seccion. Lo que sigue
     * entrando por una sola puerta son los 27 catalogos, agrupados en
     * /admin; no sumar atajos sueltos aca, duplicarian ese indice.
     *
     * 'activaEn' permite marcar el item con un patron de rutas (admin.*)
     * cuando la seccion abarca mas de una pantalla; por defecto es su ruta.
     * 'titulo' abre un grupo: dibuja una linea y un rotulo encima del item.
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
        // NUEVO: Historial de cirugías para el Cirujano
        [
            'etiqueta' => 'Historial de cirugías',
            'icono' => 'history',
            'ruta' => 'cirujano.historial',
            'activaEn' => 'cirujano.historial.*',
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
        // Pacientes, disponible para Gestor, Cirujano y Anestesista
        ['etiqueta' => 'Pacientes', 'icono' => 'groups', 'ruta' => 'pacientes.index', 'roles' => ['Gestor de quirófano', 'Cirujano', 'Anestesista']],

        /*
         * Administracion. Cada item queda marcado en todas las pantallas de
         * su modulo, no solo en el indice. 'Catálogos' va ultimo y apunta a
         * /admin, que quedo siendo eso: el indice de las tablas maestras.
         */
        [
            'etiqueta' => 'Usuarios',
            'icono' => 'manage_accounts',
            'ruta' => 'admin.usuarios.index',
            'activaEn' => 'admin.usuarios.*',
            'titulo' => 'Administración',
            'roles' => ['Administrador'],
        ],
        [
            'etiqueta' => 'Consentimientos',
            'icono' => 'draw',
            'ruta' => 'admin.consentimientos.index',
            'activaEn' => 'admin.consentimientos.*',
            'roles' => ['Administrador'],
        ],
        /*
         * El unico item de este bloque que no es solo del administrador. Para
         * el anestesista no aparece bajo el rotulo 'Administración' —cuelga del
         * item Usuarios, que el no ve— sino al final de su propia lista, que es
         * donde corresponde: el cuestionario es su herramienta de trabajo.
         */
        [
            'etiqueta' => 'Cuestionario preanestésico',
            'icono' => 'assignment',
            'ruta' => 'admin.cuestionario.index',
            'activaEn' => 'admin.cuestionario.*',
            'roles' => ['Administrador', 'Anestesista'],
        ],
        [
            'etiqueta' => 'Proveedores y precios',
            'icono' => 'inventory_2',
            'ruta' => 'admin.precios.index',
            'activaEn' => 'admin.precios.*',
            'roles' => ['Administrador'],
        ],
        [
            'etiqueta' => 'Auditoría',
            'icono' => 'description',
            'ruta' => 'admin.auditoria',
            'roles' => ['Administrador'],
        ],
        [
            'etiqueta' => 'Catálogos',
            'icono' => 'folder_open',
            'ruta' => 'admin.inicio',
            // El indice y las pantallas de cada tabla, que cuelgan de el.
            'activaEn' => ['admin.inicio', 'admin.catalogos.*'],
            'roles' => ['Administrador'],
        ],
    ];

    $usuario = auth()->user();
@endphp

<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Secciones">
    @foreach ($secciones as $seccion)
        @continue($seccion['roles'] !== [] && ! $usuario->tieneRolPropio(...$seccion['roles']))

        @php
            $habilitada = (bool) $seccion['ruta'];
            // El asterisco deja activa la sección en sus subrutas, p. ej. el
            // formulario de evaluación anestésica sigue resaltando "Evaluaciones".
            $patronesActivos = (array) ($seccion['activaEn'] ?? $seccion['ruta']);
            $activa = $habilitada && request()->routeIs(...[...$patronesActivos, $seccion['ruta'].'.*']);
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
