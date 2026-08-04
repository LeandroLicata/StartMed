@extends('layouts.app')

@section('titulo', 'Pacientes')
@section('subtitulo', 'Padrón de todas las personas registradas')

@section('contenido')
    <x-tarjeta titulo="Búsqueda de Pacientes" icono="search" class="mb-6">
        <form method="GET" action="{{ route('pacientes.index') }}" class="grid gap-4 md:grid-cols-5 items-end">
            <div class="md:col-span-2">
                <x-input
                    nombre="buscar"
                    etiqueta="Buscar por Nombre, Apellido o DNI"
                    :valor="request('buscar')"
                    placeholder="Ej: Perez, Juan o 30123456"
                />
            </div>
            
            <div>
                <x-select
                    nombre="genero"
                    etiqueta="Género"
                    :opciones="['M' => 'Masculino', 'F' => 'Femenino', 'X' => 'Otro']"
                    :valor="request('genero')"
                    placeholder="Todos"
                />
            </div>

            <div>
                <x-select
                    nombre="es_cronico"
                    etiqueta="Crónico"
                    :opciones="['1' => 'Sí', '0' => 'No']"
                    :valor="request('es_cronico')"
                    placeholder="Todos"
                />
            </div>

            <div>
                <x-boton tipo="submit" icono="search" class="w-full">Buscar</x-boton>
            </div>
        </form>
    </x-tarjeta>

    <x-tarjeta titulo="Resultados" icono="groups">
        <x-slot:acciones>
            <x-estado tono="info">{{ $pacientes->total() }} pacientes</x-estado>
        </x-slot:acciones>

        <div class="overflow-x-auto -mx-5 px-5 pt-3 pb-5">
            <table class="w-full min-w-[800px] text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Paciente</th>
                        <th class="px-3 pb-2 font-semibold">Documento</th>
                        <th class="px-3 pb-2 font-semibold">Edad</th>
                        <th class="px-3 pb-2 font-semibold">Datos de contacto</th>
                        <th class="px-5 pb-2 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($pacientes as $paciente)
                        <tr class="hover:bg-hu-gris-tenue/30 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-hu-azul/10 text-hu-azul font-bold text-xs uppercase">
                                        {{ substr($paciente->nombres, 0, 1) }}{{ substr($paciente->apellidos, 0, 1) }}
                                    </span>
                                    <div>
                                        <p class="font-bold text-hu-azul">{{ $paciente->apellidos }}, {{ $paciente->nombres }}</p>
                                        @if ($paciente->es_cronico)
                                            <span class="text-[10px] uppercase font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded-sm">Crónico</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <span class="text-hu-gris-medio">{{ $paciente->tipoDocumento?->nombreTipoDocumento ?? 'DNI' }}</span><br>
                                <span class="font-semibold">{{ $paciente->documento }}</span>
                            </td>
                            <td class="px-3 py-3">
                                @if($paciente->fecha_nacimiento)
                                    {{ \Carbon\Carbon::parse($paciente->fecha_nacimiento)->age }} años
                                @elseif($paciente->usar_edad_aproximada && $paciente->fecha_edad_aproximada)
                                    Aprox. {{ \Carbon\Carbon::parse($paciente->fecha_edad_aproximada)->age }} años
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if($paciente->telefono_numero)
                                    <div class="flex items-center gap-1"><x-icono nombre="call" class="text-xs text-hu-gris-medio" /> {{ $paciente->telefono_numero }}</div>
                                @endif
                                @if($paciente->contacto_email_direccion)
                                    <div class="flex items-center gap-1 mt-0.5"><x-icono nombre="mail" class="text-xs text-hu-gris-medio" /> {{ $paciente->contacto_email_direccion }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-boton :href="route('pacientes.show', $paciente)" variante="contorno" class="!px-3 !py-1.5 !text-xs">
                                    Ver historial
                                </x-boton>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-hu-gris-medio">
                                <x-icono nombre="search_off" class="text-4xl mb-2 opacity-50" />
                                <p>No se encontraron pacientes que coincidan con la búsqueda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pacientes->hasPages())
            <div class="border-t border-hu-gris-suave/60 bg-hu-gris-tenue/30 px-5 py-4">
                {{ $pacientes->links() }}
            </div>
        @endif
    </x-tarjeta>
@endsection
