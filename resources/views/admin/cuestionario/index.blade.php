@extends('layouts.app')

@section('titulo', 'Cuestionario preanestésico')
@section('subtitulo', 'Administración · Lo que completa el paciente antes de la evaluación')

@section('contenido')

    <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
        <form method="POST" action="{{ route('admin.cuestionario.store') }}">
            @csrf
            <x-boton icono="add">Publicar versión nueva</x-boton>
        </form>
    </div>

    <x-alerta tipo="info" class="mb-4">
        Publicar una versión cierra la vigente y copia sus preguntas para que las edites. Los
        cuestionarios ya respondidos siguen apuntando a la versión con la que se respondieron.
    </x-alerta>

    <x-tarjeta titulo="Versiones" icono="assignment">
        <x-slot:acciones>
            <x-estado tono="info">{{ $versiones->count() }}</x-estado>
        </x-slot:acciones>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-160 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Vigencia</th>
                        <th class="px-3 pb-2 font-semibold">Estado</th>
                        <th class="px-3 pb-2 text-right font-semibold">Preguntas</th>
                        <th class="px-3 pb-2 text-right font-semibold">Respondidos</th>
                        <th class="px-5 pb-2 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($versiones as $version)
                        @php
                            $vigente = $version->fechaFinVigeConfigTipoExamenPreAnestesico === null;
                            $respondidos = $version->examen_pre_anestesico_configes_count;
                        @endphp

                        <tr class="align-middle hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3 whitespace-nowrap tabular-nums">
                                {{ $version->fechaInicioVigeConfigTipoExamenPreAnestesico?->format('d/m/Y') ?? '—' }}
                                <span class="text-hu-gris-medio">→</span>
                                {{ $version->fechaFinVigeConfigTipoExamenPreAnestesico?->format('d/m/Y') ?? 'hoy' }}
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$vigente ? 'exito' : 'neutro'">
                                    {{ $vigente ? 'En uso' : 'Cerrada' }}
                                </x-estado>
                            </td>

                            <td class="px-3 py-3 text-right tabular-nums">
                                {{ $version->config_tipo_examen_pre_anestesico_preguntas_count }}
                            </td>

                            <td class="px-3 py-3 text-right tabular-nums">
                                {{ $respondidos === 0 ? '—' : $respondidos }}
                            </td>

                            <td class="px-5 py-3 text-right">
                                <x-boton
                                    :href="route('admin.cuestionario.show', $version)"
                                    variante="contorno"
                                    :icono="$vigente && $respondidos === 0 ? 'edit' : 'description'"
                                    forma="grupo"
                                >
                                    {{ $vigente && $respondidos === 0 ? 'Editar' : 'Ver' }}
                                </x-boton>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-hu-gris-medio">
                                Todavía no hay ningún cuestionario. Publicá el primero para empezar a cargar preguntas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tarjeta>

@endsection
