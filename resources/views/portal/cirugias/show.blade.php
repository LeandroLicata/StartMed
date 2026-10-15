@extends('layouts.portal')

{{--
    Detalle de una cirugia del paciente: encabezado comun y una pestaña por
    modulo. Cada pestaña es un parcial en portal/cirugias/secciones y recibe
    $caso (ResumenCirugia con el expediente cargado) y $soloLectura (true en
    una cirugia pasada: se muestra, pero no se firma, sube ni responde nada).
--}}

@section('titulo', $caso->procedimiento())

@section('contenido')
    <a
        href="{{ $soloLectura ? route('portal.historial') : route('portal.inicio') }}"
        class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-hu-azul hover:text-hu-azul-claro"
    >
        <x-icono nombre="arrow_back" class="text-lg" />
        {{ $soloLectura ? 'Historial' : 'Próximas cirugías' }}
    </a>

    {{-- Encabezado --}}
    <div class="overflow-hidden rounded-2xl border-b-4 border-hu-dorado bg-hu-azul px-5 py-6 text-white sm:px-7">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-white/60">{{ $soloLectura ? 'Cirugía anterior' : 'Tu cirugía' }}</p>
                <h1 class="mt-1 text-xl font-black sm:text-2xl">{{ $caso->procedimiento() }}</h1>
            </div>
            <x-portal.estado-cirugia :estado="$caso->estado()" />
        </div>

        <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-3 text-sm">
            <div>
                <dt class="text-white/60">Fecha</dt>
                <dd class="font-semibold">{{ $caso->cuando()?->translatedFormat('l j \d\e F \d\e Y') }}</dd>
            </div>
            <div>
                <dt class="text-white/60">Hora</dt>
                <dd class="font-semibold">{{ $caso->cuando()?->format('H:i') }} hs</dd>
            </div>
            @if ($caso->cirujano())
                <div>
                    <dt class="text-white/60">Cirujano/a</dt>
                    <dd class="font-semibold">{{ $caso->cirujano() }}</dd>
                </div>
            @endif
            @if ($caso->anestesista())
                <div>
                    <dt class="text-white/60">Anestesista</dt>
                    <dd class="font-semibold">{{ $caso->anestesista() }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if ($soloLectura)
        <x-alerta tipo="info" class="mt-4">
            Es una cirugía anterior: podés consultar todo, pero ya no se puede modificar.
        </x-alerta>
    @endif

    {{-- Una sola fila de pestañas; en pantallas angostas se desplaza en vez de cortarse. --}}
    <nav class="-mx-4 mt-4 overflow-x-auto px-4 [scrollbar-width:thin] sm:mx-0 sm:px-0" aria-label="Secciones de la cirugía">
        <ul class="flex gap-2 whitespace-nowrap pb-1">
            @foreach ($secciones as $clave => [$etiqueta, $icono])
                @php($activa = $seccion === $clave)
                <li>
                    <a
                        href="{{ route('portal.cirugias.show', [$caso->cirugia, $clave]) }}"
                        @class([
                            'inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition-colors',
                            'border-hu-azul bg-hu-azul text-white' => $activa,
                            'border-hu-gris-suave bg-white text-hu-azul hover:bg-hu-azul-tenue' => ! $activa,
                        ])
                        @if ($activa) aria-current="page" @endif
                    >
                        <x-icono :nombre="$icono" class="text-lg" :relleno="$activa" />
                        {{ $etiqueta }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <section class="mt-4">
        @include('portal.cirugias.secciones.'.$seccion)
    </section>
@endsection
