@extends('layouts.app')

@section('titulo', $caso->nombrePaciente())
@section('subtitulo', $caso->procedimiento().' · '.$caso->cuando()?->translatedFormat('l j/m · H:i').' hs')

@section('contenido')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-boton variante="fantasma" forma="grupo" icono="arrow_back" tipo="button"
                onclick="history.back()" class="px-3">
            Volver
        </x-boton>
        <div class="flex flex-wrap items-center gap-2">
            @if ($caso->quirofano)
                <x-estado tono="info" icono="meeting_room">{{ $caso->quirofano->nombreQuirofano }}</x-estado>
            @endif

            @if ($caso->cirugia->requiereImplante)
                <x-estado tono="aviso" icono="inventory_2">Con implante</x-estado>
            @endif

            <x-estado :tono="$caso->semaforo()" :icono="$caso->estaLista() ? 'check_circle' : 'warning'">
                {{ $caso->estaLista() ? 'Listo para operar' : $caso->estado() }}
            </x-estado>

            <x-boton
                variante="contorno"
                forma="grupo"
                icono="person"
                :href="route('cirugias.portal', $caso->cirugia)"
                class="px-3 py-1.5 text-xs"
            >
                Ver como el paciente
            </x-boton>
        </div>
    </div>

    @if ($caso->alertaProfilaxis())
        <x-alerta tipo="error" titulo="Alerta clínica" class="mb-6">
            {{ $caso->alertaProfilaxis() }}
        </x-alerta>
    @endif

    {{-- Checklist general --}}
    <x-tarjeta titulo="Estado del proceso" icono="check_circle">
        <ul class="divide-y divide-hu-gris-suave/60">
            @php
                $items = [
                    ['Autorización del acto quirúrgico', $caso->autorizacion(), $caso->autorizacionAprobada(),
                        $caso->nroAutorizacion() ? 'Nº '.$caso->nroAutorizacion() : ($caso->plan?->nombrePlan ?? '')],
                    ['Estudios prequirúrgicos', $caso->estudiosSubidos().' de '.$caso->estudiosTotal().' subidos',
                        $caso->estudiosPendientes() === 0,
                        $caso->nombresEstudiosPendientes()->join(', ')],
                    ['Evaluación anestésica', $caso->evaluacion(), $caso->evaluacionCompleta(),
                        trim(($caso->asa() ?? '').' '.($caso->tipoAnestesia() ? '· '.$caso->tipoAnestesia() : ''))],
                    ['Materiales', $caso->materiales(), $caso->materialesAprobados(),
                        $caso->requiereMateriales() ? 'USD '.number_format($caso->importeMateriales(), 2, ',', '.') : ''],
                    ['Consentimiento informado',
                        $caso->consentimientoFirmado() ? 'Firmado' : ($caso->consentimiento() ? 'Pendiente de firma' : 'Sin generar'),
                        $caso->consentimientoFirmado(),
                        $caso->consentimiento()?->fechaFirmaConsentimiento?->translatedFormat('j/m/Y') ?? ''],
                ];
            @endphp

            @foreach ($items as [$titulo, $valor, $ok, $detalle])
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-icono
                            :nombre="$ok ? 'check_circle' : 'pending'"
                            class="shrink-0 text-xl {{ $ok ? 'text-emerald-600' : 'text-hu-dorado-oscuro' }}"
                            relleno
                        />
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-hu-azul">{{ $titulo }}</p>
                            @if ($detalle)
                                <p class="truncate text-xs text-hu-gris-medio">{{ $detalle }}</p>
                            @endif
                        </div>
                    </div>

                    <x-estado :tono="$ok ? 'exito' : 'aviso'">{{ $valor }}</x-estado>
                </li>
            @endforeach
        </ul>
    </x-tarjeta>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">

        {{-- Paciente --}}
        <x-tarjeta titulo="Paciente" icono="person">
            <dl class="divide-y divide-hu-gris-suave/60 text-sm">
                @php
                    $paciente = $caso->paciente;
                    $datos = [
                        'Documento' => $paciente?->tipoDocumento?->nombreTipoDocumento.' '.$paciente?->documento,
                        'Fecha de nacimiento' => $paciente?->fecha_nacimiento?->translatedFormat('j/m/Y')
                            .($paciente?->fecha_nacimiento ? ' · '.$paciente->fecha_nacimiento->age.' años' : ''),
                        'Grupo sanguíneo' => $paciente?->grupoSanguineo?->nombreGrupoSanguineo,
                        'Cobertura' => $caso->plan
                            ? $caso->plan->obraSocial?->nombreObraSocial.' · '.$caso->plan->nombrePlan
                            : null,
                    ];
                @endphp

                @foreach (array_filter($datos, fn ($v) => trim((string) $v) !== '') as $etiqueta => $valor)
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-hu-gris-medio">{{ $etiqueta }}</dt>
                        <dd class="text-right font-semibold text-hu-azul">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($paciente?->observaciones)
                <div class="mt-3 rounded-xl bg-hu-dorado-tenue px-3 py-2 text-xs leading-relaxed">
                    {{ $paciente->observaciones }}
                </div>
            @endif
        </x-tarjeta>

        {{-- Equipo --}}
        <x-tarjeta titulo="Equipo quirúrgico" icono="groups">
            @forelse ($caso->equipo() as $miembro)
                <div class="flex items-center gap-3 border-b border-hu-gris-suave/60 py-2.5 last:border-0">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-hu-azul-tenue text-hu-azul">
                        <x-icono nombre="person" class="text-lg" relleno />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-hu-azul">
                            {{ $miembro->personal?->persona?->nombre_completo }}
                        </p>
                        <p class="text-xs text-hu-gris-medio">
                            {{ $miembro->rol?->nombreRol }}
                            @if ($miembro->personal?->matriculaProvincial)
                                · {{ $miembro->personal->matriculaProvincial }}
                            @endif
                        </p>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-hu-gris-medio">Sin equipo asignado.</p>
            @endforelse
        </x-tarjeta>

        {{-- Estudios --}}
        <x-tarjeta titulo="Estudios prequirúrgicos" icono="science">
            @forelse ($caso->cirugia->cirugiaTipoEstudios as $estudio)
                <div class="flex items-center justify-between gap-3 border-b border-hu-gris-suave/60 py-2.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-hu-azul">
                            {{ $estudio->tipoEstudio?->nombreTipoEstudio }}
                        </p>
                        <p class="text-xs text-hu-gris-medio">
                            @if ($estudio->fechaSubidaCirugiaTipoEstudio)
                                Subido el {{ $estudio->fechaSubidaCirugiaTipoEstudio->translatedFormat('j/m') }}
                                @if ($estudio->resultadoCirugiaTipoEstudio)
                                    · {{ $estudio->resultadoCirugiaTipoEstudio }}
                                @endif
                            @else
                                Vence el {{ $estudio->fechaEsperadaResultadoCirugiaTipoEstudio?->translatedFormat('j/m') ?? 's/d' }}
                            @endif
                        </p>
                    </div>

                    <x-estado :tono="$estudio->fechaSubidaCirugiaTipoEstudio ? 'exito' : 'aviso'">
                        {{ $estudio->fechaSubidaCirugiaTipoEstudio ? 'Subido' : 'Pendiente' }}
                    </x-estado>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-hu-gris-medio">Sin estudios indicados.</p>
            @endforelse
        </x-tarjeta>

        {{-- Profilaxis --}}
        <x-tarjeta titulo="Profilaxis antibiótica" icono="vaccines">
            @forelse ($caso->profilaxis() as $item)
                <div class="border-b border-hu-gris-suave/60 py-2.5 last:border-0">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-hu-azul">
                            {{ $item->profilaxis?->nombreProfilaxis }}
                        </p>
                        <x-estado :tono="$item->profilaxisRol?->nombreProfilaxisRol === 'Principal' ? 'info' : 'neutro'">
                            {{ $item->profilaxisRol?->nombreProfilaxisRol }}
                        </x-estado>
                    </div>
                    @if ($item->indicacionesProfilaxisAtbCirugiaProfilaxis)
                        <p class="mt-0.5 text-xs text-hu-gris-medio">
                            {{ $item->indicacionesProfilaxisAtbCirugiaProfilaxis }}
                        </p>
                    @endif
                </div>
            @empty
                <p class="py-6 text-center text-sm text-hu-gris-medio">Sin profilaxis indicada.</p>
            @endforelse
        </x-tarjeta>
    </div>

    {{-- Materiales --}}
    @if ($caso->requiereMateriales())
        <x-tarjeta titulo="Materiales y presupuesto" icono="inventory_2" class="mt-6">
            <x-slot:acciones>
                <x-estado :tono="$caso->materialesAprobados() ? 'exito' : 'aviso'">
                    {{ $caso->materiales() }}
                </x-estado>
            </x-slot:acciones>

            <div class="-mx-5 overflow-x-auto">
                <table class="w-full min-w-160 text-sm">
                    <thead>
                        <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                            <th class="px-5 pb-2 font-semibold">Material</th>
                            <th class="px-3 pb-2 font-semibold">Proveedor</th>
                            <th class="px-3 pb-2 font-semibold">Cantidad</th>
                            <th class="px-5 pb-2 text-right font-semibold">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hu-gris-suave/60">
                        @foreach ($caso->cirugia->pedidoMateriales as $pedido)
                            <tr>
                                <td class="px-5 py-2.5">
                                    <p class="font-semibold text-hu-azul">{{ $pedido->material?->nombreMaterial }}</p>
                                    @if ($pedido->material?->codMaterial)
                                        <p class="text-xs text-hu-gris-medio">Cód. {{ $pedido->material->codMaterial }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">{{ $pedido->proveedor?->nombreProveedor ?? '—' }}</td>
                                <td class="px-3 py-2.5">
                                    {{ $pedido->cantidadPedidoMaterial }}
                                    <span class="text-hu-gris-medio">{{ $pedido->tipoMedida?->nombreTipoMedida }}</span>
                                </td>
                                <td class="px-5 py-2.5 text-right font-semibold text-hu-azul">
                                    USD {{ number_format((float) $pedido->subtotalPedidoMaterial, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-hu-azul/20">
                            <td colspan="3" class="px-5 pt-3 text-right text-sm font-semibold">Total</td>
                            <td class="px-5 pt-3 text-right text-base font-black text-hu-azul">
                                USD {{ number_format($caso->importeMateriales(), 2, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-tarjeta>
    @endif

    {{-- Hemoderivados --}}
    @if ($caso->hemoderivados()->isNotEmpty())
        <x-tarjeta titulo="Reserva de hemoderivados" icono="bloodtype" class="mt-6">
            @foreach ($caso->hemoderivados() as $item)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hu-gris-suave/60 py-2.5 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-hu-azul">
                            {{ $item->tipoHemoderivado?->nombreTipoHemoderivado }}
                        </p>
                        <p class="text-xs text-hu-gris-medio">
                            {{ $item->establecimiento?->nombreEstablecimiento }}
                            @if ($caso->paciente?->grupoSanguineo)
                                · Grupo {{ $caso->paciente->grupoSanguineo->nombreGrupoSanguineo }}
                            @endif
                        </p>
                        @if ($item->descripcionPedidoTipoHemoderivado)
                            <p class="mt-0.5 text-xs text-hu-gris-medio">
                                {{ $item->descripcionPedidoTipoHemoderivado }}
                            </p>
                        @endif
                    </div>

                    <x-estado tono="info">
                        {{ $item->cantidadPedidoTipoHemoderivado }}
                        {{ str('unidad')->plural($item->cantidadPedidoTipoHemoderivado) }}
                    </x-estado>
                </div>
            @endforeach
        </x-tarjeta>
    @endif

    {{-- Consentimiento --}}
    @if ($consentimiento = $caso->consentimiento())
        <x-tarjeta titulo="Consentimiento informado" icono="draw" class="mt-6">
            <x-slot:acciones>
                <x-estado :tono="$caso->consentimientoFirmado() ? 'exito' : 'aviso'">
                    {{ $caso->consentimientoFirmado() ? 'Firmado' : 'Pendiente de firma' }}
                </x-estado>
            </x-slot:acciones>

            <pre class="max-h-64 overflow-y-auto whitespace-pre-wrap rounded-xl bg-hu-gris-tenue px-4 py-3
                        font-sans text-sm leading-relaxed">{{ $consentimiento->textoRenderizadoConsentimiento }}</pre>

            @if ($consentimiento->hashConsentimiento)
                <p class="mt-3 break-all text-xs text-hu-gris-medio">
                    <span class="font-semibold">SHA-256:</span> {{ $consentimiento->hashConsentimiento }}
                </p>
            @endif
        </x-tarjeta>
    @endif

@endsection
