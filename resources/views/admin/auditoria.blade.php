@extends('layouts.app')

@section('titulo', 'Auditoría')
@section('subtitulo', 'Administración · Quién escribió qué')

@section('contenido')

    @php
        // Cada acción con su tono, para que se lean de un vistazo.
        $tonos = [
            'alta' => 'exito',
            'edición' => 'info',
            'baja' => 'error',
            'reactivación' => 'aviso',
            'cambio de contraseña' => 'neutro',
        ];
    @endphp

    <x-tarjeta titulo="Historial de cambios" icono="assignment">
        <x-slot:acciones>
            <x-estado tono="info">{{ $registros->total() }}</x-estado>
        </x-slot:acciones>

        <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-4">
            <x-select
                nombre="autor"
                etiqueta="Autor"
                :opciones="$autores"
                :valor="$filtros['autor']"
                vacio="Todos"
            />

            <x-select
                nombre="accion"
                etiqueta="Acción"
                :opciones="$acciones"
                :valor="$filtros['accion']"
                vacio="Todas"
            />

            <x-select
                nombre="tabla"
                etiqueta="Tabla"
                :opciones="$tablas"
                :valor="$filtros['tabla']"
                vacio="Todas"
            />

            <div class="flex items-end gap-2">
                <x-boton tipo="submit" variante="contorno" forma="grupo">Filtrar</x-boton>

                @if (array_filter($filtros))
                    <x-boton :href="route('admin.auditoria')" variante="fantasma" forma="grupo">
                        Limpiar
                    </x-boton>
                @endif
            </div>
        </form>

        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-208 text-sm">
                <thead>
                    <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                        <th class="px-5 pb-2 font-semibold">Cuándo</th>
                        <th class="px-3 pb-2 font-semibold">Autor</th>
                        <th class="px-3 pb-2 font-semibold">Acción</th>
                        <th class="px-5 pb-2 font-semibold">Sobre qué</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-hu-gris-suave/60">
                    @forelse ($registros as $registro)
                        <tr class="align-top hover:bg-hu-azul-tenue/40">
                            <td class="px-5 py-3 whitespace-nowrap tabular-nums">
                                <p>{{ $registro->fechaHoraAuditoria->translatedFormat('D j/m/Y') }}</p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $registro->fechaHoraAuditoria->format('H:i') }} hs
                                </p>
                            </td>

                            <td class="px-3 py-3">
                                <p class="font-semibold text-hu-azul">
                                    {{ $registro->usuario?->nombreUsuario ?? 'Sistema' }}
                                </p>
                                <p class="text-xs text-hu-gris-medio">
                                    {{ $registro->usuario?->personal?->persona?->nombre_completo }}
                                </p>
                            </td>

                            <td class="px-3 py-3">
                                <x-estado :tono="$tonos[$registro->accionAuditoria] ?? 'neutro'">
                                    {{ ucfirst($registro->accionAuditoria) }}
                                </x-estado>
                            </td>

                            <td class="px-5 py-3">
                                <p>{{ $registro->descripcionAuditoria }}</p>

                                @if ($registro->cambiosAuditoria)
                                    <ul class="mt-1 space-y-0.5 text-xs text-hu-gris-medio">
                                        @foreach ($registro->cambiosAuditoria as $campo => $cambio)
                                            <li>
                                                <span class="font-semibold">{{ $campo }}:</span>
                                                <span class="line-through">{{ $cambio['antes'] ?? '—' }}</span>
                                                <span aria-hidden="true">→</span>
                                                <span class="text-hu-azul">{{ $cambio['despues'] ?? '—' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-hu-gris-medio">
                                @if (array_filter($filtros))
                                    Ningún movimiento coincide con el filtro.
                                @else
                                    Todavía no se registró ningún cambio.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($registros->hasPages())
            <div class="pt-4">{{ $registros->links() }}</div>
        @endif
    </x-tarjeta>

@endsection
