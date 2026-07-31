@extends('layouts.app')

@section('titulo', 'Inicio - StartMed')

@section('contenido')
@php($persona = auth()->user()->personal?->persona)

<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">StartMed</h1>
            <p class="text-sm text-gray-600">
                Sesion iniciada como <strong>{{ auth()->user()->nombreUsuario }}</strong>
                @if ($persona)
                    &mdash; {{ $persona->nombre_completo }}
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-100">
                Salir
            </button>
        </form>
    </div>

    @if ($roles = auth()->user()->personal?->rolesVigentes)
        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="text-sm font-medium text-gray-500">Roles vigentes</h2>
            <ul class="mt-2 list-inside list-disc text-sm">
                @forelse ($roles as $rol)
                    <li>{{ $rol->nombreRol }}</li>
                @empty
                    <li class="list-none text-gray-500">Sin roles asignados.</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
@endsection
