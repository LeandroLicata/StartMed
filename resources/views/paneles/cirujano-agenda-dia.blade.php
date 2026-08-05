@extends('layouts.app')

@section('titulo', 'Agenda')
@section('subtitulo', $fecha->translatedFormat('l j \d\e F \d\e Y'))

@section('contenido')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-boton variante="fantasma" forma="grupo" icono="arrow_back" :href="$volverA" class="px-3">
            Volver
        </x-boton>
    </div>

    <x-tarjeta titulo="Cirugias del dia" icono="event">
        <x-slot:acciones>
            <x-estado tono="info">{{ $cirugias->count() }}</x-estado>
        </x-slot:acciones>

        <x-tabla-cirugias :cirugias="$cirugias" />
    </x-tarjeta>

@endsection
