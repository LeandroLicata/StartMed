@extends('layouts.app')

@php
    $esNuevo = $paciente === null;
    $titulo = $esNuevo ? 'Nuevo paciente' : 'Editar datos de '.$paciente->nombre_completo;
@endphp

@section('titulo', $titulo)
@section('subtitulo', 'Padrón de pacientes')

@section('contenido')
    <div class="mb-4">
        <x-boton
            :href="$esNuevo ? route('pacientes.index') : route('pacientes.show', $paciente)"
            variante="fantasma"
            icono="arrow_back"
            forma="grupo"
        >
            {{ $esNuevo ? 'Volver al padrón' : 'Volver a la ficha' }}
        </x-boton>
    </div>

    <x-tarjeta :titulo="$titulo" icono="person" class="max-w-3xl">
        <form
            method="POST"
            action="{{ $esNuevo ? route('pacientes.store') : route('pacientes.update', $paciente) }}"
            class="space-y-6"
        >
            @csrf
            @unless ($esNuevo)
                @method('PUT')
            @endunless

            @include('partials.persona-campos', ['persona' => $paciente])

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <x-boton tipo="submit" icono="check_circle">
                    {{ $esNuevo ? 'Registrar paciente' : 'Guardar cambios' }}
                </x-boton>

                <x-boton
                    :href="$esNuevo ? route('pacientes.index') : route('pacientes.show', $paciente)"
                    variante="fantasma"
                >
                    Cancelar
                </x-boton>
            </div>
        </form>
    </x-tarjeta>
@endsection