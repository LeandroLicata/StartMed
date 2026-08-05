@extends('layouts.app')

@section('titulo', 'Agenda')
@section('subtitulo', $referencia->translatedFormat('F \d\e Y'))

@section('contenido')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
                <a
                href="{{ route('cirujano.agenda') }}"
                class="rounded-full border border-hu-gris-suave px-4 py-1.5 text-sm font-semibold text-hu-azul transition-colors hover:bg-hu-azul-tenue"
            >
                Hoy
            </a>
            <div class="flex items-center gap-1">
                    <a
                    href="{{ route('cirujano.agenda', ['mes' => $mesAnterior]) }}"
                    class="flex size-9 items-center justify-center rounded-full text-hu-azul transition-colors hover:bg-hu-azul-tenue"
                    aria-label="Mes anterior"
                >
                    <x-icono nombre="arrow_back" class="text-lg" />
                </a>
                    <a
                    href="{{ route('cirujano.agenda', ['mes' => $mesSiguiente]) }}"
                    class="flex size-9 items-center justify-center rounded-full text-hu-azul transition-colors hover:bg-hu-azul-tenue"
                    aria-label="Mes siguiente"
                >
                    <x-icono nombre="arrow_back" class="rotate-180 text-lg" />
                </a>
            </div>
            <h2 class="text-lg font-semibold capitalize text-hu-azul">
                {{ $referencia->translatedFormat('F \d\e Y') }}
            </h2>
        </div>
        @include('paneles.cirujano-agenda-toggle', ['vista' => 'mes'])
    </div>

    <x-tarjeta icono="event">
        <div class="grid grid-cols-7 gap-1 pb-2 text-center text-xs font-semibold uppercase tracking-wide text-hu-gris-medio">
            <div>Lun</div>
            <div>Mar</div>
            <div>Mie</div>
            <div>Jue</div>
            <div>Vie</div>
            <div>Sab</div>
            <div>Dom</div>
        </div>
        <div class="space-y-1">
            @foreach ($semanas as $semana)
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($semana as $dia)
                            <a
                            href="{{ route('cirujano.agenda.dia', $dia['fecha']->toDateString()) }}"
                            class="flex min-h-20 flex-col items-start gap-1.5 rounded-xl border p-2 transition-colors
                                {{ $dia['fecha']->isToday() ? 'border-hu-dorado bg-hu-dorado-tenue' : 'border-hu-gris-suave/60 hover:bg-hu-azul-tenue/40' }}
                                {{ $dia['delMes'] ? '' : 'opacity-40' }}"
                        >
                            <span class="text-sm font-semibold text-hu-azul">{{ $dia['fecha']->day }}</span>
                            @if ($dia['cantidad'] > 0)
                                <span class="flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold
                                    {{ $dia['enRiesgo'] > 0 ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}"
                                >
                                    {{ $dia['cantidad'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-tarjeta>

@endsection
