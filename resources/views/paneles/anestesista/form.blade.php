@extends('layouts.app')

@section('titulo', $evaluacion ? 'Editar evaluación pre-anestésica' : 'Evaluación pre-anestésica')
@section('subtitulo', $resumen->nombrePaciente().' · '.$resumen->procedimiento())

@section('contenido')

    @php
        $asaActual = $evaluacion?->evaluacionTipoAsas->firstWhere('fechaFinTipoAsa', null)?->idTipoAsa;
        $anestesiaActual = $evaluacion?->evaluacionTipoAnestesias->firstWhere('fechaFinTipoAnestesia', null)?->idTipoAnestesia;
        $asaSeleccion = old('idTipoAsa', $asaActual !== null ? (string) $asaActual : '');
        $anestesiaSeleccion = old('idTipoAnestesia', $anestesiaActual !== null ? (string) $anestesiaActual : '');
        $cuestionario = $resumen->cuestionario();
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-boton variante="fantasma" forma="grupo" icono="arrow_back" :href="route('anestesista')" class="px-3">
            Volver a mis evaluaciones
        </x-boton>

        <div class="flex flex-wrap items-center gap-2">
            <x-estado :tono="$evaluacion && $resumen->evaluacionCompleta() ? 'exito' : 'neutro'">
                {{ $evaluacion ? $resumen->evaluacion() : 'Sin evaluación' }}
            </x-estado>

            @if ($resumen->cuando())
                <x-estado tono="info" icono="schedule">
                    {{ $resumen->cuando()->translatedFormat('l j/m · H:i') }} hs
                </x-estado>
            @endif
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">

        {{-- Resumen del caso --}}
        <div class="space-y-5 lg:col-span-1">
            <x-tarjeta titulo="Cirugía" icono="event">
                <dl class="divide-y divide-hu-gris-suave/60 text-sm">
                    @php
                        $datos = [
                            'Paciente' => trim(
                                ($resumen->paciente?->tipoDocumento?->nombreTipoDocumento ?? '').' '
                                .($resumen->paciente?->documento ?? '')
                            ),
                            'Procedimiento' => $resumen->procedimiento(),
                            'Cirujano' => $resumen->cirujano(),
                            'Fecha' => $resumen->cuando()?->translatedFormat('l j/m/Y'),
                            'Hora' => $resumen->cuando()?->format('H:i').' hs',
                        ];
                    @endphp

                    @foreach (array_filter($datos, fn ($v) => trim((string) $v) !== '') as $etiqueta => $valor)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-hu-gris-medio">{{ $etiqueta }}</dt>
                            <dd class="text-right font-semibold text-hu-azul">{{ $valor }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-tarjeta>

            @if ($cuestionario->isNotEmpty())
                <x-tarjeta titulo="Cuestionario del paciente" icono="assignment">
                    <dl class="grid gap-3">
                        @foreach ($cuestionario as $fila)
                            <div class="border-b border-hu-gris-suave/60 pb-2 last:border-0">
                                <dt class="text-xs text-hu-gris-medio">{{ $fila['pregunta'] }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-hu-azul">{{ $fila['respuesta'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-tarjeta>
            @endif
        </div>

        {{-- Formulario --}}
        @include('paneles.anestesista.formulario')
    </div>

@endsection
