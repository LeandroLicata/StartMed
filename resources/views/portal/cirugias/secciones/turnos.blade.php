{{--
    Pestaña "turnos" del detalle. Recibe $caso (App\Support\ResumenCirugia) y $soloLectura.

    Los turnos médicos quedan fuera del alcance del sistema. La pestaña muestra
    el aviso para coordinar por los canales habituales del hospital y lo que sí
    se sabe por los datos reales (evaluación anestésica y estudios pendientes),
    sin inventar horarios ni consultorios.
--}}

@php
    $evaluacionCompleta = $caso->evaluacionCompleta();
    $estudiosPendientes = $caso->estudiosPendientes();
    $nombresPendientes = $caso->nombresEstudiosPendientes();
@endphp

<x-tarjeta titulo="Turnos previos a tu cirugía" icono="event">
    @if ($soloLectura)
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <x-icono nombre="history" class="text-4xl text-hu-gris-medio" />
            <p class="text-sm text-hu-gris-medio">
                Los turnos de esta cirugía se coordinaron fuera de StartMed.
            </p>
        </div>
    @else
        <div class="space-y-6">
            {{-- Aviso principal --}}
            <div class="rounded-xl border border-hu-dorado/30 bg-hu-dorado-tenue/40 p-4">
                <div class="flex items-start gap-2.5">
                    <x-icono nombre="info" class="mt-0.5 shrink-0 text-lg text-hu-dorado-oscuro" />
                    <div class="min-w-0">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-hu-dorado-oscuro">
                            Coordiná tus turnos por los canales habituales
                        </h3>
                        <p class="mt-1 text-sm text-hu-azul">
                            Los turnos (evaluación con el anestesista, cardiología, clínica u otros que te pida tu equipo) se coordinan por los canales habituales del hospital, y StartMed todavía no los muestra.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Lo que sí se sabe por datos reales --}}
            <div class="space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-hu-dorado-oscuro">
                    Estado de tus consultas y estudios
                </h3>

                <ul class="divide-y divide-hu-gris-suave/70 rounded-xl border border-hu-gris-suave/70 bg-hu-gris-tenue/30 px-4">
                    {{-- Evaluación con el anestesista --}}
                    <li class="flex flex-col gap-2 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-hu-azul">
                                Evaluación con el anestesista
                            </p>
                            <p class="text-xs text-hu-gris-medio">
                                @if ($evaluacionCompleta)
                                    Tu anestesista ya completó la evaluación preanestésica.
                                @else
                                    Pendiente de realización por parte del equipo de anestesia.
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0">
                            @if ($evaluacionCompleta)
                                <x-estado tono="exito" icono="check_circle">Completa</x-estado>
                            @else
                                <x-estado tono="aviso" icono="schedule">Pendiente</x-estado>
                            @endif
                        </div>
                    </li>

                    {{-- Estudios pedidos que faltan subir, como recordatorio --}}
                    @if ($estudiosPendientes > 0)
                        <li class="flex flex-col gap-2 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-hu-azul">
                                    Estudios prequirúrgicos
                                </p>
                                <p class="text-xs text-hu-gris-medio">
                                    Tenés {{ $estudiosPendientes }} {{ str('estudio')->plural($estudiosPendientes) }} pendiente(s) de subir:
                                    <span class="font-medium text-hu-azul">{{ $nombresPendientes->join(', ') }}</span>.
                                </p>
                                <div class="mt-1">
                                    <a
                                        href="{{ route('portal.cirugias.show', [$caso->cirugia, 'estudios']) }}"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-hu-azul underline hover:text-hu-azul-claro"
                                    >
                                        <span>Ir a la pestaña Estudios</span>
                                        <x-icono nombre="chevron_right" class="text-sm" />
                                    </a>
                                </div>
                            </div>
                            <div class="shrink-0">
                                <x-estado tono="aviso" icono="schedule">Faltan subir</x-estado>
                            </div>
                        </li>
                    @endif
                </ul>
            </div>

            {{-- Link a contacto --}}
            <div class="border-t border-hu-gris-suave/70 pt-4">
                <a
                    href="{{ route('portal.cirugias.show', [$caso->cirugia, 'contacto']) }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-hu-azul bg-white px-4 py-2.5 text-sm font-semibold text-hu-azul shadow-sm transition hover:bg-hu-azul-tenue"
                >
                    <x-icono nombre="call" class="text-lg text-hu-dorado-oscuro" />
                    <span>¿Necesitás coordinar un turno? Contactá al hospital</span>
                    <x-icono nombre="chevron_right" class="text-base" />
                </a>
            </div>
        </div>
    @endif
</x-tarjeta>
