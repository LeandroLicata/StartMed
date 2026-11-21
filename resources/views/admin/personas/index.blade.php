@extends('layouts.app')

@section('titulo', 'Personas')
@section('subtitulo', 'Administración · Padrón completo y acceso al portal')

@section('contenido')

    <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
        <x-boton :href="route('admin.personas.create')" icono="person_add">
            Nueva persona
        </x-boton>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica
            :valor="$indicadores['personas']"
            etiqueta="Personas activas"
            icono="badge"
            :detalle="$indicadores['dadasDeBaja'].' dadas de baja'"
        />

        <x-metrica
            :valor="$indicadores['soloPacientes']"
            etiqueta="Solo pacientes"
            icono="groups"
            detalle="Sin usuario de staff"
        />

        <x-metrica
            :valor="$indicadores['conUsuario']"
            etiqueta="Con usuario de staff"
            icono="manage_accounts"
            detalle="Personal / roles internos"
        />
    </div>

    <x-catalogos-relacionados :slugs="['tipo-documento', 'grupo-sanguineo', 'rol']" />

    <x-tarjeta titulo="Padrón de personas" icono="badge">
        <x-slot:acciones>
            <x-estado tono="info">{{ $personas->total() }}</x-estado>
        </x-slot:acciones>

        <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-[1fr_11rem_11rem_auto]">
            <x-input
                nombre="q"
                etiqueta="Buscar"
                :valor="$filtros['q']"
                placeholder="Apellido, nombre, DNI o mail"
            />

            <x-select
                nombre="tipo"
                etiqueta="Tipo"
                :opciones="['paciente' => 'Solo pacientes', 'usuario' => 'Con usuario staff']"
                :valor="$filtros['tipo']"
                vacio="Todos"
            />

            <x-filtro-baja :valor="$filtros['estado']" />

            <div class="flex items-end gap-2">
                <x-boton tipo="submit" variante="contorno" forma="grupo">Buscar</x-boton>

                @if (array_filter($filtros))
                    <x-boton :href="route('admin.personas.index')" variante="fantasma" forma="grupo">
                        Limpiar
                    </x-boton>
                @endif
            </div>
        </form>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-224 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Persona</th>
                        <th class="px-3 pb-2 font-semibold">Contacto</th>
                        <th class="px-3 pb-2 font-semibold">Acceso / Roles</th>
                        <th class="px-3 pb-2 font-semibold">Estado</th>
                        <th class="px-5 pb-2 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($personas as $persona)
                        @php
                            $deBaja = $persona->fechaHoraBajaPersona !== null;
                            $usuario = $persona->personal?->usuario;
                            $roles = $persona->personal?->rolesVigentes ?? collect();
                        @endphp

                        <tr class="align-middle hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-hu-azul">{{ $persona->nombre_completo }}</p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $persona->tipoDocumento?->nombreTipoDocumento ?? 'DNI' }} {{ $persona->documento }}
                                </p>
                            </td>

                            <td class="px-3 py-3">
                                <p class="text-hu-azul">{{ $persona->contacto_email_direccion ?? 'Sin mail personal' }}</p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $persona->telefono_numero ?? 'Sin teléfono' }}
                                    @if ($persona->direccionPersona)
                                        · {{ $persona->direccionPersona }}
                                    @endif
                                </p>
                            </td>

                            <td class="px-3 py-3">
                                @if ($usuario)
                                    <p class="mb-1 text-xs font-semibold text-hu-azul">{{ $usuario->nombreUsuario }}</p>
                                    @forelse ($roles as $rol)
                                        <x-estado tono="info" class="mr-1 mb-1">{{ $rol->nombreRol }}</x-estado>
                                    @empty
                                        <span class="text-xs text-hu-gris-medio">Sin roles vigentes</span>
                                    @endforelse
                                @else
                                    <x-estado tono="neutro">Solo paciente</x-estado>
                                @endif
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$deBaja ? 'neutro' : 'exito'">
                                    {{ $deBaja ? 'Dada de baja' : 'Activa' }}
                                </x-estado>
                            </td>

                            <td class="px-5 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <x-boton
                                        :href="route('admin.personas.edit', $persona)"
                                        variante="fantasma"
                                        icono="edit"
                                        forma="grupo"
                                        class="px-3"
                                    >
                                        <span class="sr-only sm:not-sr-only">Editar</span>
                                    </x-boton>

                                    @if (! $deBaja)
                                        @if ($usuario)
                                            <x-boton
                                                :href="route('admin.usuarios.edit', $usuario)"
                                                variante="contorno"
                                                icono="manage_accounts"
                                                forma="grupo"
                                                class="px-3"
                                            >
                                                <span class="sr-only sm:not-sr-only">Ver usuario</span>
                                            </x-boton>
                                        @else
                                            <x-boton
                                                :href="route('admin.usuarios.create', ['persona' => $persona->idPersona])"
                                                variante="contorno"
                                                icono="person_add"
                                                forma="grupo"
                                                class="px-3"
                                            >
                                                <span class="sr-only sm:not-sr-only">Crear usuario</span>
                                            </x-boton>
                                        @endif
                                    @endif

                                    @if ($deBaja)
                                        <form method="POST" action="{{ route('admin.personas.restore', $persona) }}">
                                            @csrf
                                            <x-boton variante="contorno" icono="restore" forma="grupo" class="px-3">
                                                <span class="sr-only sm:not-sr-only">Reactivar</span>
                                            </x-boton>
                                        </form>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.personas.destroy', $persona) }}"
                                            data-confirmar-titulo="Dar de baja a {{ $persona->nombre_completo }}"
                                            data-confirmar="No va a poder ingresar al portal de pacientes ni aparecer en búsquedas activas. Se puede reactivar más adelante."
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
                            <td colspan="5" class="px-5 py-10 text-center text-hu-gris-medio">
                                @if (array_filter($filtros))
                                    Ninguna persona coincide con la búsqueda.
                                @else
                                    No hay personas que mostrar.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($personas->hasPages())
            <div class="pt-4">{{ $personas->links() }}</div>
        @endif
    </x-tarjeta>

@endsection