@extends('layouts.app')

@section('titulo', 'Dirección médica')
@section('subtitulo', 'Período '.$desde->translatedFormat('F Y').' — '.now()->translatedFormat('F Y'))

@section('contenido')

    @php($maximo = max($porMes->max('total'), 1))

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica
            :valor="$mesActual['total']"
            etiqueta="Cirugías este mes"
            icono="event"
            :tono="$mesAnterior && $mesActual['total'] >= $mesAnterior['total'] ? 'exito' : 'aviso'"
            :detalle="$mesAnterior
                ? ($mesActual['total'] - $mesAnterior['total'] >= 0 ? '+' : '').($mesActual['total'] - $mesAnterior['total']).' vs. mes anterior'
                : 'Sin mes previo para comparar'"
        />

        <x-metrica
            :valor="$totalPeriodo"
            etiqueta="Total del período"
            icono="monitoring"
            :detalle="$porMes->count().' meses'"
        />

        <x-metrica
            :valor="$tasaSuspension.'%'"
            etiqueta="Tasa de suspensión"
            icono="cancel"
            :tono="$tasaSuspension < 5 ? 'exito' : 'error'"
            detalle="Objetivo institucional: menos del 5%"
        />

        <x-metrica
            :valor="$suspendidas"
            etiqueta="Cirugías suspendidas"
            icono="warning"
            :tono="$suspendidas > 0 ? 'aviso' : 'exito'"
            detalle="En todo el período"
        />
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        <x-tarjeta titulo="Volumen mensual" icono="monitoring" class="lg:col-span-2">
            {{-- Barras apiladas en CSS: realizadas abajo, suspendidas arriba. --}}
            <div class="flex h-56 items-end justify-between gap-3">
                @foreach ($porMes as $mes)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                        <span class="text-xs font-semibold text-hu-azul">{{ $mes['total'] }}</span>

                        <div
                            class="flex w-full max-w-16 flex-col justify-end overflow-hidden rounded-t-lg"
                            style="height: {{ max(round($mes['total'] / $maximo * 100), 2) }}%"
                            title="{{ $mes['realizadas'] }} realizadas · {{ $mes['suspendidas'] }} suspendidas"
                        >
                            @if ($mes['suspendidas'] > 0)
                                <div
                                    class="w-full bg-hu-dorado"
                                    style="height: {{ round($mes['suspendidas'] / max($mes['total'], 1) * 100) }}%"
                                ></div>
                            @endif
                            <div class="w-full flex-1 bg-hu-azul"></div>
                        </div>

                        <span class="text-xs uppercase text-hu-gris-medio">{{ $mes['etiqueta'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-hu-gris-suave/60 pt-3 text-xs">
                <span class="flex items-center gap-1.5">
                    <span class="size-3 rounded bg-hu-azul"></span> Realizadas
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-3 rounded bg-hu-dorado"></span> Suspendidas
                </span>
            </div>
        </x-tarjeta>

        <x-tarjeta titulo="Suspensión por mes" icono="cancel">
            <ul class="space-y-3">
                @foreach ($porMes as $mes)
                    <li>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="uppercase text-hu-gris-medio">{{ $mes['etiqueta'] }}</span>
                            <span class="font-semibold {{ $mes['tasa'] < 5 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ $mes['tasa'] }}%
                            </span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-hu-gris-tenue">
                            <div
                                class="h-full rounded-full {{ $mes['tasa'] < 5 ? 'bg-emerald-600' : 'bg-red-600' }}"
                                style="width: {{ min($mes['tasa'] * 10, 100) }}%"
                            ></div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 border-t border-hu-gris-suave/60 pt-3 text-xs text-hu-gris-medio">
                La barra llena equivale al 10%.
            </p>
        </x-tarjeta>
    </div>

    <x-tarjeta titulo="Uso de quirófanos" icono="meeting_room" class="mt-6">
        @php($maximoQuirofano = max($porQuirofano->max() ?? 0, 1))

        <div class="space-y-4">
            @foreach ($quirofanos as $quirofano)
                @php($cantidad = $porQuirofano[$quirofano->idQuirofano] ?? 0)

                <div>
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="font-semibold text-hu-azul">
                            {{ $quirofano->nombreQuirofano }}
                            <span class="font-normal text-hu-gris-medio">· Q{{ $quirofano->nroQuirofano }}</span>
                        </span>
                        <span class="text-hu-gris">
                            {{ $cantidad }} {{ str('cirugía')->plural($cantidad) }}
                        </span>
                    </div>
                    <div class="mt-1 h-2.5 overflow-hidden rounded-full bg-hu-gris-tenue">
                        <div
                            class="h-full rounded-full bg-hu-azul"
                            style="width: {{ round($cantidad / $maximoQuirofano * 100) }}%"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-tarjeta>

    <x-alerta tipo="info" titulo="Qué falta para completar este tablero" class="mt-6">
        <p class="leading-relaxed">
            El desglose de suspensiones por causa (estudios incompletos, autorización
            demorada, materiales sin aprobar, causa del paciente) todavía no se puede
            calcular: <code class="rounded bg-white/60 px-1">CirugiaEstado</code> registra
            <em>que</em> una cirugía se suspendió, pero no <em>por qué</em>. Hace falta una
            tabla de motivos de suspensión, o una columna en el historial de estados.
        </p>
        <p class="mt-2 leading-relaxed">
            Lo mismo con el impacto económico: se puede sumar
            <code class="rounded bg-white/60 px-1">PedidoMaterial.subtotalPedidoMaterial</code>,
            pero no hay ningún campo con el valor de la cirugía en sí.
        </p>
    </x-alerta>

@endsection
