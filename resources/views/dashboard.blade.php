@extends('layouts.app')

@section('titulo', 'Tablero de quirófano')
@section('subtitulo', $hoy->translatedFormat('l j \d\e F \d\e Y'))

@section('contenido')

    {{-- Panorama del día --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica
            :valor="$indicadores['cirugiasHoy']"
            etiqueta="Cirugías hoy"
            icono="event"
            :detalle="$indicadores['proximas'].' programadas de hoy en adelante'"
        />

        <x-metrica
            :valor="$quirofanosActivos"
            etiqueta="Quirófanos habilitados"
            icono="meeting_room"
            :detalle="$agenda->count().' con actividad hoy'"
        />

        <x-metrica
            :valor="$indicadores['listas'].'/'.$indicadores['cirugiasHoy']"
            etiqueta="Pacientes listos"
            icono="check_circle"
            tono="exito"
            :detalle="$indicadores['porcentajeListas'].'% del día sin pendientes'"
        />

        <x-metrica
            :valor="$enRiesgoCount"
            etiqueta="Casos en riesgo"
            icono="warning"
            :tono="$enRiesgoCount === 0 ? 'exito' : 'error'"
            detalle="Con algún requisito sin cumplir"
        />
    </div>

    {{-- Agenda de hoy por quirófano --}}
    <x-tarjeta titulo="Agenda de hoy" icono="meeting_room" class="mt-6">
        @if ($agenda->isEmpty())
            <p class="py-6 text-center text-sm text-hu-gris-medio">
                Ningún quirófano tiene cirugías asignadas para hoy.
            </p>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($agenda as $nombreQuirofano => $casos)
                    <div class="rounded-xl border border-hu-gris-suave/70">
                        <div class="flex items-center justify-between gap-2 rounded-t-xl bg-hu-azul px-4 py-2.5 text-white">
                            <p class="truncate text-sm font-semibold">{{ $nombreQuirofano }}</p>
                            <span class="shrink-0 text-xs text-white/70">
                                {{ $casos->count() }} {{ str('cirugía')->plural($casos->count()) }}
                            </span>
                        </div>

                        <ul class="divide-y divide-hu-gris-suave/60 px-4">
                            @foreach ($casos as $caso)
                                <li>
                                    <a
                                        href="{{ route('cirugias.show', $caso->cirugia) }}"
                                        class="-mx-1 flex items-start gap-3 rounded-lg px-1 py-3 transition-colors hover:bg-hu-azul-tenue/40"
                                    >
                                        <span class="w-12 shrink-0 text-sm font-black text-hu-azul">
                                            {{ $caso->cuando()?->format('H:i') }}
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-hu-azul">
                                                {{ $caso->nombrePaciente() }}
                                            </p>
                                            <p class="truncate text-xs text-hu-gris-medio">
                                                {{ $caso->procedimiento() }}
                                            </p>
                                        </div>

                                        <x-estado :tono="$caso->semaforo()" class="shrink-0">
                                            {{ $caso->estaLista() ? 'Listo' : 'Pendiente' }}
                                        </x-estado>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-tarjeta>

    {{-- Cirugías de la semana --}}
    <x-tarjeta titulo="Cirugías de la semana" icono="event" class="mt-6">
        <x-slot:acciones>
            <x-estado tono="info">{{ $cirugiasFiltradas->total() }}</x-estado>
        </x-slot:acciones>

        <x-filtro-cirugias
            :action="route('dashboard')"
            :filtros="$filtros"
            :estados-cirugia="$estadosCirugia"
            :quirofanos-catalogo="$quirofanosCatalogo"
            :obras-sociales-catalogo="$obrasSocialesCatalogo"
            :hay-filtros-activos="$hayFiltrosActivos"
        />

        <x-tabla-cirugias :cirugias="$cirugiasFiltradas" />

        @if ($cirugiasFiltradas->hasPages())
            <div class="pt-4">{{ $cirugiasFiltradas->links() }}</div>
        @endif
    </x-tarjeta>

    {{-- Dónde se traban los casos --}}
    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        <x-metrica
            :valor="$indicadores['sinAutorizacion']"
            etiqueta="Sin autorización aprobada"
            icono="shield"
            :tono="$indicadores['sinAutorizacion'] > 0 ? 'aviso' : 'exito'"
            detalle="Sobre el total de cirugías próximas"
        />

        <x-metrica
            :valor="$indicadores['estudiosPendientes']"
            etiqueta="Con estudios sin subir"
            icono="inventory_2"
            :tono="$indicadores['estudiosPendientes'] > 0 ? 'aviso' : 'exito'"
            detalle="El paciente todavía no los cargó"
        />

        <x-metrica
            :valor="$indicadores['porcentajeListas'].'%'"
            etiqueta="Procesos completos hoy"
            icono="check_circle"
            :tono="$indicadores['porcentajeListas'] >= 75 ? 'exito' : 'aviso'"
            detalle="Objetivo institucional: 75%"
        />
    </div>

@endsection
