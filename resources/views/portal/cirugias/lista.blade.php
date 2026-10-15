@extends('layouts.portal')

{{-- Proximas cirugias e historial: la misma lista, con distinto filtro. --}}

@section('titulo', $titulo)

@section('contenido')
    <h1 class="titulo-corto mb-4 text-lg text-hu-azul">{{ $titulo }}</h1>

    @if ($casos->isEmpty())
        <x-tarjeta>
            <div class="flex flex-col items-center gap-2 px-5 py-10 text-center">
                <x-icono nombre="event" class="text-4xl text-hu-gris-medio" />
                <p class="text-sm text-hu-gris-medio">{{ $vacio }}</p>
            </div>
        </x-tarjeta>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($casos as $caso)
                <li>
                    <a
                        href="{{ route('portal.cirugias.show', $caso->cirugia) }}"
                        class="flex items-center gap-4 rounded-2xl border border-hu-gris-suave/70 bg-white px-5 py-4
                               shadow-sm transition-colors hover:border-hu-dorado focus-visible:border-hu-dorado"
                    >
                        <div class="flex w-14 shrink-0 flex-col items-center rounded-xl bg-hu-azul-tenue py-2 text-hu-azul">
                            <span class="text-xs font-semibold uppercase">{{ $caso->cuando()?->translatedFormat('M') }}</span>
                            <span class="text-xl font-black leading-none">{{ $caso->cuando()?->format('j') }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-hu-azul">{{ $caso->procedimiento() }}</p>
                            <p class="text-sm text-hu-gris-medio">
                                {{ $caso->cuando()?->translatedFormat('l j \d\e F \d\e Y') }}
                                · {{ $caso->cuando()?->format('H:i') }} hs
                            </p>
                            @if ($caso->cirujano())
                                <p class="truncate text-xs text-hu-gris-medio">Cirujano/a: {{ $caso->cirujano() }}</p>
                            @endif
                            <x-portal.estado-cirugia :estado="$caso->estado()" class="mt-2" />
                        </div>

                        <x-icono nombre="chevron_right" class="shrink-0 text-2xl text-hu-gris-medio" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
