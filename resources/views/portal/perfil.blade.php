@extends('layouts.portal')

@section('titulo', 'Mi perfil')

@section('contenido')
    @php
        $iniciales = mb_strtoupper(mb_substr($persona->nombres, 0, 1).mb_substr($persona->apellidos, 0, 1));
    @endphp

    <div class="space-y-6">
        {{-- Identidad y datos personales de solo lectura (ancho completo) --}}
        <x-tarjeta titulo="Mis datos" icono="person">
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-hu-azul text-lg font-bold tracking-wide text-white">
                        {{ $iniciales }}
                    </span>

                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-bold text-hu-azul">{{ $persona->nombre_completo }}</h2>
                        <p class="text-xs text-hu-gris-medio">Ficha personal registrada en el hospital</p>
                    </div>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-hu-gris-suave/60 bg-hu-gris-tenue/40 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-hu-gris-medio">DNI</dt>
                        <dd class="mt-0.5 font-semibold text-hu-azul">{{ $persona->documento }}</dd>
                    </div>

                    <div class="rounded-xl border border-hu-gris-suave/60 bg-hu-gris-tenue/40 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-hu-gris-medio">Fecha de nacimiento</dt>
                        <dd class="mt-0.5 font-semibold text-hu-azul">{{ $persona->fecha_nacimiento?->format('d/m/Y') ?? 'Sin cargar' }}</dd>
                    </div>

                    <div class="rounded-xl border border-hu-gris-suave/60 bg-hu-gris-tenue/40 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-hu-gris-medio">Grupo sanguíneo</dt>
                        <dd class="mt-0.5 font-semibold text-hu-azul">{{ $persona->grupoSanguineo?->nombreGrupoSanguineo ?? 'Sin cargar' }}</dd>
                    </div>

                    <div class="rounded-xl border border-hu-gris-suave/60 bg-hu-gris-tenue/40 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-hu-gris-medio">Dirección</dt>
                        <dd class="mt-0.5 truncate font-semibold text-hu-azul" title="{{ $persona->direccionPersona }}">
                            {{ $persona->direccionPersona ?? 'Sin cargar' }}
                        </dd>
                    </div>
                </dl>

                <p class="flex items-center gap-1.5 text-xs text-hu-gris-medio">
                    <x-icono nombre="info" class="text-sm shrink-0" />
                    <span>Si algún dato está mal, avisale al hospital desde la pestaña Contacto de tu cirugía.</span>
                </p>
            </div>
        </x-tarjeta>

        {{-- Columnas principales equilibradas --}}
        <div class="grid items-start gap-6 lg:grid-cols-2">
            {{-- Columna izquierda: obras sociales y email --}}
            <div class="space-y-6">
                <x-tarjeta titulo="Mis obras sociales" icono="shield">
                    @if ($vigentes->isNotEmpty())
                        <div class="space-y-3">
                            @foreach ($vigentes as $cobertura)
                                <div @class([
                                    'flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 transition-colors',
                                    'border-emerald-200 bg-emerald-50/40' => $cobertura->esPredeterminadaPlanObraSocial,
                                    'border-hu-gris-suave/70 bg-white' => ! $cobertura->esPredeterminadaPlanObraSocial,
                                ])>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-hu-azul">
                                            {{ $cobertura->plan?->obrasocial?->nombreObraSocial }} · {{ $cobertura->plan?->nombrePlan }}
                                        </p>
                                        <p class="mt-0.5 text-sm text-hu-gris-medio">
                                            Nro. de afiliado: {{ $cobertura->nroBeneficiaroPlanObraSocial }}
                                        </p>

                                        @if ($cobertura->fechaFinPlanObraSocial)
                                            <p class="mt-1 text-xs font-medium text-hu-dorado-oscuro">
                                                Vigente hasta el {{ $cobertura->fechaFinPlanObraSocial->format('d/m/Y') }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        @if ($cobertura->esPredeterminadaPlanObraSocial)
                                            <x-estado tono="exito" icono="check_circle">Predeterminada</x-estado>
                                        @else
                                            <form method="POST" action="{{ route('portal.perfil.predeterminada', $cobertura->idPlanObraSocial) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-boton variante="contorno" forma="grupo" class="px-3 py-1.5 text-xs">
                                                    Marcar como predeterminada
                                                </x-boton>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-hu-gris-suave bg-hu-gris-tenue/30 px-4 py-7 text-center">
                            <span class="mx-auto mb-2.5 flex size-11 items-center justify-center rounded-full bg-hu-azul-tenue text-hu-azul">
                                <x-icono nombre="shield" class="text-xl" />
                            </span>
                            <p class="text-sm font-semibold text-hu-azul">No tenés obras sociales vigentes.</p>
                            <p class="mt-1 text-xs text-hu-gris-medio">
                                Cuando el hospital cargue tus coberturas activas aparecerán acá.
                            </p>
                        </div>
                    @endif

                    <p class="mt-4 flex items-start gap-1.5 border-t border-hu-gris-suave/60 pt-3 text-xs text-hu-gris-medio">
                        <x-icono nombre="info" class="mt-0.5 text-sm shrink-0" />
                        <span>Solo podés elegir tu obra social predeterminada. Si necesitás agregar o dar de baja una cobertura, solicitalo en recepción o al gestor de quirófano.</span>
                    </p>
                </x-tarjeta>

                @if ($historicas->isNotEmpty())
                    <x-tarjeta titulo="Obras sociales anteriores" icono="history">
                        <div class="divide-y divide-hu-gris-suave/60">
                            @foreach ($historicas as $cobertura)
                                <div class="py-3 first:pt-0 last:pb-0">
                                    <p class="font-semibold text-hu-gris">
                                        {{ $cobertura->plan?->obrasocial?->nombreObraSocial }} · {{ $cobertura->plan?->nombrePlan }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-hu-gris-medio">
                                        Dada de baja el {{ $cobertura->fechaFinPlanObraSocial->format('d/m/Y') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </x-tarjeta>
                @endif

                <x-tarjeta titulo="Mi email" icono="mail">
                    <div class="mb-4 rounded-xl border border-hu-gris-suave/60 bg-hu-gris-tenue/40 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-hu-gris-medio">Email actual</p>
                        <p class="mt-0.5 break-all font-semibold text-hu-azul">{{ $persona->contacto_email_direccion ?? 'Sin cargar' }}</p>
                    </div>

                    <form method="POST" action="{{ route('portal.perfil.email') }}" class="space-y-4">
                        @csrf

                        <x-input
                            nombre="email_nuevo"
                            tipo="email"
                            etiqueta="Email nuevo"
                            placeholder="nombre@ejemplo.com"
                            ayuda="Te vamos a mandar un link a este email para confirmar que es tuyo."
                            requerido
                        />

                        <div class="flex justify-end">
                            <x-boton forma="grupo" icono="mail">Cambiar email</x-boton>
                        </div>
                    </form>
                </x-tarjeta>
            </div>

            {{-- Columna derecha: datos de contacto y preferencias clínicas --}}
            <div class="space-y-6">
                <x-tarjeta titulo="Mis datos de contacto" icono="call">
                    <p class="mb-4 text-xs text-hu-gris-medio">
                        Mantené actualizados tus teléfonos para que el equipo del hospital pueda comunicarse antes de tu cirugía.
                    </p>

                    <form method="POST" action="{{ route('portal.perfil.datos') }}" class="space-y-5">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-3 gap-3">
                            <x-input
                                nombre="telefono_codigo"
                                etiqueta="Código de área"
                                placeholder="261"
                                :valor="$persona->telefono_codigo"
                            />
                            <div class="col-span-2">
                                <x-input
                                    nombre="telefono_numero"
                                    etiqueta="Teléfono fijo"
                                    placeholder="4251234"
                                    :valor="$persona->telefono_numero"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <x-input
                                nombre="contacto_celular_codigo"
                                etiqueta="Código de área"
                                placeholder="261"
                                :valor="$persona->contacto_celular_codigo"
                            />
                            <div class="col-span-2">
                                <x-input
                                    nombre="contacto_celular_numero"
                                    etiqueta="Celular"
                                    placeholder="5551234"
                                    :valor="$persona->contacto_celular_numero"
                                />
                            </div>
                        </div>

                        <div class="rounded-xl border border-hu-gris-suave/70 bg-hu-gris-tenue/40 p-4">
                            <x-checkbox
                                nombre="no_acepta_donacion_sanguinea"
                                etiqueta="No acepto recibir transfusiones de sangre"
                                ayuda="El equipo médico lo tiene en cuenta antes de tu cirugía."
                                :valor="$persona->no_acepta_donacion_sanguinea"
                            />
                        </div>

                        <div class="flex justify-end border-t border-hu-gris-suave/60 pt-4">
                            <x-boton forma="grupo" icono="check_circle">Guardar cambios</x-boton>
                        </div>
                    </form>
                </x-tarjeta>
            </div>
        </div>
    </div>
@endsection