@extends('layouts.portal')

{{--
    Paso intermedio del enlace del mail. Abrir el enlace solo llega hasta aca:
    entrar de verdad es el POST de este boton. Un cliente de correo que abre los
    links para analizarlos no hace ese POST, asi que no gasta el enlace.
--}}

@section('titulo', 'Ingresar')
@section('pantalla-ingreso', true)

@section('contenido')
    <div class="overflow-hidden rounded-2xl border border-white/50 bg-white/95 shadow-2xl backdrop-blur-md">
        {{-- Detalle dorado superior de marca institucional --}}
        <div class="h-1.5 w-full bg-hu-dorado"></div>

        <form method="POST" action="{{ $accion }}" class="flex flex-col items-center gap-6 p-6 text-center sm:p-8">
            @csrf

            <a href="{{ route('portal.ingresar') }}" class="inline-block transition-opacity hover:opacity-95" aria-label="Hospital Universitario">
                <img
                    src="{{ asset('img/logo-hu.svg') }}"
                    alt="Hospital Universitario"
                    class="mx-auto h-11 w-auto"
                >
            </a>

            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-hu-dorado-tenue text-hu-dorado">
                <x-icono nombre="shield" class="text-3xl text-hu-dorado" relleno />
            </div>

            <div class="space-y-2">
                <h1 class="titulo-corto text-xl text-hu-azul">Confirmá tu ingreso</h1>
                <p class="text-sm leading-relaxed text-hu-gris">
                    Tocá el botón para entrar a tu portal. Este enlace sirve una sola vez.
                </p>
            </div>

            <div class="w-full pt-1">
                <x-boton class="w-full justify-center">Entrar</x-boton>
            </div>
        </form>
    </div>
@endsection
