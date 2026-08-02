@extends('layouts.app')

@section('titulo', 'Consentimientos informados')
@section('subtitulo', 'Administración · Una plantilla por tipo de cirugía')

@section('contenido')

    <div class="mb-4">
        <x-boton :href="route('admin.inicio')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a administración
        </x-boton>
    </div>

    <x-tarjeta titulo="Plantillas por procedimiento" icono="draw">
        <x-slot:acciones>
            <x-estado tono="info">{{ $tipos->count() }}</x-estado>
        </x-slot:acciones>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-208 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Tipo de cirugía</th>
                        <th class="px-3 pb-2 font-semibold">Plantilla vigente</th>
                        <th class="px-3 pb-2 font-semibold">Desde</th>
                        <th class="px-3 pb-2 text-right font-semibold">Versiones</th>
                        <th class="px-5 pb-2 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($tipos as $tipo)
                        @php($vigente = $tipo->configConsentimientos->first())

                        <tr class="align-top hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3 font-semibold text-hu-azul">
                                {{ $tipo->nombreTipoCirugia }}
                            </td>

                            <td class="px-3 py-3">
                                @if ($vigente)
                                    <x-estado tono="exito" icono="check_circle">Publicada</x-estado>
                                @else
                                    {{-- Sin plantilla no se le puede dar un consentimiento al paciente. --}}
                                    <x-estado tono="error" icono="warning">Sin plantilla</x-estado>
                                @endif
                            </td>

                            <td class="px-3 py-3 whitespace-nowrap tabular-nums text-hu-gris-medio">
                                {{ $vigente?->fechaInicioConfigConsentimiento?->format('d/m/Y') ?? '—' }}
                            </td>

                            <td class="px-3 py-3 text-right tabular-nums">
                                {{ $tipo->config_consentimientos_count }}
                            </td>

                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <x-boton
                                        :href="route('admin.consentimientos.show', $tipo)"
                                        variante="fantasma"
                                        icono="description"
                                        forma="grupo"
                                        class="px-3"
                                    >
                                        Ver versiones
                                    </x-boton>

                                    <x-boton
                                        :href="route('admin.consentimientos.create', $tipo)"
                                        :variante="$vigente ? 'contorno' : 'primario'"
                                        icono="add"
                                        forma="grupo"
                                    >
                                        {{ $vigente ? 'Nueva versión' : 'Publicar' }}
                                    </x-boton>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-hu-gris-medio">
                                No hay tipos de cirugía activos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tarjeta>

@endsection
