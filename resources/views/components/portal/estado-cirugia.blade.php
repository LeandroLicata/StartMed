@props(['estado'])

{{--
    El estado vigente de una cirugia como lo ve el paciente. El nombre es el
    del catalogo EstadoCirugia; solo cambia el color.
--}}

@php
    $tono = match ($estado) {
        'Realizada', 'Confirmada', 'Asistencia de paciente confirmada' => 'exito',
        'Suspendida' => 'error',
        'A reprogramar', 'En riesgo' => 'aviso',
        'Reprogramada', 'Sin estado' => 'neutro',
        default => 'info',
    };
@endphp

<x-estado :tono="$tono" {{ $attributes }}>{{ $estado }}</x-estado>
