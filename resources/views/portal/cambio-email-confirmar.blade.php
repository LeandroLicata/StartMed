@extends('layouts.portal')

{{--
    Paso intermedio del enlace de cambio de email, igual que acceso-confirmar:
    abrir el enlace solo llega hasta aca y el cambio es el POST de este boton.
    Un cliente de correo que abre los links para analizarlos no lo gasta.
    Usa la pantalla de ingreso porque puede abrirse sin sesion.
--}}

@section('titulo', 'Confirmar email')
@section('pantalla-ingreso', true)

@section('contenido')
    <div class="overflow-hidden rounded-2xl border border-white/50 bg-white/95 shadow-2xl backdrop-blur-md">
        {{-- Detalle dorado superior de marca institucional --}}
        <div class="h-1.5 w-full bg-hu-dorado"></div>

        <form method="POST" action="{{ $accion }}" class="flex flex-col items-center gap-6 p-6 text-center sm:p-8">
            @csrf

            <img
                src="{{ asset('img/logo-hu.svg') }}"
                alt="Hospital Universitario"
                class="mx-auto h-11 w-auto"
            >

            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-hu-dorado-tenue text-hu-dorado">
                <x-icono nombre="mail" class="text-3xl text-hu-dorado" relleno />
            </div>

            <div class="space-y-2">
                <h1 class="titulo-corto text-xl text-hu-azul">Confirmá tu nuevo email</h1>
                <p class="text-sm leading-relaxed text-hu-gris">
                    Desde ahora vamos a usar <span class="font-semibold text-hu-azul">{{ $email }}</span>
                    para mandarte los enlaces de ingreso y los avisos del portal.
                </p>
            </div>

            <div class="w-full pt-1">
                <x-boton class="w-full justify-center">Confirmar</x-boton>
            </div>
        </form>
    </div>
@endsection
