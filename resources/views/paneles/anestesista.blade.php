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

    <x-tarjeta titulo="Cirugías asignadas" icono="stethoscope" class="mt-6">
        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-208 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Paciente</th>
                        <th class="px-3 pb-2 font-semibold">Procedimiento</th>
                        <th class="px-3 pb-2 font-semibold">Fecha</th>
                        <th class="px-3 pb-2 font-semibold">ASA</th>
                        <th class="px-3 pb-2 font-semibold">Anestesia</th>
                        <th class="px-3 pb-2 font-semibold">Cuestionario</th>
                        <th class="px-5 pb-2 font-semibold">Evaluación</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($evaluaciones as $caso)
                        <tr class="hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3">
                                <a href="{{ route('cirugias.show', $caso->cirugia) }}"
                                   class="font-semibold text-hu-azul hover:underline">
                                    {{ $caso->nombrePaciente() }}
                                </a>
                                @if ($caso->alertaProfilaxis())
                                    <p class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-red-700">
                                        <x-icono nombre="warning" class="text-sm" relleno />
                                        Alergia documentada
                                    </p>
                                @endif
                            </td>

                            <td class="px-3 py-3">{{ $caso->procedimiento() }}</td>

                            <td class="px-3 py-3 whitespace-nowrap">
                                {{ $caso->cuando()?->translatedFormat('D j/m') }}
                                <span class="text-hu-gris-medio">{{ $caso->cuando()?->format('H:i') }}</span>
                            </td>

                            <td class="px-3 py-3">
                                @if ($caso->asa())
                                    <x-estado :tono="in_array($caso->asa(), ['ASA III', 'ASA IV', 'ASA V']) ? 'aviso' : 'info'">
                                        {{ $caso->asa() }}
                                    </x-estado>
                                @else
                                    <span class="text-hu-gris-medio">—</span>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-hu-gris">
                                {{ $caso->tipoAnestesia() ?? '—' }}
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$caso->cuestionario()->isNotEmpty() ? 'exito' : 'neutro'">
                                    {{ $caso->cuestionario()->isNotEmpty() ? 'Recibido' : 'Sin datos' }}
                                </x-estado>
                            </td>

                            <td class="px-5 py-3">
                                <x-estado
                                    :tono="$caso->evaluacionCompleta() ? 'exito' : 'aviso'"
                                    :icono="$caso->evaluacionCompleta() ? 'check_circle' : 'pending'"
                                >
                                    {{ $caso->evaluacion() }}
                                </x-estado>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-hu-gris-medio">
                                No tenés cirugías asignadas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tarjeta>

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
