@extends('layouts.app')

@php
    /*
     * Todo lo de esta pantalla es navegación: lleva a otro lado y nada más.
     * Por eso módulos y catálogos comparten el mismo tratamiento —tarjeta
     * clickeable con chevron— y la jerarquía la hace el orden y la densidad,
     * no un botón por tarjeta.
     */
    $modulos = [
        [
            'etiqueta' => 'Usuarios',
            'descripcion' => 'Personas, legajos, credenciales y roles.',
            'icono' => 'manage_accounts',
            'ruta' => 'admin.usuarios.index',
        ],
        [
            'etiqueta' => 'Consentimientos informados',
            'descripcion' => 'El texto que firma el paciente, por tipo de cirugía.',
            'icono' => 'draw',
            'ruta' => 'admin.consentimientos.index',
        ],
        [
            'etiqueta' => 'Cuestionario preanestésico',
            'descripcion' => 'Lo que completa el paciente antes de la evaluación.',
            'icono' => 'assignment',
            'ruta' => 'admin.cuestionario.index',
        ],
        [
            'etiqueta' => 'Proveedores y precios',
            'descripcion' => 'Quién vende cada material, a cuánto y en qué unidades.',
            'icono' => 'inventory_2',
            'ruta' => 'admin.precios.index',
        ],
        [
            'etiqueta' => 'Auditoría',
            'descripcion' => 'Quién dio de alta, editó o dio de baja cada cosa.',
            'icono' => 'description',
            'ruta' => 'admin.auditoria',
        ],
    ];

    $encabezado = 'mb-3 text-xs font-semibold uppercase tracking-widest text-hu-gris-medio';

    $tarjetaEnlace = 'group flex items-start gap-3 rounded-2xl border border-hu-gris-suave/70 bg-white
                      px-5 py-4 shadow-sm transition-colors hover:border-hu-azul-suave
                      hover:bg-hu-azul-tenue/40 focus-visible:outline-2
                      focus-visible:-outline-offset-2 focus-visible:outline-hu-azul';

    $chevron = 'mt-1 shrink-0 text-lg text-hu-gris-suave transition-transform
                group-hover:translate-x-0.5 group-hover:text-hu-dorado motion-reduce:transition-none';
@endphp

@section('titulo', 'Administración')
@section('subtitulo', 'Datos maestros y usuarios del sistema')

@section('contenido')

    {{-- En su propia grilla: si comparten fila con los módulos, se estiran. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica
            :valor="$indicadores['usuarios']"
            etiqueta="Usuarios activos"
            icono="manage_accounts"
            :detalle="$indicadores['dadosDeBaja'].' dados de baja'"
        />

        <x-metrica
            :valor="$indicadores['sinRol']"
            etiqueta="Usuarios sin rol"
            icono="warning"
            :tono="$indicadores['sinRol'] > 0 ? 'aviso' : 'exito'"
            detalle="No pueden entrar a ningún panel"
        />
    </div>

    <section class="mt-8">
        <h2 class="{{ $encabezado }}">Módulos</h2>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($modulos as $modulo)
                <a href="{{ route($modulo['ruta']) }}" class="{{ $tarjetaEnlace }}">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-hu-azul-tenue text-hu-azul">
                        <x-icono :nombre="$modulo['icono']" class="text-xl" relleno />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-hu-azul">{{ $modulo['etiqueta'] }}</span>
                        <span class="mt-0.5 block text-xs text-hu-gris-medio">{{ $modulo['descripcion'] }}</span>
                    </span>

                    <x-icono nombre="chevron_right" class="{{ $chevron }}" />
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-8">
        <h2 class="{{ $encabezado }}">Catálogos</h2>

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
