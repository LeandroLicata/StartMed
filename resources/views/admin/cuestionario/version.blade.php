@extends('layouts.app')

@php
    $vigente = $version->fechaFinVigeConfigTipoExamenPreAnestesico === null;
    $respondidos = $version->examen_pre_anestesico_configes_count;
@endphp

@section('titulo', 'Cuestionario preanestésico')
@section('subtitulo', 'Administración · Versión del '.($version->fechaInicioVigeConfigTipoExamenPreAnestesico?->format('d/m/Y') ?? 's/f'))

@section('contenido')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-boton :href="route('admin.cuestionario.index')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a versiones
        </x-boton>

        @if ($vigente)
            <form
                method="POST"
                action="{{ route('admin.cuestionario.destroy', $version) }}"
                data-confirmar-titulo="Retirar el cuestionario preanestésico"
                data-confirmar="Los pacientes dejan de tener un cuestionario que completar hasta que publiques otra versión. Los ya respondidos no se tocan."
                data-confirmar-accion="Retirar"
            >
                @csrf
                @method('DELETE')
                <x-boton variante="peligro" icono="cancel" forma="grupo">Retirar</x-boton>
            </form>
        @endif
    </div>

    @unless ($editable)
        <x-alerta tipo="aviso" titulo="Solo lectura" class="mb-4">
            @if ($respondidos > 0)
                {{ $respondidos }} {{ str('cuestionario')->plural($respondidos) }} se respondieron con esta
                versión. El expediente lee el texto de las preguntas de acá, así que editarlas cambiaría
                qué se le preguntó a esos pacientes. Para cambiar algo, publicá una versión nueva.
            @else
                Esta versión está cerrada. Para cambiar algo, publicá una versión nueva.
            @endif
        </x-alerta>
    @endunless

    <x-tarjeta titulo="Preguntas" icono="assignment">
        <x-slot:acciones>
            <x-estado tono="info">{{ $preguntas->count() }}</x-estado>
        </x-slot:acciones>

        <ol class="space-y-5">
            @forelse ($preguntas as $numero => $pregunta)
                @php($cerrada = (bool) $pregunta->requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta)

                <li class="rounded-xl border border-hu-gris-suave/70 p-4">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 text-xs font-black tabular-nums text-hu-dorado">
                            {{ str_pad($numero + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>

                        <div class="min-w-0 flex-1 space-y-3">
                            @if ($editable)
                                <form
                                    method="POST"
                                    action="{{ route('admin.cuestionario.preguntas.update', [$version, $pregunta]) }}"
                                    class="flex flex-wrap items-end gap-3"
                                >
                                    @csrf
                                    @method('PUT')

                                    <div class="min-w-64 flex-1">
                                        <x-input
                                            nombre="nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"
                                            :id="'pregunta-'.$pregunta->idConfigTipoExamenPreAnestesicoPregunta"
                                            etiqueta="Pregunta"
                                            :valor="$pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"
                                        />
                                    </div>

                                    <label class="flex items-center gap-2 pb-2.5 text-sm text-hu-gris">
                                        <input type="hidden" name="requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta" value="0">
                                        <input
                                            type="checkbox"
                                            name="requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta"
                                            value="1"
                                            @checked($cerrada)
                                            class="size-4 rounded border-hu-gris-suave text-hu-azul focus:ring-hu-azul"
                                        >
                                        Con opciones
                                    </label>

                                    <x-boton tipo="submit" variante="contorno" forma="grupo" class="mb-0.5">
                                        Guardar
                                    </x-boton>
                                </form>
                            @else
                                <p class="font-semibold text-hu-azul">
                                    {{ $pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta }}
                                </p>
                            @endif

                            @if ($cerrada)
                                <div class="flex flex-wrap items-center gap-2">
                                    @forelse ($pregunta->configTipoExamenPreAnestesicoPreguntaRespuestas as $respuesta)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-hu-azul-tenue px-2.5 py-0.5
                                                     text-xs font-semibold text-hu-azul">
                                            {{ $respuesta->nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta }}

                                            @if ($editable)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.cuestionario.respuestas.destroy', [$version, $pregunta, $respuesta]) }}"
                                                    class="inline"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="text-hu-azul/60 hover:text-red-700"
                                                        aria-label="Quitar opción"
                                                    >
                                                        <x-icono nombre="close" class="text-sm" />
                                                    </button>
                                                </form>
                                            @endif
                                        </span>
                                    @empty
                                        {{-- Cerrada y sin opciones: el paciente no tendría qué elegir. --}}
                                        <x-estado tono="error" icono="warning">Sin opciones para elegir</x-estado>
                                    @endforelse
                                </div>

                                @if ($editable)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.cuestionario.respuestas.store', [$version, $pregunta]) }}"
                                        class="flex flex-wrap items-end gap-2"
                                    >
                                        @csrf
                                        <div class="min-w-56 flex-1">
                                            <x-input
                                                nombre="nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta"
                                                :id="'respuesta-'.$pregunta->idConfigTipoExamenPreAnestesicoPregunta"
                                                placeholder="Agregar una opción de respuesta"
                                            />
                                        </div>

                                        <x-boton tipo="submit" variante="fantasma" forma="grupo" icono="add">
                                            Agregar
                                        </x-boton>
                                    </form>
                                @endif
                            @else
                                <p class="text-xs text-hu-gris-medio">Se responde con texto libre.</p>
                            @endif
                        </div>

                        @if ($editable)
                            <form
                                method="POST"
                                action="{{ route('admin.cuestionario.preguntas.destroy', [$version, $pregunta]) }}"
                                data-confirmar-titulo="Eliminar la pregunta"
                                data-confirmar="Se elimina junto con sus opciones de respuesta. Como nadie respondió esta versión todavía, no afecta a ningún paciente."
                                data-confirmar-accion="Eliminar"
                            >
                                @csrf
                                @method('DELETE')
                                <x-boton variante="fantasma" forma="grupo" class="px-2 text-red-700">
                                    <x-icono nombre="delete" class="text-lg" />
                                    <span class="sr-only">Eliminar pregunta</span>
                                </x-boton>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="py-8 text-center text-hu-gris-medio">
                    Esta versión todavía no tiene preguntas.
                </li>
            @endforelse
        </ol>
    </x-tarjeta>

    @if ($editable)
        <x-tarjeta titulo="Agregar una pregunta" icono="add" class="mt-5">
            <form
                method="POST"
                action="{{ route('admin.cuestionario.preguntas.store', $version) }}"
                class="flex flex-wrap items-end gap-3"
            >
                @csrf

                <div class="min-w-72 flex-1">
                    <x-input
                        nombre="nombrePreguntaConfigTipoExamenPreAnestesicoPregunta"
                        etiqueta="Pregunta"
                        placeholder="¿Tenés alergia a algún medicamento?"
                        requerido
                    />
                </div>

                <label class="flex items-center gap-2 pb-2.5 text-sm text-hu-gris">
                    <input type="hidden" name="requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta" value="0">
                    <input
                        type="checkbox"
                        name="requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta"
                        value="1"
                        class="size-4 rounded border-hu-gris-suave text-hu-azul focus:ring-hu-azul"
                    >
                    Con opciones
                </label>

                <x-boton tipo="submit" icono="add" class="mb-0.5">Agregar</x-boton>
            </form>

            <p class="mt-3 text-xs text-hu-gris-medio">
                Sin marcar «Con opciones», el paciente responde con texto libre. Marcada, hay que
                cargarle las opciones entre las que elige.
            </p>
        </x-tarjeta>
    @endif

@endsection
