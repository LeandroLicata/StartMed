<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Mi cirugía') · Hospital Universitario</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">

    {{--
        Layout del portal del paciente: sin el sidebar del staff. Los iconos van
        subsetados igual que en layouts/app: uno nuevo en una vista del portal
        se agrega aca o no se ve.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0..1,0&icon_names=arrow_back,assignment,call,cancel,check_circle,chevron_right,draw,error,event,event_busy,history,home,info,logout,no_food,notifications,pending,person,schedule,shield,stethoscope,upload_file,visibility,warning&display=block">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">

@php($paciente = auth('paciente')->user())

<header class="bg-hu-azul">
    <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-4 sm:px-6">
        <a href="{{ $paciente ? route('portal.inicio') : route('portal.ingresar') }}" class="block shrink-0">
            <img src="{{ asset('img/logo-hu-blanco.svg') }}" alt="Hospital Universitario" class="h-9 w-auto">
        </a>

        @if ($paciente)
            <div class="ml-auto flex min-w-0 items-center gap-3">
                <p class="hidden truncate text-sm font-semibold text-white sm:block">
                    {{ $paciente->nombre_completo }}
                </p>

                <form method="POST" action="{{ route('portal.salir') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm text-white/80 hover:bg-white/10 hover:text-white">
                        <x-icono nombre="logout" class="text-xl" />
                        <span>Salir</span>
                    </button>
                </form>
            </div>
        @endif
    </div>

    @if ($paciente)
        {{-- Tres columnas iguales: icono arriba y texto abajo en el celular, lado a lado desde sm. --}}
        <nav class="mx-auto max-w-6xl px-4 sm:px-6" aria-label="Secciones del portal">
            <ul class="grid grid-cols-3 gap-1">
                @foreach ([
                    ['portal.inicio', 'Próximas', 'Próximas cirugías', 'event', ['portal.inicio', 'portal.cirugias.*']],
                    ['portal.historial', 'Historial', 'Historial de cirugías', 'history', ['portal.historial']],
                    ['portal.perfil', 'Perfil', 'Mi perfil', 'person', ['portal.perfil']],
                ] as [$ruta, $etiqueta, $descripcion, $icono, $activaEn])
                    {{-- La vista puede fijar $navSeccionActiva: una cirugía anterior marca Historial. --}}
                    @php($activa = isset($navSeccionActiva) ? $navSeccionActiva === $ruta : request()->routeIs(...$activaEn))
                    <li>
                        <a
                            href="{{ route($ruta) }}"
                            title="{{ $descripcion }}"
                            @class([
                                'flex flex-col items-center justify-center gap-0.5 rounded-t-xl px-2 py-2.5 text-xs font-semibold transition-colors sm:flex-row sm:gap-2 sm:px-4 sm:py-3 sm:text-sm',
                                'bg-hu-gris-tenue text-hu-azul' => $activa,
                                'text-white/75 hover:text-white' => ! $activa,
                            ])
                            @if ($activa) aria-current="page" @endif
                        >
                            <x-icono :nombre="$icono" class="text-xl sm:text-lg" :relleno="$activa" />
                            {{ $etiqueta }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</header>

<main class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
    @include('partials.flash')
    @yield('contenido')
</main>

{{-- El mismo dialogo de confirmacion que el staff; lo usan los forms con data-confirmar. --}}
<x-confirmar />

@stack('scripts')

</body>
</html>
