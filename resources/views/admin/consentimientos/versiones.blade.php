@extends('layouts.app')

@section('titulo', 'Consentimiento: '.$tipo->nombreTipoCirugia)
@section('subtitulo', 'Administración · Historial de versiones')

@section('contenido')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-boton :href="route('admin.consentimientos.index')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a consentimientos
        </x-boton>

        <x-boton :href="route('admin.consentimientos.create', $tipo)" icono="add">
            Nueva versión
        </x-boton>
    </div>

    @forelse ($versiones as $version)
        @php
            $vigente = $version->fechaFinConfigConsentimiento === null;
            $firmados = $version->consentimiento_pacientes_count;
        @endphp

        <x-tarjeta class="mb-4">
            <x-slot:titulo>
                {{ $vigente ? 'Versión vigente' : 'Versión anterior' }}
            </x-slot:titulo>

            <x-slot:acciones>
                <x-estado :tono="$vigente ? 'exito' : 'neutro'">
                    {{ $vigente ? 'En uso' : 'Cerrada' }}
                </x-estado>
            </x-slot:acciones>

            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-xs text-hu-gris-medio">
                <span>
                    Desde
                    <strong class="text-hu-gris">{{ $version->fechaInicioConfigConsentimiento?->format('d/m/Y H:i') ?? '—' }}</strong>
                </span>

                <span>
                    Hasta
                    <strong class="text-hu-gris">{{ $version->fechaFinConfigConsentimiento?->format('d/m/Y H:i') ?? 'hoy' }}</strong>
                </span>

                <span>
                    Firmados con esta versión
                    <strong class="text-hu-gris">{{ $firmados }}</strong>
                </span>
            </div>

            <pre class="mt-3 max-h-72 overflow-auto rounded-xl bg-hu-gris-tenue/40 p-4 font-sans text-sm
                        leading-relaxed whitespace-pre-wrap text-hu-gris">{{ $version->textoConfigConsentimiento }}</pre>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($firmados === 0)
                    <x-boton
                        :href="route('admin.consentimientos.edit', [$tipo, $version])"
                        variante="contorno"
                        icono="edit"
                        forma="grupo"
                    >
                        Corregir
                    </x-boton>
                @else
                    {{-- Con firmas encima, corregir haría mentir a la auditoría. --}}
                    <p class="text-xs text-hu-gris-medio">
                        Ya se usó para {{ $firmados }} {{ str('consentimiento')->plural($firmados) }}, así que no se
                        corrige: los cambios van en una versión nueva.
                    </p>
                @endif

                @if ($vigente)
                    <form
                        method="POST"
                        action="{{ route('admin.consentimientos.destroy', $tipo) }}"
                        data-confirmar-titulo="Retirar la plantilla de «{{ $tipo->nombreTipoCirugia }}»"
                        data-confirmar="Ese tipo de cirugía queda sin consentimiento hasta que publiques otra versión. Los ya firmados no se tocan."
                        data-confirmar-accion="Retirar"
                        class="ml-auto"
                    >
                        @csrf
                        @method('DELETE')
                        <x-boton variante="peligro" icono="cancel" forma="grupo">Retirar</x-boton>
                    </form>
                @endif
            </div>
        </x-tarjeta>
    @empty
        <x-tarjeta>
            <div class="py-8 text-center">
                <p class="text-hu-gris-medio">
                    Todavía no hay ninguna plantilla para {{ mb_strtolower($tipo->nombreTipoCirugia) }}.
                </p>
                <p class="mt-1 text-xs text-hu-gris-medio">
                    Sin plantilla no se le puede generar el consentimiento al paciente.
                </p>

                <x-boton :href="route('admin.consentimientos.create', $tipo)" icono="add" class="mt-4">
                    Publicar la primera
                </x-boton>
            </div>
        </x-tarjeta>
    @endforelse

@endsection
