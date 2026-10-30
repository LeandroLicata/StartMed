{{--
    Pestaña "preanestesica" del detalle. A cargo de: Gonzalo. Recibe $caso y $soloLectura.

    El paciente completa por única vez el cuestionario preanestésico vigente.
    Una vez enviado, queda en solo lectura mostrando las respuestas registradas
    mediante ResumenCirugia::cuestionario(). En una cirugía anterior no se
    responde nada: solo se consulta.
--}}

@php
    $cuestionarioVigente = $cuestionarioVigente ?? null;
@endphp

@if ($caso->cuestionarioRespondido())
    {{-- Cuestionario ya respondido: solo lectura --}}
    <x-tarjeta titulo="Cuestionario preanestésico" icono="stethoscope">
        <x-slot:acciones>
            <x-estado tono="exito" icono="check_circle">Respondido</x-estado>
        </x-slot:acciones>

        <p class="mb-4 text-sm text-hu-gris">
            Ya respondiste este cuestionario. Tu anestesista revisa estas respuestas antes de la cirugía.
        </p>

        <dl class="divide-y divide-hu-gris-suave/70">
            @foreach ($caso->cuestionario() as $fila)
                <div class="py-3 first:pt-0 last:pb-0">
                    <dt class="text-xs font-semibold text-hu-gris-medio">{{ $fila['pregunta'] }}</dt>
                    <dd class="mt-1 text-sm font-semibold text-hu-azul">{{ $fila['respuesta'] }}</dd>
                </div>
            @endforeach
        </dl>
    </x-tarjeta>
@elseif ($soloLectura)
    {{-- Cirugía anterior sin cuestionario --}}
    <x-tarjeta titulo="Cuestionario preanestésico" icono="stethoscope">
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <x-icono nombre="history" class="text-4xl text-hu-gris-medio" />
            <p class="text-sm text-hu-gris-medio">Esta cirugía ya pasó y no se completó el cuestionario preanestésico.</p>
        </div>
    </x-tarjeta>
@elseif (! $cuestionarioVigente || $cuestionarioVigente->configTipoExamenPreAnestesicoPreguntas->isEmpty())
    {{-- Sin cuestionario vigente en el sistema --}}
    <x-tarjeta titulo="Cuestionario preanestésico" icono="stethoscope">
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <x-icono nombre="pending" class="text-4xl text-hu-dorado" />
            <p class="text-sm text-hu-gris-medio">No hay un cuestionario preanestésico disponible para responder en este momento.</p>
            <p class="text-xs text-hu-gris-medio">Si tenés dudas, consultá con el equipo del hospital.</p>
        </div>
    </x-tarjeta>
@else
    {{-- Formulario para responder el cuestionario vigente --}}
    <x-tarjeta titulo="Cuestionario preanestésico" icono="stethoscope">
        <x-slot:acciones>
            <x-estado tono="aviso" icono="schedule">Pendiente</x-estado>
        </x-slot:acciones>

        <p class="mb-5 text-sm text-hu-gris">
            Por favor respondé las siguientes preguntas sobre tu salud. La información es confidencial y le permite al equipo de anestesia preparar tu atención de forma segura. Todas las preguntas son obligatorias.
        </p>

        <form method="POST" action="{{ route('portal.cirugias.preanestesica.responder', $caso->cirugia) }}" class="space-y-6">
            @csrf

            @if ($errors->any())
                <x-alerta tipo="error" class="mb-4">
                    Por favor respondé todas las preguntas obligatorias antes de enviar.
                </x-alerta>
            @endif

            <ol class="space-y-5">
                @foreach ($cuestionarioVigente->configTipoExamenPreAnestesicoPreguntas as $indice => $pregunta)
                    @php
                        $idPregunta = $pregunta->idConfigTipoExamenPreAnestesicoPregunta;
                        $campo = 'respuestas.'.$idPregunta;
                        $hayError = $errors->has($campo);
                        $cerrada = (bool) $pregunta->requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta;
                        $opciones = $pregunta->configTipoExamenPreAnestesicoPreguntaRespuestas;
                    @endphp

                    <li @class([
                        'rounded-2xl border p-4 sm:p-5 transition-colors',
                        'border-hu-gris-suave/70 bg-white' => ! $hayError,
                        'border-red-400 bg-red-50/20' => $hayError,
                    ])>
                        <div class="flex items-start gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-hu-azul-tenue text-xs font-bold text-hu-azul">
                                {{ $indice + 1 }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-hu-azul">
                                    {{ $pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta }}
                                    <span class="text-red-700" aria-hidden="true">*</span>
                                </p>

                                <div class="mt-3">
                                    @if ($cerrada)
                                        <fieldset class="space-y-2">
                                            <legend class="sr-only">{{ $pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta }}</legend>
                                            @foreach ($opciones as $opcion)
                                                @php($idOpcion = $opcion->idConfigTipoExamenPreAnestesicoPreguntaRespuesta)
                                                <label class="flex items-center gap-3 rounded-xl border border-hu-gris-suave/60 px-3.5 py-2.5 text-sm cursor-pointer hover:bg-hu-azul-tenue/40 transition-colors">
                                                    <input
                                                        type="radio"
                                                        name="respuestas[{{ $idPregunta }}]"
                                                        value="{{ $idOpcion }}"
                                                        @checked(old($campo) == $idOpcion)
                                                        class="size-4 border-hu-gris-suave text-hu-azul focus:ring-hu-azul"
                                                        required
                                                    >
                                                    <span class="font-medium text-hu-azul">
                                                        {{ $opcion->nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                    @else
                                        <input
                                            type="text"
                                            id="pregunta-{{ $idPregunta }}"
                                            name="respuestas[{{ $idPregunta }}]"
                                            value="{{ old($campo) }}"
                                            placeholder="Escribí tu respuesta acá..."
                                            required
                                            maxlength="255"
                                            @class([
                                                'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-hu-gris placeholder:text-hu-gris-medio focus:border-hu-azul focus:ring-0',
                                                'border-hu-gris-suave' => ! $hayError,
                                                'border-red-600' => $hayError,
                                            ])
                                        >
                                    @endif

                                    @if ($hayError)
                                        <p class="mt-2 flex items-center gap-1 text-xs font-semibold text-red-700">
                                            <x-icono nombre="error" class="text-sm" relleno />
                                            {{ $errors->first($campo) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4 border-t border-hu-gris-suave/70">
                <x-boton tipo="submit" icono="check_circle" class="w-full sm:w-auto">
                    Enviar respuestas
                </x-boton>
            </div>
        </form>
    </x-tarjeta>
@endif
