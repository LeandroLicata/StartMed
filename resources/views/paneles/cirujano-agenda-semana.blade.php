@extends('layouts.app')

@section('titulo', 'Agenda')
@section('subtitulo', $inicio->translatedFormat('j M').' - '.$fin->translatedFormat('j M Y'))

@section('contenido')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
                <a
                href="{{ route('cirujano.agenda', ['vista' => 'semana']) }}"
                class="rounded-full border border-hu-gris-suave px-4 py-1.5 text-sm font-semibold text-hu-azul transition-colors hover:bg-hu-azul-tenue"
            >
                Hoy
            </a>

            <div class="flex items-center gap-1">
                    <a
                    href="{{ route('cirujano.agenda', ['vista' => 'semana', 'semana' => $semanaAnterior]) }}"
                    class="flex size-9 items-center justify-center rounded-full text-hu-azul transition-colors hover:bg-hu-azul-tenue"
                    aria-label="Semana anterior"
                >
                    <x-icono nombre="arrow_back" class="text-lg" />
                </a>
                    <a
                    href="{{ route('cirujano.agenda', ['vista' => 'semana', 'semana' => $semanaSiguiente]) }}"
                    class="flex size-9 items-center justify-center rounded-full text-hu-azul transition-colors hover:bg-hu-azul-tenue"
                    aria-label="Semana siguiente"
                >
                    <x-icono nombre="arrow_back" class="rotate-180 text-lg" />
                </a>
            </div>

            <h2 class="text-lg font-semibold text-hu-azul">
                {{ $inicio->translatedFormat('j \d\e M') }} - {{ $fin->translatedFormat('j \d\e M \d\e Y') }}
            </h2>
        </div>

        @include('paneles.cirujano-agenda-toggle', ['vista' => 'semana'])
    </div>

    <div class="flex flex-col bg-white rounded-2xl shadow-sm border border-hu-gris-suave/70 overflow-hidden" style="height: calc(100vh - 200px); min-height: 600px;">

        <div class="flex border-b border-hu-gris-suave/70 bg-white z-20">
            <div class="w-16 shrink-0 border-r border-hu-gris-suave/70">
                <div class="h-full flex items-end justify-center pb-2 text-[10px] text-hu-gris-medio">GMT-03</div>
            </div>

            <div class="flex-1 grid grid-cols-7 divide-x divide-hu-gris-suave/70">
                @foreach ($dias as $dia)
                    <div class="py-3 text-center {{ $dia['fecha']->isToday() ? 'bg-hu-azul/5' : '' }}">
                        <div class="text-[11px] font-semibold uppercase tracking-wider {{ $dia['fecha']->isToday() ? 'text-hu-azul' : 'text-hu-gris-medio' }}">
                            {{ $dia['fecha']->translatedFormat('D') }}
                        </div>
                        <div class="mt-1 flex justify-center">
                            <div class="text-2xl font-normal w-10 h-10 flex items-center justify-center rounded-full transition-colors {{ $dia['fecha']->isToday() ? 'bg-hu-azul text-white font-medium' : 'text-hu-azul' }}">
                                {{ $dia['fecha']->format('j') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex-1 overflow-y-auto relative scroll-smooth bg-white" id="calendar-body">
            <div class="flex relative" style="height: 1440px;">

                <div class="w-16 shrink-0 border-r border-hu-gris-suave/70 bg-white z-10 relative">
                    @for ($i = 0; $i < 24; $i++)
                        @if ($i > 0)
                            <div class="absolute w-full text-right pr-2 text-xs text-hu-gris-medio font-medium" style="top: {{ $i * 60 }}px; margin-top: -8px;">
                                {{ $i < 12 ? $i . ' AM' : ($i == 12 ? '12 PM' : ($i - 12) . ' PM') }}
                            </div>
                        @endif
                    @endfor
                </div>

                <div class="flex-1 grid grid-cols-7 divide-x divide-hu-gris-suave/70 relative">
                    <div class="absolute inset-0 pointer-events-none flex flex-col z-0">
                        @for ($i = 0; $i < 24; $i++)
                            <div class="border-b border-hu-gris-suave/30 w-full" style="height: 60px;"></div>
                        @endfor
                    </div>

                    @php
                        $now = \Carbon\Carbon::now('America/Argentina/Buenos_Aires');
                        $nowMinutes = ($now->hour * 60) + $now->minute;
                    @endphp

                    @foreach ($dias as $dia)
                        <div class="relative w-full h-full z-10 {{ $dia['fecha']->isToday() ? 'bg-hu-azul/[0.02]' : '' }}">

                            @if ($dia['fecha']->isToday())
                                <div class="absolute w-full flex items-center z-30 pointer-events-none" style="top: {{ $nowMinutes }}px; left: 0;">
                                    <div class="w-2.5 h-2.5 rounded-full bg-red-500 -ml-1.5 shadow-sm"></div>
                                    <div class="h-0.5 bg-red-500 w-full shadow-sm"></div>
                                </div>
                            @endif

                            @foreach ($dia['cirugias'] as $caso)
                                @php
                                    $top = $caso->minutosDesdeMedianoche();
                                    $height = $caso->duracionEnMinutos();
                                    $width = 100 / $caso->overlapTotal;
                                    $left = $width * $caso->overlapCol;

                                    $isReady = $caso->estaLista();
                                    $bgClass = $isReady ? 'bg-emerald-50 border-emerald-200 hover:bg-emerald-100' : 'bg-red-50 border-red-200 hover:bg-red-100';
                                    $textClass = $isReady ? 'text-emerald-900' : 'text-red-900';
                                    $borderLeftClass = $isReady ? 'border-l-emerald-500' : 'border-l-red-500';
                                @endphp

                                <a href="{{ route('cirugias.show', $caso->cirugia) }}"
                                   class="absolute rounded-md border {{ $bgClass }} {{ $textClass }} border-l-4 {{ $borderLeftClass }} p-1.5 overflow-hidden transition-all hover:z-40 hover:shadow-md flex flex-col gap-0.5"
                                   style="top: {{ $top }}px; height: {{ $height }}px; left: {{ $left }}%; width: calc({{ $width }}% - 2px); z-index: {{ 10 + $caso->overlapCol }}; margin-left: 1px;"
                                   title="{{ $caso->procedimiento() }} - {{ $caso->nombrePaciente() }}"
                                >
                                    <div class="text-xs font-semibold truncate leading-tight">{{ $caso->procedimiento() }}</div>
                                    <div class="text-[10px] truncate opacity-90 leading-tight">
                                        {{ $caso->cuando()->format('H:i') }} - {{ $caso->nombrePaciente() }}
                                    </div>

                                    @if (!$isReady)
                                        <div class="mt-auto text-[10px] font-semibold flex items-center gap-1 text-red-600">
                                            <x-icono nombre="warning" class="text-[11px]" relleno />
                                            <span class="truncate">Faltan items</span>
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('calendar-body');
            @if($dias->contains(fn($dia) => $dia['fecha']->isToday()))
                const nowMinutes = {{ $nowMinutes }};
                container.scrollTop = Math.max(0, nowMinutes - (container.clientHeight / 2));
            @else
                container.scrollTop = 8 * 60 - 20;
            @endif
        });
    </script>

@endsection
