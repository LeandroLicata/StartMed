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
        <x-tarjeta
            :titulo="$evaluacion ? 'Datos de la evaluación' : 'Nueva evaluación pre-anestésica'"
            icono="stethoscope"
            class="lg:col-span-2"
        >
            <form
                method="POST"
                action="{{ $evaluacion ? route('anestesista.update', $cirugia) : route('anestesista.store', $cirugia) }}"
                class="space-y-5"
            >
                @csrf
                @if ($evaluacion)
                    @method('PUT')
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="idTipoAsa" class="block text-sm font-semibold text-hu-azul">
                            Clasificación ASA <span class="text-red-700" aria-hidden="true">*</span>
                        </label>

                        <select
                            id="idTipoAsa"
                            name="idTipoAsa"
                            required
                            @if ($errors->has('idTipoAsa'))
                                aria-invalid="true" aria-describedby="idTipoAsa-error"
                            @endif
                            @class([
                                'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris
                                 focus:border-hu-azul focus:ring-0',
                                'border-hu-gris-suave' => ! $errors->has('idTipoAsa'),
                                'border-red-600' => $errors->has('idTipoAsa'),
                            ])
                        >
                            <option value="" disabled @selected($asaSeleccion === '')>Seleccioná una clasificación…</option>
                            @foreach ($tiposAsa as $asa)
                                <option value="{{ $asa->idTipoAsa }}" @selected($asaSeleccion === (string) $asa->idTipoAsa)>
                                    {{ $asa->aliasTipoAsa }} — {{ $asa->descripcionTipoAsa }}
                                </option>
                            @endforeach
                        </select>

                        @error('idTipoAsa')
                            <p id="idTipoAsa-error" class="flex items-center gap-1 text-xs font-semibold text-red-700">
                                <x-icono nombre="error" class="text-sm" relleno />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="idTipoAnestesia" class="block text-sm font-semibold text-hu-azul">
                            Tipo de anestesia <span class="text-red-700" aria-hidden="true">*</span>
                        </label>

                        <select
                            id="idTipoAnestesia"
                            name="idTipoAnestesia"
                            required
                            @if ($errors->has('idTipoAnestesia'))
                                aria-invalid="true" aria-describedby="idTipoAnestesia-error"
                            @endif
                            @class([
                                'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris
                                 focus:border-hu-azul focus:ring-0',
                                'border-hu-gris-suave' => ! $errors->has('idTipoAnestesia'),
                                'border-red-600' => $errors->has('idTipoAnestesia'),
                            ])
                        >
                            <option value="" disabled @selected($anestesiaSeleccion === '')>Seleccioná un tipo…</option>
                            @foreach ($tiposAnestesia as $anestesia)
                                <option
                                    value="{{ $anestesia->idTipoAnestesia }}"
                                    @selected($anestesiaSeleccion === (string) $anestesia->idTipoAnestesia)
                                >
                                    {{ $anestesia->nombreTipoAnestesia }}
                                </option>
                            @endforeach
                        </select>

                        @error('idTipoAnestesia')
                            <p id="idTipoAnestesia-error" class="flex items-center gap-1 text-xs font-semibold text-red-700">
                                <x-icono nombre="error" class="text-sm" relleno />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="observacionesEquipoEvaluacion" class="block text-sm font-semibold text-hu-azul">
                        Observaciones del equipo
                    </label>
                    <textarea
                        id="observacionesEquipoEvaluacion"
                        name="observacionesEquipoEvaluacion"
                        rows="3"
                        placeholder="Por ejemplo: indicaciones del acto, riesgos detectados…"
                        @if ($errors->has('observacionesEquipoEvaluacion'))
                            aria-invalid="true" aria-describedby="observacionesEquipoEvaluacion-error"
                        @endif
                        @class([
                            'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris
                             placeholder:text-hu-gris-medio focus:border-hu-azul focus:ring-0',
                            'border-hu-gris-suave' => ! $errors->has('observacionesEquipoEvaluacion'),
                            'border-red-600' => $errors->has('observacionesEquipoEvaluacion'),
                        ])
                    >{{ old('observacionesEquipoEvaluacion', $evaluacion?->observacionesEquipoEvaluacion) }}</textarea>

                    @error('observacionesEquipoEvaluacion')
                        <p id="observacionesEquipoEvaluacion-error" class="flex items-center gap-1 text-xs font-semibold text-red-700">
                            <x-icono nombre="error" class="text-sm" relleno />
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="observacionesPacienteEvaluacion" class="block text-sm font-semibold text-hu-azul">
                        Observaciones del paciente
                    </label>
                    <textarea
                        id="observacionesPacienteEvaluacion"
                        name="observacionesPacienteEvaluacion"
                        rows="3"
                        placeholder="Antecedentes que aporta el paciente en la entrevista…"
                        @if ($errors->has('observacionesPacienteEvaluacion'))
                            aria-invalid="true" aria-describedby="observacionesPacienteEvaluacion-error"
                        @endif
                        @class([
                            'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris
                             placeholder:text-hu-gris-medio focus:border-hu-azul focus:ring-0',
                            'border-hu-gris-suave' => ! $errors->has('observacionesPacienteEvaluacion'),
                            'border-red-600' => $errors->has('observacionesPacienteEvaluacion'),
                        ])
                    >{{ old('observacionesPacienteEvaluacion', $evaluacion?->observacionesPacienteEvaluacion) }}</textarea>

                    @error('observacionesPacienteEvaluacion')
                        <p id="observacionesPacienteEvaluacion-error" class="flex items-center gap-1 text-xs font-semibold text-red-700">
                            <x-icono nombre="error" class="text-sm" relleno />
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-hu-gris-suave/70 pt-5">
                    <x-boton variante="contorno" forma="grupo" icono="close" :href="route('anestesista')">
                        Cancelar
                    </x-boton>

                    <x-boton variante="primario" forma="grupo" tipo="submit" icono="save">
                        {{ $evaluacion ? 'Guardar cambios' : 'Registrar evaluación' }}
                    </x-boton>
                </div>
            </form>

            @if ($evaluacion)
                <form
                    method="POST"
                    action="{{ route('anestesista.destroy', $cirugia) }}"
                    class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-hu-gris-suave/70 pt-5"
                    onsubmit="return confirm('¿Eliminar la evaluación de {{ $resumen->nombrePaciente() }}? La cirugía volverá a quedar sin evaluación.');"
                >
                    @csrf
                    @method('DELETE')
                    <p class="text-xs text-hu-gris-medio">
                        Si la cargaste por error podés borrarla y volver a generarla.
                    </p>
                    <x-boton variante="peligro" forma="grupo" tipo="submit" icono="delete">
                        Eliminar evaluación
                    </x-boton>
                </form>
            @endif
        </x-tarjeta>
    </div>

@endsection
