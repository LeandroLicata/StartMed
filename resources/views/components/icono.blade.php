@props([
    'nombre',
    'relleno' => false,
])

{{--
    Material Symbols (Google), como indica el Manual de Marca.
    El nombre es el de https://fonts.google.com/icons — por ejemplo "event".
    Si agregas un icono nuevo, sumalo tambien a la lista `icon_names` del
    <link> en layouts/app.blade.php, o no se descarga.
--}}
<span
    {{ $attributes->class(['material-symbols-rounded', 'relleno' => $relleno]) }}
    aria-hidden="true"
>{{ $nombre }}</span>
