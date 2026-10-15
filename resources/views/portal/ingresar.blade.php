@extends('layouts.portal')

{{--
    Placeholder: aca va el acceso real (DNI -> link firmado al email). Mientras
    no exista, en local se puede entrar como la paciente de la demo.
--}}

@section('titulo', 'Ingresar')

@section('contenido')
    <x-tarjeta class="mx-auto max-w-md">
        <div class="flex flex-col gap-4 px-5 py-8 text-center">
            <h1 class="titulo-corto text-lg text-hu-azul">Portal del paciente</h1>
            <p class="text-sm text-hu-gris-medio">
                El ingreso con tu DNI todavía no está disponible.
            </p>

            @if (Route::has('portal.entrar-local'))
                <x-boton :href="route('portal.entrar-local')" class="self-center">
                    Entrar como María García (solo en local)
                </x-boton>
            @endif
        </div>
    </x-tarjeta>
@endsection
