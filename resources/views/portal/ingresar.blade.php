@extends('layouts.portal')

{{--
    La puerta del portal: el paciente pone su DNI y le llega un enlace de un solo
    uso por correo. La respuesta es la misma exista o no el DNI (ver
    AccesoController::solicitar), asi que esta pantalla no puede decir mas que eso.
--}}

@section('titulo', 'Ingresar')

@section('contenido')
    <x-tarjeta class="mx-auto max-w-md">
        <form method="POST" action="{{ route('portal.acceso.solicitar') }}" class="flex flex-col gap-5 px-5 py-8">
            @csrf

            <div class="space-y-2 text-center">
                <h1 class="titulo-corto text-lg text-hu-azul">Portal del paciente</h1>
                <p class="text-sm text-hu-gris-medio">
                    Ingresá tu DNI y te enviamos un enlace al correo que tenemos registrado.
                </p>
            </div>

            <x-input
                nombre="documento"
                etiqueta="DNI"
                inputmode="numeric"
                autocomplete="off"
                placeholder="Sin puntos ni espacios"
                requerido
            />

            <x-boton class="self-center">Enviar enlace</x-boton>

            @if (Route::has('portal.entrar-local'))
                <x-boton :href="route('portal.entrar-local')" variante="contorno" class="self-center">
                    Entrar como María García (solo en local)
                </x-boton>
            @endif
        </form>
    </x-tarjeta>
@endsection
