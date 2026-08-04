@extends('layouts.app')

@php
    $esNuevo = $usuario === null;
    $titulo = $esNuevo ? 'Nuevo usuario' : 'Editar usuario '.$usuario->nombreUsuario;

    // old() gana sobre lo guardado, para no perder la selección si falla la validación.
    $rolesElegidos = collect(old('roles', $rolesActuales))->map(fn ($id) => (int) $id);
@endphp

@section('titulo', $titulo)
@section('subtitulo', 'Administración · Personas y acceso')

@section('contenido')

    <div class="mb-4">
        <x-boton :href="route('admin.usuarios.index')" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver a usuarios
        </x-boton>
    </div>

    <x-tarjeta :titulo="$titulo" icono="manage_accounts" class="max-w-3xl">
        <form
            method="POST"
            action="{{ $esNuevo ? route('admin.usuarios.store') : route('admin.usuarios.update', $usuario) }}"
            class="space-y-6"
        >
            @csrf
            @unless ($esNuevo)
                @method('PUT')
            @endunless

            {{-- Persona: los datos de la persona real detrás del usuario. --}}
            <div class="space-y-4">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">Datos personales</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select
                        nombre="tipo_documento_id"
                        etiqueta="Tipo de documento"
                        :opciones="$tiposDocumento"
                        :valor="$valores['tipo_documento_id']"
                        requerido
                    />

                    <x-input
                        nombre="documento"
                        etiqueta="Documento"
                        :valor="$valores['documento']"
                        requerido
                    />

                    <x-input
                        nombre="apellidos"
                        etiqueta="Apellidos"
                        :valor="$valores['apellidos']"
                        requerido
                    />

                    <x-input
                        nombre="nombres"
                        etiqueta="Nombres"
                        :valor="$valores['nombres']"
                        requerido
                    />
                </div>
            </div>

            {{-- Personal: el legajo dentro del hospital. --}}
            <div class="space-y-4">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">Legajo</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input nombre="legajoPersonal" etiqueta="Legajo" :valor="$valores['legajoPersonal']" />

                    <x-input
                        nombre="mailInstitucional"
                        etiqueta="Mail institucional"
                        tipo="email"
                        :valor="$valores['mailInstitucional']"
                    />

                    <x-input
                        nombre="matriculaProvincial"
                        etiqueta="Matrícula provincial"
                        :valor="$valores['matriculaProvincial']"
                    />

                    <x-input
                        nombre="matriculaNacional"
                        etiqueta="Matrícula nacional"
                        :valor="$valores['matriculaNacional']"
                    />
                </div>
            </div>

            {{-- Usuario: con qué credenciales entra. --}}
            <div class="space-y-4">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">Acceso</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input
                        nombre="nombreUsuario"
                        etiqueta="Nombre de usuario"
                        :valor="$valores['nombreUsuario']"
                        ayuda="Con esto inicia sesión. No se puede repetir."
                        requerido
                    />

                    @if ($esNuevo)
                        <div class="hidden sm:block"></div>

                        <x-input
                            nombre="password"
                            etiqueta="Contraseña"
                            tipo="password"
                            ayuda="Mínimo 8 caracteres."
                            requerido
                        />

                        <x-input
                            nombre="password_confirmation"
                            etiqueta="Repetir contraseña"
                            tipo="password"
                            requerido
                        />
                    @endif
                </div>

                @unless ($esNuevo)
                    <x-alerta tipo="info">
                        La contraseña se cambia por separado, en el formulario de abajo.
                    </x-alerta>
                @endunless
            </div>

            {{-- Roles vigentes: quitar uno cierra la asignación, no borra el historial. --}}
            <div class="space-y-4">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">Roles</h3>

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $rol)
                        <label class="flex items-center gap-2.5 rounded-xl border border-hu-gris-suave/70 px-3.5 py-2.5 text-sm">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $rol->idRol }}"
                                @checked($rolesElegidos->contains($rol->idRol))
                                class="size-4 shrink-0 rounded border-hu-gris-suave bg-white text-hu-azul focus:ring-hu-azul"
                            >
                            <span class="font-semibold text-hu-azul">{{ $rol->nombreRol }}</span>
                        </label>
                    @endforeach
                </div>

                @error('roles')
                    <p class="flex items-center gap-1 text-xs font-semibold text-red-700">
                        <x-icono nombre="error" class="text-sm" relleno />
                        {{ $message }}
                    </p>
                @enderror

                <p class="text-xs text-hu-gris-medio">
                    El rol decide a qué panel entra el usuario. Sin ningún rol, ve una pantalla
                    que se lo explica.
                </p>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <x-boton tipo="submit" icono="check_circle">
                    {{ $esNuevo ? 'Crear usuario' : 'Guardar cambios' }}
                </x-boton>

                <x-boton :href="route('admin.usuarios.index')" variante="fantasma">
                    Cancelar
                </x-boton>
            </div>
        </form>
    </x-tarjeta>

    @unless ($esNuevo)
        <x-tarjeta titulo="Cambiar contraseña" icono="key" class="mt-6 max-w-3xl">
            <form method="POST" action="{{ route('admin.usuarios.clave', $usuario) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input
                        nombre="password"
                        etiqueta="Nueva contraseña"
                        tipo="password"
                        ayuda="Mínimo 8 caracteres."
                        requerido
                    />

                    <x-input
                        nombre="password_confirmation"
                        etiqueta="Repetir contraseña"
                        tipo="password"
                        requerido
                    />
                </div>

                <x-boton tipo="submit" variante="secundario" icono="key">
                    Cambiar contraseña
                </x-boton>
            </form>
        </x-tarjeta>
    @endunless

@endsection
