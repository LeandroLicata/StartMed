@extends('layouts.app')

@php
    $esNuevo = $persona === null;
    $titulo = $esNuevo ? 'Nueva persona' : 'Editar persona '.$persona->nombre_completo;
    $usuarioVinculado = $persona?->personal?->usuario;
@endphp

@section('titulo', $titulo)
@section('subtitulo', 'Administración · Padrón de personas')

@section('contenido')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-boton :href="route('admin.personas.index')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a personas
        </x-boton>

        @if (! $esNuevo && $usuarioVinculado)
            <x-boton :href="route('admin.usuarios.edit', $usuarioVinculado)" variante="contorno" icono="manage_accounts" forma="grupo">
                Gestionar usuario y roles ({{ $usuarioVinculado->nombreUsuario }})
            </x-boton>
        @elseif (! $esNuevo && $persona->fechaHoraBajaPersona === null)
            <x-boton :href="route('admin.usuarios.create', ['persona' => $persona->idPersona])" variante="contorno" icono="person_add" forma="grupo">
                Crear usuario y asignar roles
            </x-boton>
        @endif
    </div>

    <x-tarjeta :titulo="$titulo" icono="badge" class="max-w-3xl">
        <form
            method="POST"
            action="{{ $esNuevo ? route('admin.personas.store') : route('admin.personas.update', $persona) }}"
            class="space-y-6"
        >
            @csrf
            @unless ($esNuevo)
                @method('PUT')
            @endunless

            @include('partials.persona-campos', ['persona' => $persona])

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <x-boton tipo="submit" icono="check_circle">
                    {{ $esNuevo ? 'Crear persona' : 'Guardar cambios' }}
                </x-boton>

                @if ($esNuevo || ! $usuarioVinculado)
                    <x-boton
                        tipo="submit"
                        name="accion"
                        value="crear_usuario"
                        variante="secundario"
                        icono="person_add"
                    >
                        Guardar y crear usuario
                    </x-boton>
                @endif

                <x-boton :href="route('admin.personas.index')" variante="fantasma">
                    Cancelar
                </x-boton>
            </div>
        </form>
    </x-tarjeta>

@endsection