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

        <div class="grid gap-4 pt-3 pb-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($pacientes as $paciente)
                <x-tarjeta class="!p-0 overflow-hidden">
                    <div class="flex h-full flex-col">
                        <div class="flex items-start gap-4 border-b border-hu-gris-suave/60 p-5 pb-4">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-hu-azul/10 text-hu-azul font-black text-base uppercase">
                                {{ substr($paciente->nombres, 0, 1) }}{{ substr($paciente->apellidos, 0, 1) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold text-hu-azul">{{ $paciente->apellidos }}, {{ $paciente->nombres }}</p>
                                <p class="mt-0.5 flex items-center gap-1 text-xs text-hu-gris-medio">
                                    <x-icono nombre="badge" class="text-sm" />
                                    {{ $paciente->tipoDocumento?->nombreTipoDocumento ?? 'DNI' }} {{ $paciente->documento }}
                                </p>
                                @if ($paciente->es_cronico)
                                    <span class="mt-2 inline-flex items-center gap-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-bold uppercase text-red-500">
                                        <x-icono nombre="warning" class="text-xs" /> Crónico
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-2.5 p-5 pt-4 text-sm">
                            <p class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5 text-hu-gris-medio">
                                    <x-icono nombre="event" class="text-base" /> Edad
                                </span>
                                <span class="font-semibold text-hu-azul">
                                    @if($paciente->fecha_nacimiento)
                                        {{ \Carbon\Carbon::parse($paciente->fecha_nacimiento)->age }} años
                                    @elseif($paciente->usar_edad_aproximada && $paciente->fecha_edad_aproximada)
                                        Aprox. {{ \Carbon\Carbon::parse($paciente->fecha_edad_aproximada)->age }} años
                                    @else
                                        -
                                    @endif
                                </span>
                            </p>

                            <p class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5 text-hu-gris-medio">
                                    <x-icono nombre="call" class="text-base" /> Teléfono
                                </span>
                                <span class="break-all text-right font-semibold text-hu-azul">
                                    {{ $paciente->telefono_numero ?? '—' }}
                                </span>
                            </p>

                            <p class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5 text-hu-gris-medio">
                                    <x-icono nombre="mail" class="text-base" /> Correo
                                </span>
                                <span class="break-all text-right font-semibold text-hu-azul">
                                    {{ $paciente->contacto_email_direccion ?? '—' }}
                                </span>
                            </p>
                        </div>

                        <div class="flex gap-2 border-t border-hu-gris-suave/60 p-4">
                            <x-boton
                                variante="contorno"
                                forma="grupo"
                                icono="folder_open"
                                :href="route('pacientes.show', $paciente)"
                                class="w-full"
                            >
                                Ver historial
                            </x-boton>
                        </div>
                    </div>
                </x-tarjeta>
            @empty
                <div class="col-span-full py-12 text-center text-hu-gris-medio">
                    <x-icono nombre="search_off" class="text-4xl mb-2 block mx-auto opacity-50" />
                    <p>No se encontraron pacientes que coincidan con la búsqueda.</p>
                </div>
            @endforelse
        </div>

        @if ($pacientes->hasPages())
            <div class="border-t border-hu-gris-suave/60 bg-hu-gris-tenue/30 px-5 py-4">
                {{ $pacientes->links() }}
            </div>
        @endif
    </x-tarjeta>
@endsection
