@props(['estado'])

{{--
    El estado vigente de una cirugia (CirugiaEstado), tal cual el catalogo
    EstadoCirugia; solo cambian el color y el icono. Lo usan el portal del
    paciente y el staff, que lo muestra junto al semaforo de preparacion.

    Los iconos tienen que estar en icon_names de los dos layouts (app y portal).
--}}

@php
    [$tono, $icono] = match ($estado) {
        'Realizada', 'Confirmada', 'Asistencia de paciente confirmada' => ['exito', 'check_circle'],
        'Suspendida' => ['error', 'cancel'],
        'A reprogramar' => ['aviso', 'event_busy'],
        'En riesgo' => ['aviso', 'warning'],
        'Reprogramada' => ['neutro', 'history'],
        'Sin estado' => ['neutro', 'pending'],
        default => ['info', 'schedule'],
    };
@endphp

<x-estado :tono="$tono" :icono="$icono" {{ $attributes }}>{{ $estado }}</x-estado>
