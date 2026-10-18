{{--
    Pestaña "resumen" del detalle. A cargo de: Leandro. Recibe $caso y $soloLectura.

    Todo sale de ResumenCirugia o de config/portal.php: si un dato no esta, la
    fila no se muestra en vez de inventarse.
--}}

@php
    $datos = array_filter([
        'Cirujano/a' => $caso->cirujano(),
        'Anestesista' => $caso->anestesista(),
        'Quirófano' => $caso->quirofano?->nombreQuirofano,
        'Dirección' => config('portal.direccion_hospital'),
        'Obra social' => $caso->cobertura(),
        'Autorización' => $caso->autorizacion()
            .($caso->nroAutorizacion() ? ' · N.º '.$caso->nroAutorizacion() : ''),
    ]);
@endphp

<div class="grid items-start gap-4 lg:grid-cols-2">
    <x-tarjeta titulo="Datos de mi cirugía" icono="info">
        <dl class="divide-y divide-hu-gris-suave/70 text-sm">
            @foreach ($datos as $etiqueta => $valor)
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2.5">
                    <dt class="text-hu-gris-medio">{{ $etiqueta }}</dt>
                    <dd class="text-right font-semibold text-hu-azul">{{ $valor }}</dd>
                </div>
            @endforeach
        </dl>
    </x-tarjeta>

    <x-tarjeta titulo="Estado de mi proceso" icono="assignment">
        <ul class="divide-y divide-hu-gris-suave/70">
            @foreach ($caso->procesoPaciente() as $paso)
                @php($enlace = $paso['seccion'] ? route('portal.cirugias.show', [$caso->cirugia, $paso['seccion']]) : null)
                <li>
                    {{-- Sin href cuando no depende del paciente: queda como texto, no como link. --}}
                    <a @if ($enlace) href="{{ $enlace }}" @endif @class([
                        'flex items-center gap-3 py-3',
                        '-mx-2 rounded-lg px-2 hover:bg-hu-gris-tenue' => $enlace,
                    ])>
                        <span @class([
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            'bg-emerald-50 text-emerald-700' => $paso['listo'],
                            'bg-hu-dorado-tenue text-hu-dorado-oscuro' => ! $paso['listo'],
                        ])>
                            <x-icono :nombre="$paso['listo'] ? 'check_circle' : 'schedule'" class="text-xl" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-hu-azul">{{ $paso['titulo'] }}</p>
                            <p class="text-xs text-hu-gris-medio">{{ $paso['detalle'] }}</p>
                        </div>

                        <x-estado :tono="$paso['listo'] ? 'exito' : 'aviso'">
                            {{ $paso['listo'] ? 'Listo' : 'Pendiente' }}
                        </x-estado>

                        @if ($enlace)
                            <x-icono nombre="chevron_right" class="-mr-1 shrink-0 text-xl text-hu-gris-medio" />
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </x-tarjeta>
</div>
