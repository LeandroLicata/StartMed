@extends('layouts.paciente')

@section('titulo', 'Mi cirugía')

@section('contenido')
@php($cirugia = $portal['cirugia'])
<header class="rounded-lg border-b-4 border-hu-dorado bg-hu-azul px-5 py-5 text-white shadow-sm sm:px-7">
    <div class="flex flex-wrap items-center gap-4">
        <a href="{{ route('paciente.portal') }}" class="hidden border-r border-white/20 pr-5 md:block" aria-label="Inicio del portal">
            <img src="{{ asset('img/logo-hu-blanco.svg') }}" alt="Hospital Universitario" class="h-9 w-auto">
        </a>
        <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg font-black">{{ $portal['paciente']['iniciales'] }}</div>
        <div class="min-w-0 flex-1">
            <h1 class="text-lg font-black sm:text-xl">{{ $portal['paciente']['nombre'] }}</h1>
            <p class="mt-0.5 text-xs text-white/85 sm:text-sm">{{ $cirugia['procedimiento'] }} · {{ $cirugia['fecha'] }} · {{ $cirugia['hora'] }} hs</p>
            <span class="mt-2 inline-flex items-center gap-1 rounded-full border border-white/40 bg-white/15 px-3 py-1 text-xs font-semibold">
                <x-icono nombre="check_circle" class="text-sm" /> Cirugía confirmada · OSDE autorizada
            </span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="https://wa.me/5492615550100" class="inline-flex items-center gap-1 rounded-full border border-white/40 bg-white/10 px-3 py-2 text-xs font-semibold hover:bg-white/20">
                <x-icono nombre="call" class="text-base" /> WhatsApp
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="flex size-9 items-center justify-center rounded-full border border-white/40 hover:bg-white/20" title="Cerrar sesión"><x-icono nombre="logout" /></button>
            </form>
        </div>
    </div>
</header>

@unless(($portal['estado']['confirmar'] ?? false) || ($portal['estado']['reprogramar'] ?? false))
<section class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-5 py-5 sm:px-7">
    <div class="flex items-start gap-3">
        <x-icono nombre="notifications" class="mt-0.5 text-2xl text-amber-600" />
        <div class="min-w-0 flex-1">
            <h2 class="font-bold text-amber-900">Confirmación de asistencia: tu cirugía es en 48 horas</h2>
            <p class="mt-1 text-xs text-amber-900/80">¿Vas a poder concurrir el lunes 16 de junio a las 07:30? Si surgió algún inconveniente, avisanos ahora.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('paciente.accion', 'confirmar') }}">@csrf
                    <button class="rounded-lg bg-hu-azul px-5 py-3 text-sm font-bold text-white hover:bg-hu-azul-claro">Sí, voy a estar</button>
                </form>
                <form method="POST" action="{{ route('paciente.accion', 'reprogramar') }}">@csrf
                    <button class="rounded-lg border border-hu-gris-suave bg-white px-5 py-3 text-sm font-bold text-hu-azul hover:bg-hu-gris-tenue">Necesito reprogramar</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endunless

<nav class="mt-4 grid grid-cols-3 overflow-hidden rounded-lg border border-hu-gris-suave bg-white sm:grid-cols-6" aria-label="Progreso de la cirugía">
    @foreach($portal['pasos'] as [$clave, $etiqueta, $icono, $listo])
        <a href="{{ route('paciente.portal', ['seccion' => $clave]) }}" class="flex min-h-16 flex-col items-center justify-center gap-1 border-r border-b border-hu-gris-suave px-2 py-2 text-center text-[11px] font-semibold sm:border-b-0 {{ $seccion === $clave ? 'bg-hu-azul text-white shadow-[inset_0_-4px_0_#C7A36E]' : 'text-hu-azul hover:bg-hu-azul-tenue' }}">
            <x-icono :nombre="$listo ? 'check' : $icono" class="text-base" /> {{ $etiqueta }}
        </a>
    @endforeach
</nav>

<nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Secciones">
    @foreach([...$portal['pasos'], ['contacto', 'Contacto', 'call', true]] as [$clave, $etiqueta, $icono, $listo])
        <a href="{{ route('paciente.portal', ['seccion' => $clave]) }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-xs font-semibold shadow-sm {{ $seccion === $clave ? 'border-hu-azul bg-hu-azul text-white' : 'border-hu-gris-suave bg-white text-hu-azul hover:bg-hu-azul-tenue' }}">
            <x-icono :nombre="$icono" class="text-base" /> {{ $etiqueta }}
            @if(!$listo)<span class="flex size-4 items-center justify-center rounded-full bg-amber-100 text-[10px] text-amber-800">!</span>@endif
        </a>
    @endforeach
</nav>

<section class="mt-4">
    @include('paciente.secciones.'.$seccion)
</section>
@endsection
