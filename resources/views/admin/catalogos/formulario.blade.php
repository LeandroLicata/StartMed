@extends('layouts.app')

@php
    use App\Support\Catalogos;

    $esNuevo = ! $registro->exists;
    $titulo = $esNuevo ? 'Nuevo: '.$config['singular'] : 'Editar '.mb_strtolower($config['singular']);
@endphp

@section('titulo', $titulo)
@section('subtitulo', 'Administración · '.$config['plural'])

@section('contenido')

    <div class="mb-4">
        <x-boton
            :href="route('admin.catalogos.index', $config['slug'])"
            variante="fantasma"
            icono="arrow_back"
            forma="grupo"
        >
            Volver a {{ mb_strtolower($config['plural']) }}
        </x-boton>
    </div>

    <x-tarjeta :titulo="$titulo" :icono="Catalogos::GRUPOS[$config['grupo']]" class="max-w-2xl">
        @if ($bloqueo)
            <x-alerta tipo="aviso" titulo="Registro protegido" class="mb-5">{{ $bloqueo }}</x-alerta>
        @endif

        <form
            method="POST"
            action="{{ $esNuevo
                ? route('admin.catalogos.store', $config['slug'])
                : route('admin.catalogos.update', [$config['slug'], $registro->getKey()]) }}"
            class="space-y-5"
        >
            @csrf
            @unless ($esNuevo)
                @method('PUT')
            @endunless

            @foreach ($config['campos'] as $columna => $campo)
                @php
                    $tipo = $campo['tipo'] ?? 'texto';
                    // En un alta el registro esta vacio, asi que vale el default declarado.
                    $valor = $registro->$columna ?? ($campo['defecto'] ?? null);
                @endphp

                @switch ($tipo)
                    @case ('texto-largo')
                        <x-textarea
                            :nombre="$columna"
                            :etiqueta="$campo['etiqueta']"
                            :valor="$valor"
                            :requerido="$campo['requerido'] ?? false"
                            :ayuda="$campo['ayuda'] ?? null"
                        />
                        @break

                    @case ('select')
                        <x-select
                            :nombre="$columna"
                            :etiqueta="$campo['etiqueta']"
                            :valor="$valor"
                            :opciones="Catalogos::opciones($campo)"
                            :requerido="$campo['requerido'] ?? false"
                            :ayuda="$campo['ayuda'] ?? null"
                        />
                        @break

                    @case ('booleano')
                        <x-checkbox
                            :nombre="$columna"
                            :etiqueta="$campo['etiqueta']"
                            :valor="$valor"
                            :ayuda="$campo['ayuda'] ?? null"
                        />
                        @break

                    @default
                        <x-input
                            :nombre="$columna"
                            :etiqueta="$campo['etiqueta']"
                            :tipo="Catalogos::TIPOS[$tipo]"
                            :valor="$valor"
                            :requerido="$campo['requerido'] ?? false"
                            :ayuda="$campo['ayuda'] ?? null"
                        />
                @endswitch
            @endforeach

            <div class="flex items-center gap-3 pt-1">
                @unless ($bloqueo)
                    <x-boton tipo="submit" icono="check_circle">
                        {{ $esNuevo ? 'Crear' : 'Guardar cambios' }}
                    </x-boton>
                @endunless

                <x-boton :href="route('admin.catalogos.index', $config['slug'])" variante="fantasma">
                    {{ $bloqueo ? 'Volver' : 'Cancelar' }}
                </x-boton>
            </div>
        </form>
    </x-tarjeta>

@endsection
