<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Mi cirugía') · StartMed</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0..1,0&icon_names=assignment,call,check,check_circle,close,description,draw,event,home,info,logout,mail,no_food,notifications,person,schedule,send,stethoscope,upload_file,warning&display=block">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef2f5] text-hu-gris">
    <main class="mx-auto min-h-screen max-w-7xl px-3 py-4 sm:px-6 lg:py-6">
        @include('partials.flash')
        @yield('contenido')
    </main>
</body>
</html>
