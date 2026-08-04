@props(['valor' => ''])

{{--
    El filtro de activos / dados de baja, igual en los 26 listados de catálogo
    y en el de usuarios. Las opciones salen de App\Support\FiltroBaja para que
    no se llamen distinto en cada pantalla.
--}}
<x-select
    nombre="estado"
    etiqueta="Estado"
    :opciones="\App\Support\FiltroBaja::opciones()"
    :valor="$valor"
    :vacio="false"
    {{ $attributes }}
/>
