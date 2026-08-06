@extends('layouts.app')

@section('titulo', 'Detalle del Paciente')
@section('subtitulo', $paciente->apellidos . ', ' . $paciente->nombres)

@section('contenido')
    <div class="mb-4 flex items-center justify-between">
        <x-boton :href="route('pacientes.index')" variante="fantasma" icono="arrow_back">
            Volver al listado
        </x-boton>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Ficha del paciente --}}
        <div class="lg:col-span-1 space-y-6">
            <x-tarjeta titulo="Ficha personal" icono="person">
                <div class="space-y-4 text-sm mt-2">
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-hu-gris-medio">Nombre completo</span>
                        <span class="font-semibold text-hu-azul">{{ $paciente->apellidos }}, {{ $paciente->nombres }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-hu-gris-medio">Documento</span>
                        <span class="font-semibold text-hu-azul">{{ $paciente->tipoDocumento?->nombreTipoDocumento ?? 'DNI' }} {{ $paciente->documento }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-hu-gris-medio">Edad / Nacimiento</span>
                        <span class="font-semibold text-hu-azul">
                            @if($paciente->fecha_nacimiento)
                                {{ \Carbon\Carbon::parse($paciente->fecha_nacimiento)->age }} años ({{ \Carbon\Carbon::parse($paciente->fecha_nacimiento)->format('d/m/Y') }})
                            @elseif($paciente->usar_edad_aproximada && $paciente->fecha_edad_aproximada)
                                Aprox. {{ \Carbon\Carbon::parse($paciente->fecha_edad_aproximada)->age }} años
                            @else
                                No registrado
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-hu-gris-medio">Sexo</span>
                        <span class="font-semibold text-hu-azul">{{ $paciente->genero ?? 'No registrado' }}</span>
                    </div>
                    <div class="pt-3 border-t border-hu-gris-suave/60">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-hu-gris-medio">Contacto</span>
                        @if($paciente->telefono_numero)
                            <div class="flex items-center gap-2 mt-1">
                                <x-icono nombre="call" class="text-sm text-hu-gris-medio" />
                                <span class="font-semibold text-hu-azul">{{ $paciente->telefono_numero }}</span>
                            </div>
                        @endif
                        @if($paciente->contacto_email_direccion)
                            <div class="flex items-center gap-2 mt-1">
                                <x-icono nombre="mail" class="text-sm text-hu-gris-medio" />
                                <span class="font-semibold text-hu-azul">{{ $paciente->contacto_email_direccion }}</span>
                            </div>
                        @endif
                        @if(!$paciente->telefono_numero && !$paciente->contacto_email_direccion)
                            <span class="text-hu-gris-medio text-sm">Sin datos de contacto</span>
                        @endif
                    </div>
                    
                    @if($paciente->es_cronico || $paciente->tiene_incapacidad || $paciente->no_acepta_donacion_sanguinea)
                        <div class="pt-3 border-t border-hu-gris-suave/60 flex flex-wrap gap-2">
                            @if($paciente->es_cronico)
                                <span class="inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-md">
                                    <x-icono nombre="warning" class="text-sm" /> Crónico
                                </span>
                            @endif
                            @if($paciente->tiene_incapacidad)
                                <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-md">
                                    <x-icono nombre="accessible" class="text-sm" /> Incapacidad
                                </span>
                            @endif
                            @if($paciente->no_acepta_donacion_sanguinea)
                                <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-700 text-xs font-bold px-2 py-1 rounded-md">
                                    <x-icono text-sm nombre="bloodtype" class="text-sm" /> No donación
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </x-tarjeta>
        </div>

        {{-- Historial de cirugías --}}
        <div class="lg:col-span-2 space-y-6">
            <x-tarjeta titulo="Historial de Cirugías" icono="history">
                <x-slot:acciones>
                    <x-estado tono="info">{{ $paciente->cirugias->count() }} cirugías</x-estado>
                </x-slot:acciones>
                
                @if ($paciente->cirugias->isEmpty())
                    <p class="py-12 text-center text-hu-gris-medio">
                        <x-icono nombre="event_busy" class="text-4xl mb-2 opacity-50 block mx-auto" />
                        Este paciente no tiene cirugías registradas.
                    </p>
                @else
                    <div class="relative border-l border-hu-gris-suave/80 ml-3 mt-4 space-y-8 pb-4">
                        @foreach ($paciente->cirugias as $cirugia)
                            @php
                                $estado = $cirugia->cirugiaEstados->firstWhere('fechaDesasignacionCirugiaEstado', null)?->estadoCirugia?->nombreEstadoCirugia ?? 'Programada';
                                $esPasada = $cirugia->fechaHoraCirugia && $cirugia->fechaHoraCirugia->isPast();
                                
                                $color = match(true) {
                                    $estado === 'Realizada' => 'bg-green-500',
                                    $estado === 'Suspendida' || $estado === 'Cancelada' => 'bg-red-500',
                                    $esPasada && $estado !== 'Realizada' => 'bg-orange-500', // Pasó la fecha pero no dice realizada
                                    default => 'bg-hu-azul',
                                };
                            @endphp
                            <div class="relative pl-6">
                                <span class="absolute -left-[13px] top-1 flex size-6 items-center justify-center rounded-full {{ $color }} text-white ring-4 ring-white">
                                    <x-icono nombre="event" class="text-xs" />
                                </span>
                                
                                <div class="rounded-xl border border-hu-gris-suave/60 bg-white p-4 shadow-sm hover:border-hu-azul/30 transition-colors">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-xs font-bold uppercase tracking-wider text-hu-gris-medio">
                                                    {{ $cirugia->fechaHoraCirugia ? $cirugia->fechaHoraCirugia->translatedFormat('D d/m/Y - H:i') : 'Sin fecha' }}
                                                </span>
                                                <span class="rounded-md bg-hu-gris-tenue px-2 py-0.5 text-xs font-bold text-hu-azul">
                                                    {{ $estado }}
                                                </span>
                                            </div>
                                            <h3 class="text-base font-bold text-hu-azul">
                                                {{ $cirugia->tipoCirugia?->nombreTipoCirugia ?? $cirugia->descripcionCirugia ?? 'Procedimiento no especificado' }}
                                            </h3>
                                            <p class="mt-2 flex items-center gap-1 text-sm text-hu-gris-medio">
                                                <x-icono nombre="medical_services" class="text-sm" /> 
                                                Cirujano: <span class="font-semibold">{{ $cirugia->cirujano?->persona?->nombre_completo ?? 'No asignado' }}</span>
                                            </p>
                                        </div>
                                        <div class="shrink-0">
                                            <x-boton :href="route('cirugias.show', $cirugia)" variante="primario" icono="visibility" class="!px-3 !py-1.5 !text-xs">
                                                Ver detalle
                                            </x-boton>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-tarjeta>
        </div>
    </div>
@endsection
