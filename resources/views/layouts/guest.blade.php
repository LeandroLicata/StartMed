<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Ingresar') · StartMed</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0..1,0&icon_names=error,lock,person&display=block">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">

<div class="flex min-h-screen flex-col lg:flex-row">

    {{-- Panel de marca. En mobile se reduce a una franja con el logo. --}}
    <div class="flex items-center justify-center bg-hu-azul px-6 py-10 lg:w-2/5 lg:py-16">
        <div class="w-full max-w-sm space-y-6 text-center lg:text-left">
            <img
                src="{{ asset('img/logo-hu-blanco.svg') }}"
                alt="Hospital Universitario"
                class="mx-auto h-12 w-auto lg:mx-0 lg:h-16"
            >

            <div class="hidden space-y-3 lg:block">
                <h2 class="titulo-corto text-2xl text-white">StartMed</h2>
                <p class="text-sm leading-relaxed text-white/70">
                    Gestión de cirugías: programación de quirófanos, autorizaciones,
                    pedidos de materiales y hemoderivados, evaluación pre-anestésica
                    y consentimientos informados.
                </p>
                <div class="h-1 w-16 rounded-full bg-hu-dorado"></div>
            </div>
        </div>
    </div>

    <div class="flex flex-1 items-center justify-center px-6 py-10">
        <div class="w-full max-w-sm">
            @yield('contenido')
        </div>
    </div>

</div>

</body>
</html>
