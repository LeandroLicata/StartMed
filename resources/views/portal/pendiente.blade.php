@extends('layouts.portal')

{{-- Placeholder del contrato del portal: cada seccion lo reemplaza por su vista. --}}

@section('titulo', $titulo)

@section('contenido')
    <x-tarjeta>
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <x-icono nombre="pending" class="text-4xl text-hu-dorado" />
            <h1 class="titulo-corto text-lg text-hu-azul">{{ $titulo }}</h1>
            <p class="text-sm text-hu-gris-medio">Esta sección todavía está en construcción.</p>
        </div>
    </x-tarjeta>
@endsection
