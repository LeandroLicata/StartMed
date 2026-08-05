@extends('layouts.app')

@php
    /*
     * Esta pantalla es la única puerta a los 27 catálogos: los módulos de
     * administración ya son secciones del menú, pero una tabla maestra suelta
     * no merece un renglón ahí, así que entran todas por acá, agrupadas.
     *
     * Todo lo que hay es navegación: lleva a otro lado y nada más. Por eso la
     * jerarquía la hacen el orden y la densidad, no un botón por tarjeta. El
     * título de la pantalla ya dice qué es esto: no lleva encabezado propio.
     */
@endphp

@section('titulo', 'Catálogos')
@section('subtitulo', 'Administración · Datos maestros del sistema')

@section('contenido')

    <section>
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($grupos as $nombre => $grupo)
                <x-tarjeta :titulo="$nombre" :icono="$grupo['icono']">
                    {{--
                        El renglón entero es el enlace y llega hasta el borde de
                        la tarjeta, así el área de click es toda la fila.
                    --}}
                    <ul class="-mx-5 divide-y divide-hu-gris-suave/60">
                        @foreach ($grupo['catalogos'] as $catalogo)
                            <li>
                                <a
                                    href="{{ route('admin.catalogos.index', $catalogo['slug']) }}"
                                    class="group flex items-center justify-between gap-3 px-5 py-2.5 text-sm
                                           transition-colors hover:bg-hu-azul-tenue/50
                                           focus-visible:bg-hu-azul-tenue/50 focus-visible:outline-2
                                           focus-visible:-outline-offset-2 focus-visible:outline-hu-azul"
                                >
                                    <span class="min-w-0 truncate font-semibold text-hu-azul">
                                        {{ $catalogo['plural'] }}
                                    </span>

                                    <span class="flex shrink-0 items-center gap-2">
                                        <x-estado tono="neutro">{{ $catalogo['activos'] }}</x-estado>

                                        {{--
                                            Visible siempre, no solo al pasar el mouse: una
                                            pista que aparece con el hover confirma que se
                                            puede clickear, pero no lo anuncia.
                                        --}}
                                        <x-icono
                                            nombre="chevron_right"
                                            class="text-lg text-hu-gris-suave transition-transform
                                                   group-hover:translate-x-0.5 group-hover:text-hu-dorado
                                                   motion-reduce:transition-none"
                                        />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-tarjeta>
            @endforeach
        </div>
    </section>

@endsection
