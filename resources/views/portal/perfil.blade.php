@extends('layouts.portal')

@section('titulo', 'Mi perfil')

@section('contenido')
    <div class="grid items-start gap-6 lg:grid-cols-2">
        {{-- Columna izquierda: obras sociales vigentes + agregar --}}
        <div class="space-y-6">
            <x-tarjeta titulo="Mis obras sociales" icono="shield">
                @forelse ($vigentes as $cobertura)
                    <div class="flex items-center justify-between gap-3 border-b border-hu-gris-suave/60 py-3 last:border-0">
                        <div>
                            <p class="font-semibold text-hu-azul">
                                {{ $cobertura->plan?->obrasocial?->nombreObraSocial }} · {{ $cobertura->plan?->nombrePlan }}
                            </p>
                            <p class="text-sm text-hu-gris-medio">
                                Nro. de afiliado: {{ $cobertura->nroBeneficiaroPlanObraSocial }}
                            </p>

                            @if ($cobertura->fechaFinPlanObraSocial)
                                <p class="text-xs text-hu-dorado-oscuro">
                                    Vigente hasta el {{ $cobertura->fechaFinPlanObraSocial->format('d/m/Y') }}
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
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

                            <form
                                method="POST"
                                action="{{ route('portal.perfil.baja', $cobertura->idPlanObraSocial) }}"
                                data-confirmar-titulo="Dar de baja «{{ $cobertura->plan?->obrasocial?->nombreObraSocial }}»"
                                data-confirmar="Va a pasar a tus obras sociales anteriores. Desde el portal no se puede deshacer."
                                data-confirmar-accion="Dar de baja"
                            >
                                @csrf
                                @method('PATCH')
                                <x-boton variante="fantasma" forma="grupo" class="px-3 py-1.5 text-xs">
                                    Dar de baja
                                </x-boton>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-hu-gris-medio">
                        No tenés obras sociales vigentes.
                    </p>
                @endforelse
            </x-tarjeta>

            <x-tarjeta titulo="Agregar una obra social" icono="shield">
                <form method="POST" action="{{ route('portal.perfil.agregar') }}" class="space-y-4">
                    @csrf

                    <x-select
                        nombre="idPlan"
                        etiqueta="Obra social y plan"
                        :opciones="$planes"
                        requerido
                    />

                    <x-input
                        nombre="nroBeneficiario"
                        etiqueta="Número de afiliado"
                        placeholder="Como figura en tu credencial"
                        requerido
                    />

                    <div class="flex justify-end">
                        <x-boton forma="grupo">Agregar</x-boton>
                    </div>
                </form>
            </x-tarjeta>
        </div>

        {{-- Columna derecha: obras sociales anteriores (solo si existen) --}}
        @if ($historicas->isNotEmpty())
            <x-tarjeta titulo="Obras sociales anteriores" icono="history">
                @foreach ($historicas as $cobertura)
                    <div class="border-b border-hu-gris-suave/60 py-3 last:border-0">
                        <p class="font-semibold text-hu-gris">
                            {{ $cobertura->plan?->obrasocial?->nombreObraSocial }} · {{ $cobertura->plan?->nombrePlan }}
                        </p>
                        <p class="text-sm text-hu-gris-medio">
                            Dada de baja el {{ $cobertura->fechaFinPlanObraSocial->format('d/m/Y') }}
                        </p>
                    </div>
                @endforeach
            </x-tarjeta>
        @endif
    </div>
@endsection
