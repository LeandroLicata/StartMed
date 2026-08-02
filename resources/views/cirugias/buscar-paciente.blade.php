@extends('layouts.app')

@section('titulo', 'Nueva cirugía')
@section('subtitulo', 'Paso 1 de 2 · Buscar paciente')

@section('contenido')

    <x-boton variante="fantasma" forma="grupo" icono="arrow_back" :href="route('dashboard')" class="mb-6 px-3">
        Volver al tablero
    </x-boton>

    <x-tarjeta titulo="Buscar paciente por DNI" icono="badge">
        <form method="GET" action="{{ route('cirugias.crear') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-full max-w-xs">
                <x-input nombre="documento" etiqueta="DNI del paciente" :valor="$documento" requerido />
            </div>
            <x-boton tipo="submit" forma="grupo">Buscar</x-boton>
        </form>
    </x-tarjeta>

    @if ($buscado)
        <div class="mt-6">
            @if ($persona)
                @if ($persona->fechaHoraBajaPersona)
                    <x-alerta tipo="error" titulo="Paciente dado de baja">
                        {{ $persona->documento }} — {{ $persona->nombre_completo }} está dado de baja en el
                        sistema. No se puede continuar con esta persona.
                    </x-alerta>
                @else
                    <x-tarjeta titulo="Paciente encontrado" icono="check_circle">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-base font-semibold text-hu-azul">
                                    {{ $persona->documento }} — {{ $persona->nombre_completo }}
                                </p>
                                @if ($persona->fecha_nacimiento)
                                    <p class="text-sm text-hu-gris-medio">
                                        {{ $persona->fecha_nacimiento->translatedFormat('j/m/Y') }}
                                        · {{ $persona->fecha_nacimiento->age }} años
                                    </p>
                                @endif
                            </div>
                            <x-boton :href="route('cirugias.crear.formulario', $persona)" icono="check_circle">
                                Continuar
                            </x-boton>
                        </div>
                    </x-tarjeta>
                @endif
            @else
                <x-alerta tipo="aviso" titulo="No se encontró ningún paciente con DNI {{ $documento }}" class="mb-6">
                    Podés darlo de alta ahora para continuar con la cirugía.
                </x-alerta>

                <x-tarjeta titulo="Dar de alta al paciente" icono="person">
                    <form method="POST" action="{{ route('cirugias.crear.paciente') }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="documento" value="{{ $documento }}">

                        <x-input nombre="apellidos" etiqueta="Apellidos" requerido />
                        <x-input nombre="nombres" etiqueta="Nombres" requerido />
                        <x-input nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento" tipo="date" />
                        <x-select
                            nombre="genero"
                            etiqueta="Género"
                            :opciones="['F' => 'Femenino', 'M' => 'Masculino', 'X' => 'Otro']"
                        />
                        <x-input nombre="contacto_email_direccion" etiqueta="Email de contacto" tipo="email" />
                        <x-input nombre="contacto_telefono_numero" etiqueta="Teléfono de contacto" />

                        <div class="sm:col-span-2">
                            <x-boton tipo="submit" icono="check_circle">Dar de alta y continuar</x-boton>
                        </div>
                    </form>
                </x-tarjeta>
            @endif
        </div>
    @endif

@endsection
