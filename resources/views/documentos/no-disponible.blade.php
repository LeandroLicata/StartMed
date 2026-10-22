{{--
    Lo que queda en la pestaña cuando «Ver» no puede entregar un adjunto
    clinico. Lo usan el portal y el expediente del staff. Recibe $titulo y
    $detalle.

    «Ver» abre el archivo en una pestaña nueva, asi que si la entrega falla esta
    pagina es lo que aparece en ella. Es suelta a proposito, sin layout: con el
    del portal o el del staff, el usuario quedaba con una segunda copia de la
    aplicacion abierta. Por lo mismo no tiene links de navegacion, solo cerrar.
    window.close() funciona porque la pestaña la abrio un link y tiene una sola
    entrada de historial; si no se cierra, el texto de abajo lo explica.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Archivo no disponible · Hospital Universitario</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0..1,0&icon_names=close,error&display=block">

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-hu-gris-tenue">

<header class="bg-hu-azul">
    <div class="mx-auto max-w-4xl px-4 py-4 sm:px-6">
        <img src="{{ asset('img/logo-hu-blanco.svg') }}" alt="Hospital Universitario" class="h-9 w-auto">
    </div>
</header>

<main class="mx-auto max-w-xl px-4 py-10 sm:px-6">
    <x-tarjeta>
        <div class="flex flex-col items-center gap-3 px-5 py-8 text-center">
            <x-icono nombre="error" class="text-4xl text-hu-dorado" />
            <h1 class="text-lg font-semibold text-hu-azul">{{ $titulo }}</h1>
            <p class="max-w-md text-sm text-hu-gris">{{ $detalle }}</p>

            <x-boton tipo="button" icono="close" class="mt-3" onclick="window.close()">
                Cerrar esta pestaña
            </x-boton>
            <p class="text-xs text-hu-gris-medio">Si no se cierra, cerrala desde el navegador.</p>
        </div>
    </x-tarjeta>
</main>

</body>
</html>
