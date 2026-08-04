@extends('layouts.app')

@section('titulo', 'Mis cirugias')
@section('subtitulo', $personal->persona?->nombre_completo.' - '.($personal->matriculaProvincial ?? 'sin matricula'))
@section('contenido')

    <div class="mb-4 flex justify-end">
        <x-boton
            variante="contorno"
            forma="grupo"
            icono="event"
            :href="route('cirujano.agenda')"
            class="px-4 py-2 text-sm"
        >
            Ver agenda
        </x-boton>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica
            :valor="$proximas->count()"
            etiqueta="Cirugias programadas"
            icono="event"
            detalle="De hoy en adelante"
        />

        <x-metrica
            :valor="$indicadores['realizadasCompletas'].'/'.$indicadores['realizadas']"
            etiqueta="Realizadas por completo en {{ $indicadores['mes'] }}"
            icono="check_circle"
            tono="exito"
            detalle="Con checklist 100% cerrado"
        />

        <x-metrica
            :valor="$indicadores['tasaSuspension'].'%'"
            etiqueta="Suspension propia"
            icono="monitoring"
            :tono="$indicadores['tasaSuspension'] <= 5 ? 'exito' : 'aviso'"
            :detalle="$indicadores['suspendidas'].' suspendidas este mes'"
        />

        <x-metrica
            :valor="$conImplante"
            etiqueta="Proximas con implante"
            icono="inventory_2"
            :tono="$conImplante > 0 ? 'aviso' : 'neutro'"
            detalle="Requieren autorizacion de materiales"
        />
    </div>

    <x-tarjeta titulo="Pacientes de hoy" icono="today" class="mt-6">
        @forelse ($hoy as $caso)
            <a
                href="{{ route('cirugias.show', $caso->cirugia) }}"
                class="-mx-5 flex flex-wrap items-center gap-4 border-b border-hu-gris-suave/60 px-5 py-3.5
                       transition-colors last:border-0 hover:bg-hu-azul-tenue/50"
            >
                <div class="w-16 shrink-0">
                    <p class="text-sm font-black text-hu-azul">{{ $caso->cuando()?->format('H:i') }}</p>
                    <p class="text-xs text-hu-gris-medio">hs</p>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-hu-azul">{{ $caso->nombrePaciente() }}</p>
                    <p class="truncate text-sm text-hu-gris-medio">
                        {{ $caso->procedimiento() }}
                        @if ($caso->quirofano)
                            - {{ $caso->quirofano->nombreQuirofano }}
                        @endif
                    </p>
                </div>

                <x-estado :tono="$caso->semaforo()" :icono="$caso->estaLista() ? 'check_circle' : 'warning'">
                    {{ $caso->estaLista() ? 'Listo' : 'Pendiente' }}
                </x-estado>
            </a>
        @empty
            <p class="py-10 text-center text-sm text-hu-gris-medio">
                No tenes cirugias programadas para hoy.
            </p>
        @endforelse
    </x-tarjeta>

    <x-tarjeta titulo="Mis proximas cirugias" icono="event" class="mt-6">
        @forelse ($proximas as $caso)
            <a
                href="{{ route('cirugias.show', $caso->cirugia) }}"
                class="-mx-5 flex flex-wrap items-center gap-4 border-b border-hu-gris-suave/60 px-5 py-3.5
                       transition-colors last:border-0 hover:bg-hu-azul-tenue/50"
            >
                <div class="w-24 shrink-0">
                    <p class="text-sm font-black text-hu-azul">
                        {{ $caso->cuando()?->translatedFormat('D j/m') }}
                    </p>
                    <p class="text-xs text-hu-gris-medio">{{ $caso->cuando()?->format('H:i') }} hs</p>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-hu-azul">{{ $caso->nombrePaciente() }}</p>
                    <p class="truncate text-sm text-hu-gris-medio">
                        {{ $caso->procedimiento() }}
                        @if ($caso->quirofano)
                            - {{ $caso->quirofano->nombreQuirofano }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($caso->cirugia->requiereImplante)
                        <x-estado tono="aviso" icono="inventory_2">Con implante</x-estado>
                    @endif

                    @if ($caso->asa())
                        <x-estado tono="info">{{ $caso->asa() }}</x-estado>
                    @endif

                    <x-estado :tono="$caso->semaforo()" :icono="$caso->estaLista() ? 'check_circle' : 'warning'">
                        {{ $caso->estaLista() ? 'Listo' : 'Pendiente' }}
                    </x-estado>
                </div>
            </a>
        @empty
            <p class="py-10 text-center text-sm text-hu-gris-medio">
                No tenes cirugias programadas.
            </p>
        @endforelse
    </x-tarjeta>

    @php($conPendientes = $proximas->reject(fn ($c) => $c->estaLista()))

    @if ($conPendientes->isNotEmpty())
        <x-tarjeta titulo="Que falta resolver" icono="warning" class="mt-6">
            <ul class="divide-y divide-hu-gris-suave/60">
                @foreach ($conPendientes as $caso)
                    <li class="py-3 first:pt-0 last:pb-0">
                        <p class="font-semibold text-hu-azul">
                            {{ $caso->nombrePaciente() }}
                            <span class="font-normal text-hu-gris-medio">
                                - {{ $caso->cuando()?->translatedFormat('D j/m') }}
                            </span>
                        </p>
                        <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            @foreach ($caso->pendientes() as $pendiente)
                                <li class="flex items-center gap-1 text-hu-gris">
                                    <x-icono nombre="pending" class="text-sm text-hu-dorado-oscuro" relleno />
                                    {{ $pendiente }}
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    <x-tarjeta titulo="Procedimientos habilitados" icono="assignment" class="mt-6">
        <p class="mb-4 text-sm text-hu-gris-medio">
            Catalogo de tipos de cirugia del sistema. Cada uno tiene su plantilla de
            consentimiento informado asociada.
        </p>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($procedimientos as $procedimiento)
                <div class="rounded-xl border border-hu-gris-suave/70 px-4 py-3">
                    <p class="text-sm font-semibold text-hu-azul">
                        {{ $procedimiento->nombreTipoCirugia }}
                    </p>
                    @if ($procedimiento->descripcionTipoCirugia)
                        <p class="mt-1 text-xs leading-relaxed text-hu-gris-medio">
                            {{ $procedimiento->descripcionTipoCirugia }}
                        </p>
                    @endif
                    <p class="mt-2 text-xs text-hu-dorado-oscuro">
                        {{ $procedimiento->cirugias_count }}
                        {{ str('registrada')->plural($procedimiento->cirugias_count) }}
                    </p>
                </div>
            @endforeach
        </div>
    </x-tarjeta>

@endsection




