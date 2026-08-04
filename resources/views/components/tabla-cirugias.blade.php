@props(['cirugias'])

<div class="-mx-5 overflow-x-auto">
    <table class="w-full min-w-208 text-sm">
        <thead>
            <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                <th class="px-5 pb-2 font-semibold">Paciente</th>
                <th class="px-3 pb-2 font-semibold">Procedimiento</th>
                <th class="px-3 pb-2 font-semibold">Fecha</th>
                <th class="px-3 pb-2 font-semibold">Estudios</th>
                <th class="px-3 pb-2 font-semibold">Autorización</th>
                <th class="px-3 pb-2 font-semibold">Anestesia</th>
                <th class="px-3 pb-2 font-semibold">Materiales</th>
                <th class="px-5 pb-2 font-semibold">Estado</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-hu-gris-suave/60">
            @forelse ($cirugias as $caso)
                <tr class="relative align-middle hover:bg-hu-azul-tenue/40">
                    <td class="px-5 py-3">
                        <a
                            href="{{ route('cirugias.show', $caso->cirugia) }}"
                            class="absolute inset-0"
                            aria-label="Ver cirugía de {{ $caso->nombrePaciente() }}"
                        ></a>
                        <p class="font-semibold text-hu-azul">{{ $caso->nombrePaciente() }}</p>
                        <p class="text-xs text-hu-gris-medio">
                            {{ $caso->plan?->nombrePlan ?? 'Sin plan' }}
                        </p>
                    </td>

                    <td class="px-3 py-3">
                        <p>{{ $caso->procedimiento() }}</p>
                        <p class="text-xs text-hu-gris-medio">
                            {{ $caso->cirujano() ?? 'Sin cirujano' }}
                            @if ($caso->cirugia->requiereImplante)
                                · <span class="text-hu-dorado-oscuro">con implante</span>
                            @endif
                        </p>
                    </td>

                    <td class="px-3 py-3 whitespace-nowrap">
                        <p>{{ $caso->cuando()?->translatedFormat('D j/m') }}</p>
                        <p class="text-xs text-hu-gris-medio">
                            {{ $caso->cuando()?->format('H:i') }} hs
                            @if ($caso->quirofano)
                                · Q{{ $caso->quirofano->nroQuirofano }}
                            @endif
                        </p>
                    </td>

                    <td class="px-3 py-3">
                        <x-estado :tono="$caso->estudiosPendientes() === 0 ? 'exito' : 'aviso'">
                            {{ $caso->estudiosSubidos() }}/{{ $caso->estudiosTotal() }}
                        </x-estado>
                    </td>

                    <td class="px-3 py-3">
                        <x-estado :tono="$caso->autorizacionAprobada() ? 'exito' : 'aviso'">
                            {{ $caso->autorizacion() }}
                        </x-estado>
                    </td>

                    <td class="px-3 py-3">
                        <x-estado :tono="$caso->evaluacionCompleta() ? 'exito' : 'aviso'">
                            {{ $caso->asa() ?? $caso->evaluacion() }}
                        </x-estado>
                    </td>

                    <td class="px-3 py-3">
                        <x-estado :tono="$caso->materialesAprobados() ? 'exito' : 'aviso'">
                            {{ $caso->materiales() }}
                        </x-estado>
                    </td>

                    <td class="px-5 py-3">
                        <x-estado
                            :tono="$caso->semaforo()"
                            :icono="$caso->estaLista() ? 'check_circle' : 'warning'"
                        >
                            {{ $caso->estaLista() ? 'Listo' : ($caso->semaforo() === 'error' ? 'En riesgo' : $caso->estado()) }}
                        </x-estado>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-5 py-10 text-center text-hu-gris-medio">
                        Ninguna cirugía coincide con la búsqueda.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
