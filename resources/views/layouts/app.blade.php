<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'StartMed') · Hospital Universitario</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">

    {{--
        Material Symbols subsetado con `icon_names`: Google devuelve solo estos
        iconos en vez de la familia completa. Si sumas uno nuevo en una vista,
        agregalo aca tambien o no se va a ver.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0..1,0&icon_names=add,arrow_back,assignment,badge,bloodtype,cancel,check_circle,close,description,draw,error,event,groups,home,info,inventory_2,logout,manage_accounts,meeting_room,menu,monitoring,no_food,pending,person,personal_injury,schedule,science,shield,stethoscope,vaccines,warning&display=block">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">

<div class="flex min-h-screen">

    {{-- Barra lateral. En mobile se muestra/oculta con el boton del header. --}}
    <aside
        id="barra-lateral"
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-hu-azul
               transition-transform lg:static lg:translate-x-0"
    >
        <div class="flex items-center justify-between border-b border-white/10 px-5 py-5">
            <a href="{{ route('dashboard') }}" class="block">
                <img
                    src="{{ asset('img/logo-hu-blanco.svg') }}"
                    alt="Hospital Universitario"
                    class="h-9 w-auto"
                >
            </a>

            <button
                type="button"
                data-cerrar-menu
                class="text-white/70 hover:text-white lg:hidden"
                aria-label="Cerrar menú"
            >
                <x-icono nombre="close" class="text-2xl" />
            </button>
        </div>

        @include('partials.nav')

        <div class="border-t border-white/10 px-5 py-4">
            <p class="text-[11px] uppercase tracking-widest text-white/40">StartMed</p>
            <p class="text-xs text-white/60">Gestión de cirugías</p>
        </div>
    </aside>

    {{-- Fondo oscuro detras del menu en mobile. --}}
    <div
        id="fondo-menu"
        data-cerrar-menu
        class="fixed inset-0 z-30 hidden bg-hu-azul-oscuro/50 lg:hidden"
        aria-hidden="true"
    ></div>

    <div class="flex min-w-0 flex-1 flex-col">

        <header class="sticky top-0 z-20 border-b border-hu-gris-suave/70 bg-white">
            <div class="flex items-center gap-4 px-4 py-3.5 sm:px-6">
                <button
                    type="button"
                    id="abrir-menu"
                    class="text-hu-azul lg:hidden"
                    aria-label="Abrir menú"
                    aria-controls="barra-lateral"
                    aria-expanded="false"
                >
                    <x-icono nombre="menu" class="text-2xl" />
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="titulo-corto truncate text-base text-hu-azul">
                        @yield('titulo', 'Inicio')
                    </h1>
                    @hasSection('subtitulo')
                        <p class="truncate text-xs text-hu-gris-medio">@yield('subtitulo')</p>
                    @endif
                </div>

                @auth
                    @php($persona = auth()->user()->personal?->persona)

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-semibold text-hu-azul">
                                {{ $persona?->nombre_completo ?? auth()->user()->nombreUsuario }}
                            </p>
                            <p class="text-xs text-hu-gris-medio">{{ auth()->user()->nombreUsuario }}</p>
                        </div>

                        <span
                            class="flex size-10 items-center justify-center rounded-full bg-hu-azul-tenue
                                   text-hu-azul"
                            aria-hidden="true"
                        >
                            <x-icono nombre="person" class="text-xl" relleno />
                        </span>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-boton variante="fantasma" icono="logout" class="px-3">
                                <span class="sr-only sm:not-sr-only">Salir</span>
                            </x-boton>
                        </form>
                    </div>
                @endauth
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6">
            <div class="mx-auto max-w-6xl">
                @include('partials.flash')
                @yield('contenido')
            </div>
        </main>

    </div>
</div>

</body>
</html>
