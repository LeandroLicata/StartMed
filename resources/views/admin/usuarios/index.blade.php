@extends('layouts.app')

@section('titulo', 'Usuarios')
@section('subtitulo', 'Administración · Personas y acceso')

@section('contenido')

    {{-- Acá arriba van solo acciones; los filtros viven todos en el formulario. --}}
    <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
        <x-boton :href="route('admin.usuarios.create')" icono="add">
            Nuevo usuario
        </x-boton>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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

    {{-- Los dos catálogos que el formulario de alta ofrece como opciones. --}}
    <x-catalogos-relacionados :slugs="['rol', 'tipo-documento']" />

    <x-tarjeta titulo="Usuarios del sistema" icono="manage_accounts">
        <x-slot:acciones>
            <x-estado tono="info">{{ $usuarios->total() }}</x-estado>
        </x-slot:acciones>

        <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-[1fr_11rem_11rem_auto]">
            <x-input
                nombre="q"
                etiqueta="Buscar"
                :valor="$filtros['q']"
                placeholder="Apellido, nombre, usuario o legajo"
            />

            <x-select
                nombre="rol"
                etiqueta="Rol vigente"
                :opciones="$roles"
                :valor="$filtros['rol']"
                vacio="Todos"
            />

            <x-filtro-baja :valor="$filtros['estado']" />

            <div class="flex items-end gap-2">
                <x-boton tipo="submit" variante="contorno" forma="grupo">Buscar</x-boton>

                @if (array_filter($filtros))
                    <x-boton :href="route('admin.usuarios.index')" variante="fantasma" forma="grupo">
                        Limpiar
                    </x-boton>
                @endif
            </div>
        </form>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-208 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Usuario</th>
                        <th class="px-3 pb-2 font-semibold">Persona</th>
                        <th class="px-3 pb-2 font-semibold">Legajo</th>
                        <th class="px-3 pb-2 font-semibold">Roles vigentes</th>
                        <th class="px-3 pb-2 font-semibold">Estado</th>
                        <th class="px-5 pb-2 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($usuarios as $usuario)
                        @php
                            $deBaja = $usuario->estaDadoDeBaja();
                            $persona = $usuario->personal?->persona;
                            $roles = $usuario->personal?->rolesVigentes ?? collect();
                        @endphp

                        <tr class="align-middle hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3 font-semibold text-hu-azul">{{ $usuario->nombreUsuario }}</td>

                            <td class="px-3 py-3">
                                <p>{{ $persona?->nombre_completo ?? '—' }}</p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $usuario->personal?->mailInstitucional ?? 'Sin mail institucional' }}
                                </p>
                            </td>

                            <td class="px-3 py-3">{{ $usuario->personal?->legajoPersonal ?? '—' }}</td>

                            <td class="px-3 py-3">
                                @forelse ($roles as $rol)
                                    <x-estado tono="info" class="mr-1 mb-1">{{ $rol->nombreRol }}</x-estado>
                                @empty
                                    <span class="text-xs text-hu-gris-medio">Sin roles asignados</span>
                                @endforelse
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$deBaja ? 'neutro' : 'exito'">
                                    {{ $deBaja ? 'Dado de baja' : 'Activo' }}
                                </x-estado>
                            </td>

                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <x-boton
                                        :href="route('admin.usuarios.edit', $usuario)"
                                        variante="fantasma"
                                        icono="edit"
                                        forma="grupo"
                                        class="px-3"
                                    >
                                        <span class="sr-only sm:not-sr-only">Editar</span>
                                    </x-boton>

                                    @if ($deBaja)
                                        <form method="POST" action="{{ route('admin.usuarios.restore', $usuario) }}">
                                            @csrf
                                            <x-boton variante="contorno" icono="restore" forma="grupo" class="px-3">
                                                <span class="sr-only sm:not-sr-only">Reactivar</span>
                                            </x-boton>
                                        </form>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.usuarios.destroy', $usuario) }}"
                                            data-confirmar-titulo="Dar de baja a {{ $usuario->nombreUsuario }}"
                                            data-confirmar="No va a poder iniciar sesión. La cuenta y su historial quedan guardados, y se puede reactivar."
                                            data-confirmar-accion="Dar de baja"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-boton variante="peligro" icono="delete" forma="grupo" class="px-3">
                                                <span class="sr-only sm:not-sr-only">Dar de baja</span>
                                            </x-boton>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-hu-gris-medio">
                                @if (array_filter($filtros))
                                    Ningún usuario coincide con la búsqueda.
                                @else
                                    No hay usuarios que mostrar.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usuarios->hasPages())
            <div class="pt-4">{{ $usuarios->links() }}</div>
        @endif
    </x-tarjeta>

@endsection
