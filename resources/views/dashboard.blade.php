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
            <x-estado tono="info">{{ $cirugiasFiltradas->count() }}</x-estado>
        </x-slot:acciones>

        <form method="GET" action="{{ route('dashboard') }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <x-input nombre="q" etiqueta="Buscar" :valor="$filtros['q'] ?? null" placeholder="Paciente o DNI" />

            <x-select
                nombre="estado"
                etiqueta="Estado"
                :opciones="$estadosCirugia->pluck('nombreEstadoCirugia', 'nombreEstadoCirugia')"
                :valor="$filtros['estado'] ?? null"
            />

            <x-select
                nombre="idQuirofano"
                etiqueta="Quirófano"
                :opciones="$quirofanosCatalogo->mapWithKeys(fn ($q) => [$q->idQuirofano => 'Nº '.$q->nroQuirofano.' — '.$q->nombreQuirofano])"
                :valor="$filtros['idQuirofano'] ?? null"
            />

            <x-select
                nombre="idObraSocial"
                etiqueta="Obra social"
                :opciones="$obrasSocialesCatalogo->pluck('nombreObraSocial', 'idObraSocial')"
                :valor="$filtros['idObraSocial'] ?? null"
            />

            <x-input nombre="desde" etiqueta="Desde" tipo="date" :valor="$filtros['desde'] ?? null" />
            <x-input nombre="hasta" etiqueta="Hasta" tipo="date" :valor="$filtros['hasta'] ?? null" />

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                <x-boton tipo="submit" forma="grupo">Filtrar</x-boton>
                @if ($hayFiltrosActivos)
                    <x-boton variante="fantasma" forma="grupo" :href="route('dashboard')">Limpiar filtros</x-boton>
                @endif
            </div>
        </form>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-208 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Paciente</th>
                        <th class="px-3 pb-2 font-semibold">Procedimiento</th>
                        <th class="px-3 pb-2 font-semibold">Fecha</th>
                        <th class="px-3 pb-2 font-semibold">Estudios</th>
                        <th class="px-3 pb-2 font-semibold">Autorización</th>
                        <th class="px-3 pb-2 font-semibold">Anestesia</th>
                        <th class="px-3 pb-2 font-semibold">Materiales</th>
                        <th class="px-5 pb-2 font-semibold">Estado</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($cirugiasFiltradas as $caso)
                        <tr class="relative align-middle hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3">
                                <a
                                    href="{{ route('cirugias.show', $caso->cirugia) }}"
                                    class="absolute inset-0"
                                    aria-label="Ver cirugía de {{ $caso->nombrePaciente() }}"
                                ></a>
                                <p class="font-semibold text-hu-azul">{{ $caso->nombrePaciente() }}</p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $caso->plan?->nombrePlan ?? 'Sin plan' }}
                                </p>
                            </td>

                            <td class="px-3 py-3">
                                <p>{{ $caso->procedimiento() }}</p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $caso->cirujano() ?? 'Sin cirujano' }}
                                    @if ($caso->cirugia->requiereImplante)
                                        · <span class="text-hu-dorado-oscuro">con implante</span>
                                    @endif
                                </p>
                            </td>

                            <td class="px-3 py-3 whitespace-nowrap">
                                <p>{{ $caso->cuando()?->translatedFormat('D j/m') }}</p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $caso->cuando()?->format('H:i') }} hs
                                    @if ($caso->quirofano)
                                        · Q{{ $caso->quirofano->nroQuirofano }}
                                    @endif
                                </p>
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$caso->estudiosPendientes() === 0 ? 'exito' : 'aviso'">
                                    {{ $caso->estudiosSubidos() }}/{{ $caso->estudiosTotal() }}
                                </x-estado>
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$caso->autorizacionAprobada() ? 'exito' : 'aviso'">
                                    {{ $caso->autorizacion() }}
                                </x-estado>
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$caso->evaluacionCompleta() ? 'exito' : 'aviso'">
                                    {{ $caso->asa() ?? $caso->evaluacion() }}
                                </x-estado>
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$caso->materialesAprobados() ? 'exito' : 'aviso'">
                                    {{ $caso->materiales() }}
                                </x-estado>
                            </td>

                            <td class="px-5 py-3">
                                <x-estado
                                    :tono="$caso->semaforo()"
                                    :icono="$caso->estaLista() ? 'check_circle' : 'warning'"
                                >
                                    {{ $caso->estaLista() ? 'Listo' : $caso->estado() }}
                                </x-estado>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-hu-gris-medio">
                                Ninguna cirugía coincide con la búsqueda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
