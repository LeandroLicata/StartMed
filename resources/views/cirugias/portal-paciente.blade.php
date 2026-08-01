@extends('layouts.app')

@section('titulo', 'Portal del paciente')
@section('subtitulo', $caso->nombrePaciente().' · '.$caso->procedimiento())

@section('contenido')

    <x-alerta tipo="info" titulo="Vista previa" class="mb-6">
        Así vería el paciente su cirugía. Todavía no puede entrar por su cuenta:
        <code class="rounded bg-white/60 px-1">Usuario</code> cuelga de
        <code class="rounded bg-white/60 px-1">Personal</code>, y un paciente es una
        <code class="rounded bg-white/60 px-1">Persona</code> sin legajo. Falta definir
        cómo se lo autentica.
    </x-alerta>

    <x-boton variante="fantasma" forma="grupo" icono="arrow_back"
             :href="route('cirugias.show', $caso->cirugia)" class="mb-4 px-3">
        Volver al expediente
    </x-boton>

    {{-- Cabecera --}}
    <div class="overflow-hidden rounded-2xl bg-hu-azul px-6 py-7 text-white">
        <p class="text-sm text-white/60">Tu cirugía</p>
        <p class="mt-1 text-2xl font-black">{{ $caso->procedimiento() }}</p>

        <div class="mt-4 flex flex-wrap gap-x-8 gap-y-3 text-sm">
            <div>
                <p class="text-white/60">Fecha</p>
                <p class="font-semibold">{{ $caso->cuando()?->translatedFormat('l j \d\e F') }}</p>
            </div>
            <div>
                <p class="text-white/60">Hora</p>
                <p class="font-semibold">
                    {{ $caso->cuando()?->format('H:i') }} hs
                    <span class="font-normal text-white/70">
                        · llegá {{ $caso->cuando()?->copy()->subMinutes(30)->format('H:i') }}
                    </span>
                </p>
            </div>
            @if ($caso->cirujano())
                <div>
                    <p class="text-white/60">Cirujano/a</p>
                    <p class="font-semibold">{{ $caso->cirujano() }}</p>
                </div>
            @endif
            @if ($caso->anestesista())
                <div>
                    <p class="text-white/60">Anestesista</p>
                    <p class="font-semibold">{{ $caso->anestesista() }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Qué te falta --}}
    <x-tarjeta titulo="Cómo va tu trámite" icono="check_circle" class="mt-6">
        @php
            $pasos = [
                ['Cobertura autorizada', $caso->autorizacionAprobada(),
                    'Lo gestiona el hospital con tu obra social.'],
                ['Estudios subidos', $caso->estudiosPendientes() === 0,
                    $caso->estudiosPendientes() > 0
                        ? 'Te faltan: '.$caso->nombresEstudiosPendientes()->join(', ')
                        : 'Ya están todos.'],
                ['Evaluación con el anestesista', $caso->evaluacionCompleta(),
                    'Es obligatoria para poder operarte.'],
                ['Consentimiento firmado', $caso->consentimientoFirmado(),
                    'Se firma antes de la cirugía.'],
            ];
        @endphp

        <ul class="divide-y divide-hu-gris-suave/60">
            @foreach ($pasos as [$titulo, $listo, $ayuda])
                <li class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <x-icono
                        :nombre="$listo ? 'check_circle' : 'pending'"
                        class="mt-0.5 shrink-0 text-xl {{ $listo ? 'text-emerald-600' : 'text-hu-dorado-oscuro' }}"
                        relleno
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-hu-azul">{{ $titulo }}</p>
                        <p class="text-xs text-hu-gris-medio">{{ $ayuda }}</p>
                    </div>
                    <x-estado :tono="$listo ? 'exito' : 'aviso'">
                        {{ $listo ? 'Listo' : 'Pendiente' }}
                    </x-estado>
                </li>
            @endforeach
        </ul>
    </x-tarjeta>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">

        {{-- Ayuno --}}
        @php($preparacion = $caso->preparacion())

        @if ($preparacion->isNotEmpty())
            <x-tarjeta titulo="Preparación para la cirugía" icono="no_food">
                <p class="mb-3 text-sm text-hu-gris-medio">
                    Contado hacia atrás desde las
                    <strong class="text-hu-azul">{{ $caso->cuando()?->format('H:i') }} hs</strong>
                    del {{ $caso->cuando()?->translatedFormat('l j/m') }}.
                </p>

                @foreach ($preparacion->groupBy('bloque') as $bloque => $items)
                    <p class="titulo-corto mt-3 text-xs text-hu-dorado-oscuro first:mt-0">{{ $bloque }}</p>

                    <ul class="mt-1 space-y-1.5">
                        @foreach ($items as $item)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span>{{ $item['indicacion'] }}</span>
                                @if ($item['horas'])
                                    <span class="shrink-0 text-right">
                                        <span class="font-semibold text-hu-azul">
                                            {{ $caso->cuando()?->copy()->subHours($item['horas'])->translatedFormat('D H:i') }} hs
                                        </span>
                                        <span class="block text-xs text-hu-gris-medio">
                                            {{ $item['horas'] }} hs antes
                                        </span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </x-tarjeta>
        @endif

        {{-- Estudios --}}
        <x-tarjeta titulo="Tus estudios" icono="science">
            @forelse ($caso->cirugia->cirugiaTipoEstudios as $estudio)
                <div class="flex items-center justify-between gap-3 border-b border-hu-gris-suave/60 py-2.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-hu-azul">
                            {{ $estudio->tipoEstudio?->nombreTipoEstudio }}
                        </p>
                        <p class="text-xs text-hu-gris-medio">
                            @if ($estudio->fechaSubidaCirugiaTipoEstudio)
                                Recibido el {{ $estudio->fechaSubidaCirugiaTipoEstudio->translatedFormat('j \d\e F') }}
                            @else
                                Subilo antes del
                                {{ $estudio->fechaEsperadaResultadoCirugiaTipoEstudio?->translatedFormat('j \d\e F') ?? 'día de la cirugía' }}
                            @endif
                        </p>
                    </div>

                    <x-estado :tono="$estudio->fechaSubidaCirugiaTipoEstudio ? 'exito' : 'aviso'">
                        {{ $estudio->fechaSubidaCirugiaTipoEstudio ? 'Recibido' : 'Falta' }}
                    </x-estado>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-hu-gris-medio">No te pidieron estudios.</p>
            @endforelse
        </x-tarjeta>
    </div>

    {{-- Cuestionario --}}
    @if ($caso->cuestionario()->isNotEmpty())
        <x-tarjeta titulo="Tu cuestionario pre-anestésico" icono="assignment" class="mt-6">
            <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                @foreach ($caso->cuestionario() as $fila)
                    <div class="border-b border-hu-gris-suave/60 pb-2">
                        <dt class="text-xs text-hu-gris-medio">{{ $fila['pregunta'] }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-hu-azul">{{ $fila['respuesta'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-tarjeta>
    @endif

    {{-- Consentimiento --}}
    @if ($consentimiento = $caso->consentimiento())
        <x-tarjeta titulo="Consentimiento informado" icono="draw" class="mt-6">
            <pre class="max-h-72 overflow-y-auto whitespace-pre-wrap rounded-xl bg-hu-gris-tenue px-4 py-3
                        font-sans text-sm leading-relaxed">{{ $consentimiento->textoRenderizadoConsentimiento }}</pre>

            @if ($caso->consentimientoFirmado())
                <p class="mt-3 flex items-center gap-2 text-sm text-emerald-700">
                    <x-icono nombre="check_circle" class="text-lg" relleno />
                    Firmado el {{ $consentimiento->fechaFirmaConsentimiento->translatedFormat('j \d\e F \d\e Y') }}
                </p>
            @else
                <p class="mt-3 text-sm text-hu-gris-medio">
                    Leelo completo. Vas a firmarlo antes de la cirugía.
                </p>
            @endif
        </x-tarjeta>
    @endif

@endsection
