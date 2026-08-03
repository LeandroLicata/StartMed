@extends('layouts.app')

@section('titulo', 'Agenda')
@section('subtitulo', $fecha->translatedFormat('l j \d\e F \d\e Y'))

@section('contenido')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-boton variante="fantasma" forma="grupo" icono="arrow_back" :href="$volverA" class="px-3">
            Volver
        </x-boton>

        <x-boton icono="add" :href="route('cirugias.crear', ['fecha' => $fecha->toDateString()])">
            Nueva cirugía este día
        </x-boton>
    </div>

    <x-tarjeta titulo="Cirugías del día" icono="event">
        <x-slot:acciones>
            <x-estado tono="info">{{ $cirugias->count() }}</x-estado>
        </x-slot:acciones>

        <x-filtro-cirugias
            :action="route('agenda.dia', $fecha->toDateString())"
            :filtros="$filtros"
            :estados-cirugia="$estadosCirugia"
            :quirofanos-catalogo="$quirofanosCatalogo"
            :obras-sociales-catalogo="$obrasSocialesCatalogo"
            :hay-filtros-activos="$hayFiltrosActivos"
            :con-fechas="false"
        />

        <x-tabla-cirugias :cirugias="$cirugias" />
    </x-tarjeta>

@endsection
