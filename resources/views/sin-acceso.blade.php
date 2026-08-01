@extends('layouts.app')

@section('titulo', 'Sin secciones asignadas')

@section('contenido')
    <div class="mx-auto max-w-lg py-12 text-center">
        <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-hu-dorado-tenue text-hu-dorado-oscuro">
            <x-icono nombre="badge" class="text-3xl" relleno />
        </span>

        <h2 class="titulo-corto mt-5 text-lg text-hu-azul">Todavía no tenés un rol asignado</h2>

        <p class="mt-2 text-sm leading-relaxed text-hu-gris">
            Tu usuario existe y la contraseña es correcta, pero no tiene ningún rol
            vigente, así que no hay secciones que mostrarte. Pedile a un administrador
            que te asigne uno.
        </p>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <x-boton variante="contorno" icono="logout">Cerrar sesión</x-boton>
        </form>
    </div>
@endsection
