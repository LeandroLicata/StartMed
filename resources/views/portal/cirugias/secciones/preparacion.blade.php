{{--
    Pestaña "preparacion" del detalle. A cargo de: Jhonni. Recibe $caso y $soloLectura.

    Solo lectura: el paciente consulta las indicaciones cargadas por el equipo
    medico y quirurgico. Las horas de ingesta calculan hacia atras desde la hora
    de la cirugia.
--}}

@php
    $preparacion = $caso->preparacion();
    $recordatorio = $caso->recordatorioPreparacion();
@endphp

<x-tarjeta titulo="Indicaciones de preparación" icono="no_food">
    @if ($preparacion->isEmpty())
        <p class="py-6 text-center text-sm text-hu-gris-medio">
            Tu equipo todavía no cargó las indicaciones.
        </p>
    @else
        <div class="space-y-6">
            @foreach ($preparacion->groupBy('bloque') as $bloque => $items)
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-hu-dorado-oscuro">
                        {{ $bloque }}
                    </h3>

                    <ul class="divide-y divide-hu-gris-suave/70 rounded-xl border border-hu-gris-suave/70 bg-hu-gris-tenue/30 px-4">
                        @foreach ($items as $item)
                            <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-hu-azul">
                                        {{ $item['indicacion'] }}
                                    </p>
                                    @if ($item['horas'] !== null)
                                        <p class="text-xs text-hu-gris-medio">
                                            Hasta {{ $item['horas'] }} {{ str('hora')->plural($item['horas']) }} antes de la cirugía
                                        </p>
                                    @endif
                                </div>

                                @if ($item['ultimaIngesta'])
                                    <div class="text-left sm:text-right">
                                        <span class="block text-xs text-hu-gris-medio">Última ingesta</span>
                                        <span class="text-sm font-semibold text-hu-azul">
                                            {{ ucfirst($item['ultimaIngesta']->translatedFormat('l j \d\e F \d\e Y, H:i')) }} hs
                                        </span>
                                    </div>
                                @elseif ($item['horas'] === null)
                                    <div class="text-left sm:text-right">
                                        <span class="inline-block rounded-md bg-hu-gris-suave/40 px-2 py-0.5 text-xs text-hu-gris-medio">
                                            Sin horario definido
                                        </span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            @if (filled($recordatorio))
                <div class="rounded-xl border border-hu-dorado/30 bg-hu-dorado-tenue/40 p-4">
                    <div class="flex items-start gap-2.5">
                        <x-icono nombre="notifications" class="mt-0.5 shrink-0 text-lg text-hu-dorado-oscuro" />
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-hu-dorado-oscuro">
                                Recordatorio
                            </h4>
                            <p class="mt-1 text-sm text-hu-azul">
                                {{ $recordatorio }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-tarjeta>
