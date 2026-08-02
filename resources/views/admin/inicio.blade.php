@extends('layouts.app')

@section('titulo', 'Administración')
@section('subtitulo', 'Datos maestros y usuarios del sistema')

@section('contenido')

    {{-- Usuarios primero: es lo que más se toca. --}}
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

        <div class="sm:col-span-2 flex flex-col gap-4">
            <x-tarjeta class="w-full">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-hu-azul">Gestión de usuarios</p>
                        <p class="text-xs text-hu-gris-medio">
                            Alta de personas, legajos, credenciales y roles.
                        </p>
                    </div>

                    <x-boton :href="route('admin.usuarios.index')" icono="manage_accounts">
                        Administrar
                    </x-boton>
                </div>
            </x-tarjeta>

            <x-tarjeta class="w-full">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-hu-azul">Consentimientos informados</p>
                        <p class="text-xs text-hu-gris-medio">
                            El texto que firma el paciente, por tipo de cirugía.
                        </p>
                    </div>

                    <x-boton :href="route('admin.consentimientos.index')" variante="contorno" icono="draw">
                        Administrar
                    </x-boton>
                </div>
            </x-tarjeta>

            <x-tarjeta class="w-full">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-hu-azul">Auditoría</p>
                        <p class="text-xs text-hu-gris-medio">
                            Quién dio de alta, editó o dio de baja cada cosa, y qué cambió.
                        </p>
                    </div>

                    <x-boton :href="route('admin.auditoria')" variante="contorno" icono="assignment">
                        Ver historial
                    </x-boton>
                </div>
            </x-tarjeta>
        </div>
    </div>

    {{-- Tablas maestras, agrupadas por módulo. --}}
    <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($grupos as $nombre => $grupo)
            <x-tarjeta :titulo="$nombre" :icono="$grupo['icono']">
                {{--
                    El renglon entero es el enlace y se extiende hasta el borde
                    de la tarjeta (-mx-5 anula su padding), asi el area de click
                    es toda la fila y no solo el texto.
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

@endsection
