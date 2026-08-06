@extends('layouts.app')

@section('titulo', 'Historial completo de cirugías')
@section('subtitulo', ($personal->persona?->nombre_completo ?? 'Cirujano').' - '.($personal->matriculaProvincial ?? 'Sin matrícula'))

@section('contenido')
    {{-- Encabezado con botón de volver --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-hu-azul">Historial Completo de Cirugías</h1>
            <p class="mt-1 text-xs text-hu-gris-medio">
                {{ $personal->persona?->nombre_completo ?? 'Dr. Cirujano' }}
            </p>
        </div>
        <a href="{{ route('cirujano') }}" class="inline-flex items-center gap-2 rounded-xl border border-hu-gris-suave bg-white px-4 py-2 text-xs font-semibold text-hu-azul transition-colors hover:bg-gray-50 shadow-sm">
            <x-icono nombre="arrow_back" class="text-base" />
            Volver al panel
        </a>
    </div>

    {{-- Tarjeta contenedora con la tabla --}}
    <x-tarjeta titulo="Cirugías registradas" icono="history">
        @if($historial->isEmpty())
            <p class="py-10 text-center text-sm text-hu-gris-medio">
                No se encontraron cirugías en el historial.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-hu-azul">
                    <thead class="border-b border-hu-gris-suave/60 bg-gray-50/50 text-xs font-medium text-hu-gris-medio uppercase">
                        <tr>
                            <th scope="col" class="py-3 px-4">Fecha y Hora</th>
                            <th scope="col" class="py-3 px-4">Procedimiento</th>
                            <th scope="col" class="py-3 px-4">Quirófano</th>
                            <th scope="col" class="py-3 px-4">Paciente</th>
                            <th scope="col" class="py-3 px-4 text-right">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hu-gris-suave/60">
                        @foreach($historial as $cirugia)
                            @php
                                $estadoVal = $cirugia->estado();
                                $tonoEstado = match($estadoVal) {
                                    'Realizada' => 'exito',
                                    'Suspendida' => 'error',
                                    'Programada' => 'info',
                                    default => 'neutro',
                                };
                            @endphp
                            <tr class="relative align-middle transition-colors hover:bg-gray-50/50">
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs font-medium">
                                    <a href="{{ route('cirugias.show', $cirugia->cirugia) }}" class="absolute inset-0" aria-label="Ver cirugia de {{ $cirugia->nombrePaciente() }}"></a>
                                    {{ $cirugia->cuando()?->format('d/m/Y H:i') }} hs
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-hu-azul">
                                    {{ $cirugia->procedimiento() }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-hu-gris-medio">
                                    {{ $cirugia->quirofano?->nombreQuirofano ?? '-' }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs font-medium text-hu-gris-medio">
                                    {{ $cirugia->nombrePaciente() }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-right">
                                    <x-estado :tono="$tonoEstado">
                                        {{ $estadoVal }}
                                    </x-estado>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Links de Paginación --}}
            <div class="mt-6 border-t border-hu-gris-suave/60 pt-4">
                {{ $historial->links() }}
            </div>
        @endif
    </x-tarjeta>
@endsection