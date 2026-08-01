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
            :valor="$enRiesgo->count()"
            etiqueta="Casos en riesgo"
            icono="warning"
            :tono="$enRiesgo->isEmpty() ? 'exito' : 'error'"
            detalle="Con algún requisito sin cumplir"
        />
    </div>

    {{-- Riesgo de suspensión --}}
    @if ($enRiesgo->isNotEmpty())
        <x-tarjeta titulo="Riesgo de suspensión" icono="warning" class="mt-6">
            <x-slot:acciones>
                <x-estado tono="error">{{ $enRiesgo->count() }}</x-estado>
            </x-slot:acciones>

            <ul class="divide-y divide-hu-gris-suave/60">
                @foreach ($enRiesgo as $caso)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="font-semibold text-hu-azul">
                                {{ $caso->nombrePaciente() }}
                                <span class="font-normal text-hu-gris-medio">· {{ $caso->procedimiento() }}</span>
                            </p>

                            <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-hu-gris">
                                @foreach ($caso->pendientes() as $pendiente)
                                    <li class="flex items-center gap-1">
                                        <x-icono nombre="error" class="text-sm text-red-600" relleno />
                                        {{ $pendiente }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="text-right">
                            <p class="text-sm font-semibold text-hu-azul">
                                {{ $caso->cuando()?->translatedFormat('D j/m · H:i') }}
                            </p>
                            @php($dias = $caso->diasRestantes())
                            <p class="text-xs {{ $dias !== null && $dias <= 2 ? 'font-semibold text-red-700' : 'text-hu-gris-medio' }}">
                                @if ($dias === 0)
                                    Es hoy
                                @elseif ($dias === 1)
                                    Falta 1 día
                                @else
                                    Faltan {{ $dias }} días
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    {{-- Checklist por paciente --}}
    <x-tarjeta titulo="Estado de los pacientes" icono="groups" class="mt-6">
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
                    @forelse ($cirugias as $caso)
                        <tr class="align-middle hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3">
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
                                No hay cirugías programadas de hoy en adelante.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tarjeta>

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
                                <li class="flex items-start gap-3 py-3">
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
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
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
