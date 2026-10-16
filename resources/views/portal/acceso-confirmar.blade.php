@extends('layouts.portal')

{{--
    Paso intermedio del enlace del mail. Abrir el enlace solo llega hasta aca:
    entrar de verdad es el POST de este boton. Un cliente de correo que abre los
    links para analizarlos no hace ese POST, asi que no gasta el enlace.
--}}

@section('titulo', 'Ingresar')

@section('contenido')
    <x-tarjeta class="mx-auto max-w-md">
        <form method="POST" action="{{ $accion }}" class="flex flex-col items-center gap-4 px-5 py-8 text-center">
            @csrf

            <x-icono nombre="shield" class="text-4xl text-hu-dorado" />
            <h1 class="titulo-corto text-lg text-hu-azul">Confirmá tu ingreso</h1>
            <p class="text-sm text-hu-gris-medio">
                Tocá el botón para entrar a tu portal. Este enlace sirve una sola vez.
            </p>

            <x-boton>Entrar</x-boton>
        </form>
    </x-tarjeta>
@endsection
