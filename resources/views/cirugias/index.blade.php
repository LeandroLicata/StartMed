@extends('layouts.app')

@section('titulo', 'Cirugías')
@section('subtitulo', 'Todas las cirugías, pasadas y futuras')

@section('contenido')

    <x-tarjeta titulo="Cirugías" icono="event">
        <x-slot:acciones>
            <x-estado tono="info">{{ $cirugias->total() }}</x-estado>
            <x-boton icono="add" forma="grupo" :href="route('cirugias.crear')" class="px-4 py-1.5 text-xs">
                Nueva cirugía
            </x-boton>
        </x-slot:acciones>

        <x-filtro-cirugias
            :action="route('cirugias.index')"
            :filtros="$filtros"
            :estados-cirugia="$estadosCirugia"
            :quirofanos-catalogo="$quirofanosCatalogo"
            :obras-sociales-catalogo="$obrasSocialesCatalogo"
            :hay-filtros-activos="$hayFiltrosActivos"
        />

        <x-tabla-cirugias :cirugias="$cirugias" />

        <div class="mt-4">
            <x-paginador :paginador="$cirugias" />
        </div>
    </x-tarjeta>

@endsection
