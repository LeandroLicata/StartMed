@extends('layouts.app')

@section('titulo', 'Evaluaciones anestésicas')
@section('subtitulo', $personal->persona?->nombre_completo.' · '.($personal->matriculaProvincial ?? 'sin matrícula'))

@section('contenido')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica
            :valor="$indicadores['pendientes']"
            etiqueta="Evaluaciones pendientes"
            icono="pending"
            :tono="$indicadores['pendientes'] > 0 ? 'aviso' : 'exito'"
            :detalle="'Sobre '.$indicadores['total'].' cirugías asignadas'"
        />

        <x-metrica
            :valor="$indicadores['completas']"
            etiqueta="Completadas"
            icono="check_circle"
            tono="exito"
        />

        <x-metrica
            :valor="$indicadores['riesgoAlto']"
            etiqueta="ASA III o superior"
            icono="warning"
            :tono="$indicadores['riesgoAlto'] > 0 ? 'aviso' : 'neutro'"
            detalle="Requieren monitoreo adicional"
        />

        <x-metrica
            :valor="$indicadores['cuestionarios']"
            etiqueta="Cuestionarios recibidos"
            icono="assignment"
            detalle="Respondidos por el paciente"
        />
    </div>

    @if ($pendientes->isNotEmpty())
        <x-alerta tipo="aviso" titulo="Evaluaciones sin cerrar" class="mt-6">
            {{ $pendientes->count() }}
            {{ str('cirugía')->plural($pendientes->count()) }}
            {{ $pendientes->count() === 1 ? 'espera' : 'esperan' }}
            tu evaluación. Sin ASA asignado la cirugía no se considera lista.
        </x-alerta>
    @endif

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($evaluaciones as $caso)
            <x-tarjeta>
                <div class="flex h-full flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('cirugias.show', $caso->cirugia) }}"
                               class="font-semibold text-hu-azul hover:underline">
                                {{ $caso->nombrePaciente() }}
                            </a>
                            <p class="mt-0.5 truncate text-sm text-hu-gris-medio">
                                {{ $caso->procedimiento() }}
                            </p>
                        </div>
                        <x-icono nombre="stethoscope" class="text-2xl text-hu-dorado" relleno />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-estado tono="info" icono="schedule">
                            {{ $caso->cuando()?->translatedFormat('D j/m').' · '.$caso->cuando()?->format('H:i') }}
                        </x-estado>

                        @if ($caso->asa())
                            <x-estado :tono="in_array($caso->asa(), ['ASA III', 'ASA IV', 'ASA V'], true) ? 'aviso' : 'info'">
                                {{ $caso->asa() }}
                            </x-estado>
                        @else
                            <x-estado tono="neutro">Sin ASA</x-estado>
                        @endif

                        <x-estado
                            :tono="$caso->semaforo()"
                            :icono="$caso->estaLista() ? 'check_circle' : 'warning'"
                        >
                            {{ $caso->estaLista() ? 'Lista' : 'Pendiente' }}
                        </x-estado>
                    </div>

                    @if ($caso->alertaProfilaxis())
                        <p class="flex items-center gap-1 text-xs font-semibold text-red-700">
                            <x-icono nombre="warning" class="text-sm" relleno />
                            Alergia documentada
                        </p>
                    @endif

                    @php
                        $tieneEvaluacion = $caso->cirugia->evaluacionAnestesicas->isNotEmpty();
                        $evaluacionEtiqueta = match (true) {
                            ! $tieneEvaluacion => 'Iniciar evaluación',
                            ! $caso->evaluacionCompleta() => 'Continuar evaluación',
                            default => 'Editar evaluación',
                        };
                        $evaluacionIcono = ! $tieneEvaluacion ? 'add_circle' : 'edit_note';
                        $evaluacionRuta = $tieneEvaluacion ? 'anestesista.editar' : 'anestesista.evaluar';
                    @endphp

                    <div class="mt-auto grid grid-cols-2 gap-2 pt-1">
                        <x-boton
                            variante="contorno"
                            forma="grupo"
                            icono="folder_open"
                            :href="route('cirugias.show', $caso->cirugia)"
                            class="w-full"
                        >
                            Expediente
                        </x-boton>

                        <x-boton
                            variante="primario"
                            forma="grupo"
                            :icono="$evaluacionIcono"
                            :href="route($evaluacionRuta, $caso->cirugia)"
                            class="w-full"
                        >
                            {{ $evaluacionEtiqueta }}
                        </x-boton>
                    </div>
                </div>
            </x-tarjeta>
        @empty
            <p class="col-span-full py-10 text-center text-sm text-hu-gris-medio">
                No tenés cirugías asignadas.
            </p>
        @endforelse
    </div>

    @if ($evaluaciones->hasPages())
        <div class="mt-4">{{ $evaluaciones->links() }}</div>
    @endif

    {{-- Cuestionario del primer caso pendiente, que es el que hay que resolver --}}
    @php($proximo = $pendientes->first(fn ($c) => $c->cuestionario()->isNotEmpty()))

    @if ($proximo)
        <x-tarjeta
            titulo="Cuestionario de {{ $proximo->nombrePaciente() }}"
            icono="assignment"
            class="mt-6"
        >
            <x-slot:acciones>
                <x-boton
                    variante="contorno"
                    forma="grupo"
                    :href="route('cirugias.show', $proximo->cirugia)"
                    class="px-3 py-1.5 text-xs"
                >
                    Ver expediente
                </x-boton>
            </x-slot:acciones>

            <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                @foreach ($proximo->cuestionario() as $fila)
                    <div class="border-b border-hu-gris-suave/60 pb-2">
                        <dt class="text-xs text-hu-gris-medio">{{ $fila['pregunta'] }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-hu-azul">{{ $fila['respuesta'] }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($proximo->alertaProfilaxis())
                <x-alerta tipo="error" titulo="Alerta de profilaxis" class="mt-4">
                    {{ $proximo->alertaProfilaxis() }}
                </x-alerta>
            @endif
        </x-tarjeta>
    @endif

@endsection
