@extends('layouts.app')

@section('titulo', 'Agenda')
@section('subtitulo', $personal->persona?->nombre_completo.' · '.$mes->translatedFormat('F Y'))

@section('contenido')

    <div class="mb-4 flex justify-end">
        <x-boton variante="fantasma" forma="grupo" icono="home" :href="route('cirujano')" class="px-3">
            Volver a Mis cirugías
        </x-boton>
    </div>

    <div class="mb-6 flex items-center justify-between gap-3">
        <x-boton
            variante="fantasma"
            forma="grupo"
            icono="arrow_back"
            :href="route('cirujano.agenda', ['mes' => $mesAnterior])"
            class="px-3"
        >
            Mes anterior
        </x-boton>

        <p class="titulo-corto text-base text-hu-azul">
            {{ $mes->translatedFormat('F Y') }}
        </p>
        <x-boton
            variante="fantasma"
            forma="grupo"
            :href="route('cirujano.agenda', ['mes' => $mesSiguiente])"
            class="px-3"
        >
            Mes siguiente
            <x-icono nombre="arrow_forward" class="text-lg" />
        </x-boton>
    </div>

    <div class="overflow-hidden rounded-2xl border border-hu-gris-suave/70 bg-white">

        <div class="grid grid-cols-7 border-b border-hu-gris-suave/70 bg-hu-gris-tenue">
            @foreach (['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $dia)
                <div class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-hu-gris-medio">
                    {{ $dia }}
                </div>
            @endforeach
        </div>

        @foreach ($semanas as $semana)
            <div class="grid grid-cols-7 border-b border-hu-gris-suave/60 last:border-0">
                @foreach ($semana as $dia)
                    <div class="min-h-28 border-r border-hu-gris-suave/60 p-1.5 last:border-0 {{ ! $dia['esDelMes'] ? 'bg-hu-gris-tenue/40' : '' }}">
                        <p class="mb-1 inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold {{ $dia['esHoy'] ? 'bg-hu-azul text-white' : ($dia['esDelMes'] ? 'text-hu-azul' : 'text-hu-gris-medio/60') }}">
                            {{ $dia['fecha']->day }}
                        </p>

                        <div class="space-y-1">
                            @foreach ($dia['cirugias']->take(3) as $caso)
                                <a href="{{ route('cirugias.show', $caso->cirugia) }}" class="block truncate rounded-md px-1.5 py-1 text-[11px] font-medium transition-colors {{ match ($caso->semaforo()) { 'exito' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100', 'error' => 'bg-red-50 text-red-700 hover:bg-red-100', default => 'bg-hu-dorado-tenue text-hu-dorado-oscuro hover:bg-hu-dorado-tenue/70' } }}" title="{{ $caso->nombrePaciente() }}">
                                    {{ $caso->cuando()?->format('H:i') }} {{ $caso->nombrePaciente() }}
                                </a>
                            @endforeach

                            @if ($dia['cirugias']->count() > 3)
                                <p class="px-1.5 text-[11px] text-hu-gris-medio">
                                    +{{ $dia['cirugias']->count() - 3 }} mas
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

    </div>

@endsection