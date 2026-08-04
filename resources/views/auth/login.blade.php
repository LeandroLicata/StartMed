@extends('layouts.guest')

@section('titulo', 'Ingresar')

@section('contenido')
    <div class="mb-8 space-y-1">
        <h1 class="titulo-corto text-xl text-hu-azul">Ingresar</h1>
        <p class="text-sm text-hu-gris-medio">Accedé con tu usuario del hospital.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-input
            nombre="nombreUsuario"
            etiqueta="Usuario"
            autocomplete="username"
            autofocus
            requerido
        />

        <x-input
            nombre="password"
            etiqueta="Contraseña"
            tipo="password"
            autocomplete="current-password"
            requerido
        />

        <x-boton class="w-full">Ingresar</x-boton>
    </form>

    <p class="mt-8 text-center text-xs text-hu-gris-medio">
        ¿Problemas para entrar? Comunicate con el área de sistemas.
    </p>
@endsection
