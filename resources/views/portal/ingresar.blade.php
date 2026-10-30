@extends('layouts.portal')

{{--
    La puerta del portal: el paciente pone su DNI y le llega un enlace de un solo
    uso por correo. La respuesta es la misma exista o no el DNI (ver
    AccesoController::solicitar), asi que esta pantalla no puede decir mas que eso.
--}}

@section('titulo', 'Ingresar')
@section('pantalla-ingreso', true)

@section('contenido')
    <div class="overflow-hidden rounded-2xl border border-white/50 bg-white/95 shadow-2xl backdrop-blur-md">
        {{-- Detalle dorado superior de marca institucional --}}
        <div class="h-1.5 w-full bg-hu-dorado"></div>

        <form method="POST" action="{{ route('portal.acceso.solicitar') }}" class="flex flex-col gap-6 p-6 sm:p-8">
            @csrf

            <div class="space-y-4 text-center">
                <a href="{{ route('portal.ingresar') }}" class="inline-block transition-opacity hover:opacity-95" aria-label="Hospital Universitario">
                    <img
                        src="{{ asset('img/logo-hu.svg') }}"
                        alt="Hospital Universitario"
                        class="mx-auto h-11 w-auto"
                    >
                </a>

                <div class="mx-auto h-1 w-12 rounded-full bg-hu-dorado"></div>

                <div class="space-y-2">
                    <h1 class="titulo-corto text-xl text-hu-azul">Portal del paciente</h1>
                    <p class="text-sm leading-relaxed text-hu-gris">
                        Ingresá tu DNI y te enviamos un enlace al correo que tenemos registrado.
                    </p>
                </div>
            </div>

            <x-input
                nombre="documento"
                etiqueta="DNI"
                inputmode="numeric"
                autocomplete="off"
                placeholder="Sin puntos ni espacios"
                requerido
            />

            <div class="flex flex-col gap-3 pt-1">
                <x-boton class="w-full justify-center">Enviar enlace</x-boton>

                @if (Route::has('portal.entrar-local'))
                    <x-boton :href="route('portal.entrar-local')" variante="contorno" class="w-full justify-center text-center">
                        Entrar como María García (solo en local)
                    </x-boton>
                @endif
            </div>

            <p class="text-center text-xs text-hu-gris-medio">
                El enlace vence en 15 minutos y sirve una sola vez.
            </p>
        </form>
    </div>
@endsection
