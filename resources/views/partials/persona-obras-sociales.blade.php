<div class="space-y-6">
    <x-tarjeta titulo="Obras sociales" icono="shield">
        @forelse ($coberturasVigentes as $cobertura)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hu-gris-suave/60 py-3 last:border-0">
                <div>
                    <p class="font-semibold text-hu-azul">
                        {{ $cobertura->plan?->obrasocial?->nombreObraSocial }} · {{ $cobertura->plan?->nombrePlan }}
                    </p>
                    <p class="text-sm text-hu-gris-medio">
                        Nro. de afiliado: {{ $cobertura->nroBeneficiaroPlanObraSocial ?: '—' }}
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
                    @elseif ($puedeGestionar)
                        <form method="POST" action="{{ route('personas.coberturas.predeterminada', [$persona, $cobertura->idPlanObraSocial]) }}">
                            @csrf
                            @method('PATCH')
                            <x-boton variante="contorno" forma="grupo" class="px-3 py-1.5 text-xs">
                                Marcar como predeterminada
                            </x-boton>
                        </form>
                    @endif

                    @if ($puedeGestionar)
                        <form
                            method="POST"
                            action="{{ route('personas.coberturas.baja', [$persona, $cobertura->idPlanObraSocial]) }}"
                            data-confirmar-titulo="Dar de baja «{{ $cobertura->plan?->obrasocial?->nombreObraSocial }}»"
                            data-confirmar="La cobertura pasará al historial de obras sociales anteriores."
                            data-confirmar-accion="Dar de baja"
                        >
                            @csrf
                            @method('PATCH')
                            <x-boton variante="peligro" icono="delete" forma="grupo" class="px-3 py-1.5 text-xs">
                                Dar de baja
                            </x-boton>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-sm text-hu-gris-medio">
                No tiene obras sociales vigentes registradas.
            </p>
        @endforelse

        @if ($puedeGestionar)
            <div class="mt-4 border-t border-hu-gris-suave/60 pt-4">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">
                    Agregar obra social
                </h3>

                <form method="POST" action="{{ route('personas.coberturas.store', $persona) }}" class="grid gap-3 sm:grid-cols-[1fr_13rem_auto] sm:items-end">
                    @csrf

                    <x-select
                        nombre="idPlan"
                        etiqueta="Obra social y plan"
                        :opciones="$planesDisponibles"
                        requerido
                    />

                    <x-input
                        nombre="nroBeneficiario"
                        etiqueta="Número de afiliado"
                        placeholder="Ej: 123456"
                        requerido
                    />

                    <div>
                        <x-boton forma="grupo" icono="add_circle" class="w-full sm:w-auto">
                            Agregar
                        </x-boton>
                    </div>
                </form>
            </div>
        @endif
    </x-tarjeta>

    @if ($coberturasHistoricas->isNotEmpty())
        <x-tarjeta titulo="Obras sociales anteriores" icono="history">
            @foreach ($coberturasHistoricas as $cobertura)
                <div class="border-b border-hu-gris-suave/60 py-2.5 last:border-0 text-sm">
                    <p class="font-semibold text-hu-gris">
                        {{ $cobertura->plan?->obrasocial?->nombreObraSocial }} · {{ $cobertura->plan?->nombrePlan }}
                    </p>
                    <p class="text-xs text-hu-gris-medio">
                        Afiliado: {{ $cobertura->nroBeneficiaroPlanObraSocial ?: '—' }}
                        · Dada de baja el {{ $cobertura->fechaFinPlanObraSocial->format('d/m/Y') }}
                    </p>
                </div>
            @endforeach
        </x-tarjeta>
    @endif
</div>