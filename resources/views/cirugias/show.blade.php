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
                {{ $caso->estaLista() ? 'Listo para operar' : ($caso->semaforo() === 'error' ? 'En riesgo' : $caso->estado()) }}
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
            <x-boton
                variante="contorno"
                forma="grupo"
                icono="schedule"
                tipo="button"
                onclick="document.getElementById('modal-reprogramar').showModal()"
                class="px-3 py-1.5 text-xs"
            >
                Reprogramar
            </x-boton>
            
            <div class="relative" id="contenedor-menu-cirugia">
                <button type="button" id="btn-menu-cirugia" class="flex h-[34px] w-[34px] items-center justify-center rounded-lg border border-hu-gris-suave/80 text-hu-gris-medio hover:border-hu-dorado hover:text-hu-azul transition-colors focus:outline-none bg-white">
                    <x-icono nombre="more_vert" class="text-xl" />
                </button>
                <div id="dropdown-menu-cirugia" style="display: none;" class="absolute right-0 top-full z-50 mt-1 w-56 rounded-xl border border-hu-gris-suave/80 bg-white shadow-xl overflow-hidden py-1">
                    @unless ($caso->cirugia->requiereImplante)
                        <form method="POST" action="{{ route('cirugias.requerimientos.agregar', $caso->cirugia) }}" class="block">
                            @csrf @method('PATCH')
                            <input type="hidden" name="requerimiento" value="implante">
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm hover:bg-hu-gris-tenue/50 text-hu-azul font-semibold">Activar Implante</button>
                        </form>
                    @endunless
                    
                    @if ($caso->cirugia->pedidoHemoderivados->isEmpty())
                        <form method="POST" action="{{ route('cirugias.requerimientos.agregar', $caso->cirugia) }}" class="block">
                            @csrf @method('PATCH')
                            <input type="hidden" name="requerimiento" value="hemoderivados">
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm hover:bg-hu-gris-tenue/50 text-hu-azul font-semibold">Requerir Hemoderivados</button>
                        </form>
                    @endif
                    
                    @if ($caso->cirugia->hisopadoSarms->isEmpty())
                        <form method="POST" action="{{ route('cirugias.requerimientos.agregar', $caso->cirugia) }}" class="block">
                            @csrf @method('PATCH')
                            <input type="hidden" name="requerimiento" value="hisopado">
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm hover:bg-hu-gris-tenue/50 text-hu-azul font-semibold">Requerir Hisopado SAMR</button>
                        </form>
                    @endif

                    @if ($caso->estado() !== 'Cancelada' && $caso->estado() !== 'Cancelado' && $caso->estado() !== 'Suspendida')
                        @if(!$caso->cirugia->pedidoHemoderivados->isEmpty() || !$caso->cirugia->hisopadoSarms->isEmpty() || !$caso->cirugia->requiereImplante === false)
                            <div class="my-1 border-t border-hu-gris-suave/40"></div>
                        @endif
                        <form method="POST" action="{{ route('cirugias.cancelar', $caso->cirugia) }}" class="block" data-confirmar="¿Seguro que deseás cancelar esta cirugía? Se liberará el quirófano y el equipo médico." data-confirmar-titulo="Cancelar Cirugía" data-confirmar-accion="Sí, cancelar">
                            @csrf @method('PATCH')
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm font-bold hover:bg-red-50 text-red-600">Cancelar Cirugía</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('btn-menu-cirugia');
            const menu = document.getElementById('dropdown-menu-cirugia');
            const contenedor = document.getElementById('contenedor-menu-cirugia');
            
            if (btn && menu) {
                btn.addEventListener('click', () => {
                    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                });
                document.addEventListener('click', (e) => {
                    if (!contenedor.contains(e.target)) {
                        menu.style.display = 'none';
                    }
                });
            }
        });
    </script>

    {{-- Modal Reprogramar --}}
    <dialog id="modal-reprogramar" class="m-auto w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-hu-azul">Reprogramar Cirugía</h2>
            <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                <x-icono nombre="close" class="text-2xl" />
            </button>
        </div>

        @php
            $opcionesQuirofanoReprogramar = $quirofanos->mapWithKeys(
                fn ($q) => [$q->idQuirofano => $q->nombreQuirofano],
            );

            $opcionesCoberturaReprogramar = collect();
            if ($caso->plan) {
                $opcionesCoberturaReprogramar->put(
                    'misma',
                    'Mantener la actual ('.$caso->plan->obrasocial?->nombreObraSocial.' - '.$caso->plan->nombrePlan.')',
                );
            }
            $opcionesCoberturaReprogramar->put('particular', 'Particular (Sin cobertura)');
            $opcionesCoberturaReprogramar->put('existente', 'Obra social ya registrada del paciente');
            $opcionesCoberturaReprogramar->put('nueva', 'Nueva obra social');

            $opcionesPlanExistenteReprogramar = $coberturas->mapWithKeys(
                fn ($cob) => [$cob->idPlanObraSocial => $cob->plan->obrasocial?->nombreObraSocial.' - '.$cob->plan->nombrePlan],
            );

            $opcionesPlanNuevoReprogramar = [];
            foreach ($obrasSociales as $os) {
                foreach ($os->planes as $plan) {
                    $opcionesPlanNuevoReprogramar[$plan->idPlan] = $os->nombreObraSocial.' · '.$plan->nombrePlan;
                }
            }
        @endphp

        <form method="POST" action="{{ route('cirugias.reprogramar', $caso->cirugia) }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <x-input tipo="date" nombre="fecha" etiqueta="Fecha" requerido :valor="old('fecha')" />
                <x-select
                    nombre="idQuirofano"
                    etiqueta="Quirófano"
                    :opciones="$opcionesQuirofanoReprogramar"
                    requerido
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-input tipo="time" nombre="hora_inicio" etiqueta="Hora Inicio" requerido :valor="old('hora_inicio')" />
                <x-input tipo="time" nombre="hora_fin" etiqueta="Hora Fin" :valor="old('hora_fin')" />
            </div>

            <x-select
                nombre="cobertura"
                etiqueta="Cobertura"
                :opciones="$opcionesCoberturaReprogramar"
                requerido
                onchange="
                    document.getElementById('div-existente').style.display = this.value === 'existente' ? 'block' : 'none';
                    document.getElementById('div-nueva').style.display = this.value === 'nueva' ? 'block' : 'none';
                "
            />

            <div id="div-existente" style="display: none;">
                <x-select nombre="idPlanObraSocial" etiqueta="Plan existente" :opciones="$opcionesPlanExistenteReprogramar" />
            </div>

            <div id="div-nueva" style="display: none;" class="space-y-4">
                <x-select nombre="idPlan" etiqueta="Obra Social y Plan" :opciones="$opcionesPlanNuevoReprogramar" />
                <x-input nombre="nroBeneficiario" etiqueta="Nº de Afiliado" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                <x-boton tipo="submit" icono="save">Confirmar Reprogramación</x-boton>
            </div>
        </form>
    </dialog>

    @if ($caso->alertaProfilaxis())
        <x-alerta tipo="error" titulo="Alerta clínica" class="mb-6">
            {{ $caso->alertaProfilaxis() }}
        </x-alerta>
    @endif

    {{-- Solapas --}}
    <nav class="mb-6 flex flex-wrap gap-1 border-b border-hu-gris-suave/70" aria-label="Secciones de la cirugía">
        @foreach ([
            'resumen' => ['Resumen', 'check_circle'],
            'preparacion' => ['Preparación', 'no_food'],
            'estudios' => ['Estudios prequirúrgicos', 'science'],
            'materiales' => ['Materiales y presupuesto', 'inventory_2'],
            'hemoderivados' => ['Hemoderivados', 'bloodtype'],
            'profilaxis' => ['Profilaxis ATB / SAMR', 'vaccines'],
            'autorizacion' => ['Autorización financiador', 'shield'],
        ] as $tab => [$etiqueta, $icono])
            @php
                $activa = $tabActivo === $tab;
            @endphp
            <a
                href="{{ route('cirugias.show', [$caso->cirugia, 'tab' => $tab]) }}"
                @if ($activa) aria-current="page" @endif
                class="flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors
                    {{ $activa
                        ? 'border-hu-dorado text-hu-azul'
                        : 'border-transparent text-hu-gris-medio hover:text-hu-azul' }}"
            >
                <x-icono :nombre="$icono" class="text-lg" :relleno="$activa" />
                {{ $etiqueta }}
            </a>
        @endforeach
    </nav>

    {{-- Resumen --}}
    @if ($tabActivo === 'resumen')
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
                                ? $caso->plan->obrasocial?->nombreObraSocial.' · '.$caso->plan->nombrePlan
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
                @if (auth()->user()->tieneRol('Gestor de quirófano', 'Dirección médica'))
                    <x-slot:acciones>
                        <div class="flex gap-2">
                            <x-boton variante="fantasma" icono="history" class="!px-2 !py-1 !text-xs" onclick="document.getElementById('modal-historial-personal').showModal()">Historial</x-boton>
                            <x-boton variante="fantasma" icono="swap_horiz" class="!px-2 !py-1 !text-xs" onclick="abrirModalReasignar('Cirujano')">Cirujano</x-boton>
                            <x-boton variante="fantasma" icono="swap_horiz" class="!px-2 !py-1 !text-xs" onclick="abrirModalReasignar('Anestesista')">Anestesista</x-boton>
                        </div>
                    </x-slot:acciones>
                @endif
                
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
        </div>

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
    @endif

    {{-- Modal Historial Personal --}}
    <dialog id="modal-historial-personal"
            class="m-auto w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl
                   backdrop:bg-black/50 backdrop:backdrop-blur-sm
                   open:animate-in open:fade-in open:zoom-in-95">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-hu-azul">Historial de Asignaciones</h2>
            <button type="button" onclick="document.getElementById('modal-historial-personal').close()"
                    class="rounded-full p-1 text-hu-gris-medio hover:bg-hu-gris-suave/50 hover:text-hu-azul transition-colors">
                <x-icono nombre="close" />
            </button>
        </div>

        <div class="overflow-x-auto text-sm">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-hu-gris-suave/60 text-xs uppercase tracking-wider text-hu-gris-medio">
                        <th class="py-2 pr-4 font-semibold">Profesional</th>
                        <th class="py-2 pr-4 font-semibold">Rol</th>
                        <th class="py-2 pr-4 font-semibold">Inicio</th>
                        <th class="py-2 font-semibold">Fin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hu-gris-suave/40">
                    @forelse ($caso->cirugia->cirugiaPersonales()->orderByDesc('fechaInicioAsignacionCirugiaPersonal')->get() as $historial)
                        <tr class="hover:bg-hu-gris-tenue/30 transition-colors">
                            <td class="py-2.5 pr-4 font-semibold text-hu-azul">
                                {{ $historial->personal?->persona?->nombre_completo ?? 'Desconocido' }}
                            </td>
                            <td class="py-2.5 pr-4 text-hu-gris-medio">
                                {{ $historial->rol?->nombreRol ?? 'Desconocido' }}
                            </td>
                            <td class="py-2.5 pr-4 text-hu-gris-medio">
                                {{ $historial->fechaInicioAsignacionCirugiaPersonal?->format('d/m/Y H:i') ?? '-' }}
                            </td>
                            <td class="py-2.5">
                                @if($historial->fechaFinAsignacionCirugiaPersonal)
                                    <span class="text-hu-gris-medio">{{ $historial->fechaFinAsignacionCirugiaPersonal->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Actual</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-hu-gris-medio">No hay historial de asignaciones registrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </dialog>

    {{-- Modal Reasignar Personal --}}
    @if (auth()->user()->tieneRol('Gestor de quirófano', 'Dirección médica'))
    <dialog id="modal-reasignar"
            class="m-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl
                   backdrop:bg-black/50 backdrop:backdrop-blur-sm
                   open:animate-in open:fade-in open:zoom-in-95">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-hu-azul" id="titulo-modal-reasignar">Reasignar Profesional</h2>
            <button type="button" onclick="document.getElementById('modal-reasignar').close()"
                    class="rounded-full p-1 text-hu-gris-medio hover:bg-hu-gris-suave/50 hover:text-hu-azul transition-colors">
                <x-icono nombre="close" />
            </button>
        </div>

        <form method="POST" action="{{ route('cirugias.personal.reasignar', $caso->cirugia) }}">
            @csrf
            @method('PATCH')
            
            <input type="hidden" name="rol" id="input-rol-reasignar">
            
            <div class="space-y-4">
                <p class="text-sm text-hu-gris-medio">
                    Seleccione un profesional disponible. Solo se muestran los profesionales que <strong class="font-bold">no tienen otra cirugía programada</strong> en este mismo horario.
                </p>

                <div>
                    <label for="select-personal-reasignar" class="mb-1 block text-sm font-semibold text-hu-azul">
                        Profesional Disponible
                    </label>
                    <select name="idPersonal" id="select-personal-reasignar" required
                            class="w-full rounded-xl border border-hu-gris-suave/80 bg-hu-gris-tenue/20 px-3 py-2
                                   text-sm outline-none transition-colors focus:border-hu-dorado focus:bg-white focus:ring-1 focus:ring-hu-dorado disabled:opacity-50">
                        <option value="">Cargando profesionales...</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-hu-gris-suave/60 pt-4">
                <x-boton type="button" variante="fantasma" onclick="document.getElementById('modal-reasignar').close()">
                    Cancelar
                </x-boton>
                <x-boton type="submit" icono="save">
                    Guardar Cambios
                </x-boton>
            </div>
        </form>
    </dialog>
    <script>
        function abrirModalReasignar(rol) {
            document.getElementById('titulo-modal-reasignar').innerText = 'Reasignar ' + rol;
            document.getElementById('input-rol-reasignar').value = rol;
            
            const select = document.getElementById('select-personal-reasignar');
            select.innerHTML = '<option value="">Cargando profesionales libres...</option>';
            select.disabled = true;

            document.getElementById('modal-reasignar').showModal();

            fetch(`{{ route('cirugias.personal.disponible', $caso->cirugia) }}?rol=${rol}`)
                .then(res => res.json())
                .then(data => {
                    select.disabled = false;
                    if (data.length === 0) {
                        select.innerHTML = '<option value="">No hay profesionales disponibles en este horario</option>';
                    } else {
                        select.innerHTML = '<option value="">Seleccione un profesional...</option>';
                        data.forEach(p => {
                            select.innerHTML += `<option value="${p.id}">${p.nombre}</option>`;
                        });
                    }
                })
                .catch(err => {
                    select.innerHTML = '<option value="">Error al cargar datos</option>';
                    console.error(err);
                });
        }
    </script>
    @endif

    {{-- Preparación para la cirugía --}}
    @if ($tabActivo === 'preparacion')
        @php
            $preparacionActual = $caso->preparacion();
        @endphp

        {{-- Modal Editar Preparación --}}
        @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
        <dialog id="modal-preparacion"
                class="m-auto w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl
                       backdrop:bg-black/50 backdrop:backdrop-blur-sm
                       open:animate-in open:fade-in open:zoom-in-95">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Indicaciones de preparación</h2>
                <button type="button" onclick="this.closest('dialog').close()"
                        class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form action="{{ route('cirugias.preparacion.guardar', $caso->cirugia) }}" method="POST">
                @csrf

                {{-- Bloques de indicaciones --}}
                @php
                    // Armar mapa de indicaciones actuales: [idTipoPreparacion][idTipoIndicacion] = horas
                    $activasMap = [];
                    foreach ($caso->cirugia->preparacionPacientes->first()?->preparacionPacienteTipoPreparaciones ?? [] as $bloque) {
                        foreach ($bloque->preparacionPacienteTipoPreparacionTipoIndicaciones as $ind) {
                            $activasMap[$bloque->idTipoPreparacion][$ind->idTipoIndicacion] = $ind->hsReglaCantidadIngestaAnteriorCirugia;
                        }
                    }

                    // Agrupar TipoIndicacion por TipoPreparacion según el catálogo del seeder.
                    // Todos los TipoIndicacion se muestran bajo cada TipoPreparacion (el usuario elige cuáles aplican).
                    // En producción podría haber una tabla de relación; por ahora mostramos todos debajo de cada bloque.
                    $indicacionesPorBloque = [
                        'Ayuno'                  => ['Sólidos', 'Líquidos claros', 'Tabaco'],
                        'Higiene prequirúrgica'  => ['Ducha con antiséptico'],
                        'Medicación habitual'    => ['Medicación habitual'],
                        'Documentación'          => ['Documentación'],
                        'Otro'                   => [],
                    ];
                @endphp

                <div class="space-y-5 max-h-[60vh] overflow-y-auto pr-1">
                    @foreach ($tiposPreparacion as $tipoPrep)
                        @php
                            $indicacionesDeEsteBloque = $tiposIndicacion;
                        @endphp
                        <fieldset class="rounded-xl border border-hu-gris-suave/70 p-4">
                            <legend class="px-1 text-xs font-bold uppercase tracking-wider text-hu-dorado-oscuro">
                                {{ $tipoPrep->nombreTipoPreparacion }}
                            </legend>

                            <div class="mt-2 space-y-3">
                                @foreach ($tiposIndicacion as $tipoInd)
                                    @php
                                        $estaActiva  = isset($activasMap[$tipoPrep->idTipoPreparacion][$tipoInd->idTipoIndicacion]);
                                        $horasActual = $activasMap[$tipoPrep->idTipoPreparacion][$tipoInd->idTipoIndicacion] ?? '';
                                        $checkId     = 'chk_'.$tipoPrep->idTipoPreparacion.'_'.$tipoInd->idTipoIndicacion;
                                        $inputName   = 'indicaciones['.$tipoPrep->idTipoPreparacion.']['.$tipoInd->idTipoIndicacion.']';
                                    @endphp
                                    <div class="flex items-center gap-4">
                                        {{-- Checkbox activa/desactiva la indicación --}}
                                        <label for="{{ $checkId }}"
                                               class="flex flex-1 cursor-pointer items-center gap-2 text-sm text-hu-azul">
                                            <input
                                                type="checkbox"
                                                id="{{ $checkId }}"
                                                class="prep-check size-4 cursor-pointer accent-hu-dorado-oscuro"
                                                data-target="horas_{{ $tipoPrep->idTipoPreparacion }}_{{ $tipoInd->idTipoIndicacion }}"
                                                {{ $estaActiva ? 'checked' : '' }}
                                            >
                                            {{ $tipoInd->nombreTipoIndicacion }}
                                        </label>

                                        {{-- Horas antes de la cirugía --}}
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <input
                                                type="number"
                                                id="horas_{{ $tipoPrep->idTipoPreparacion }}_{{ $tipoInd->idTipoIndicacion }}"
                                                name="{{ $inputName }}"
                                                min="0"
                                                max="999"
                                                placeholder="—"
                                                value="{{ $horasActual }}"
                                                {{ $estaActiva ? '' : 'disabled' }}
                                                class="w-16 rounded-lg border border-hu-gris-suave px-2 py-1 text-center text-sm
                                                       focus:border-hu-dorado-oscuro focus:outline-none
                                                       disabled:opacity-30 disabled:cursor-not-allowed"
                                            >
                                            <span class="text-xs text-hu-gris-medio">hs antes</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    {{-- Observaciones generales --}}
                    <div>
                        <label for="obsPreparacion" class="block text-xs font-semibold uppercase tracking-wider text-hu-gris-medio mb-1">
                            Observaciones generales
                        </label>
                        <textarea
                            id="obsPreparacion"
                            name="observacionesPreparacionPaciente"
                            rows="2"
                            maxlength="255"
                            placeholder="Ej: Preparación estándar prequirúrgica."
                            class="w-full rounded-xl border border-hu-gris-suave px-3 py-2 text-sm
                                   focus:border-hu-dorado-oscuro focus:outline-none resize-none"
                        >{{ $caso->cirugia->preparacionPacientes->first()?->observacionesPreparacionPaciente }}</textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <x-boton tipo="button" variante="fantasma" forma="grupo"
                             onclick="this.closest('dialog').close()">
                        Cancelar
                    </x-boton>
                    <x-boton tipo="submit" variante="primario" forma="grupo" icono="save">
                        Guardar preparación
                    </x-boton>
                </div>
            </form>
        </dialog>

        @endif

        @php
            $puedeEditarPreparacion = auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano');
        @endphp
        <section class="mt-6 rounded-2xl border border-hu-gris-suave/70 bg-white shadow-sm">
            <header class="flex items-center justify-between gap-4 border-b border-hu-gris-suave/70 px-5 py-4">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-hu-azul">
                    <x-icono nombre="no_food" class="text-xl text-hu-dorado" relleno />
                    Preparación para la cirugía
                </h2>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @if(! $puedeEditarPreparacion) style="display:none" @endif
                        onclick="document.getElementById('modal-preparacion').showModal()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-hu-gris-suave px-3 py-1.5
                               text-xs font-semibold text-hu-azul hover:bg-hu-gris-tenue transition-colors"
                    >
                        <x-icono nombre="edit" class="text-sm" />
                        Editar preparación
                    </button>
                </div>
            </header>

            <div class="px-5 py-4">
                @if ($preparacionActual->isNotEmpty())
                @if ($caso->cuando())
                    <p class="mb-3 text-sm text-hu-gris-medio">
                        Contado hacia atrás desde las
                        <strong class="text-hu-azul">{{ $caso->cuando()->format('H:i') }} hs</strong>
                        del {{ $caso->cuando()->translatedFormat('l j/m') }}.
                    </p>
                @endif

                @foreach ($preparacionActual->groupBy('bloque') as $bloque => $items)
                    <p class="titulo-corto mt-3 text-xs text-hu-dorado-oscuro first:mt-0">{{ $bloque }}</p>
                    <ul class="mt-1 space-y-1.5">
                        @foreach ($items as $item)
                            <li class="flex items-center justify-between gap-3 text-sm border-b border-hu-gris-suave/40 py-2 last:border-0">
                                <span>{{ $item['indicacion'] }}</span>
                                @if ($item['horas'])
                                    <span class="shrink-0 text-right">
                                        <span class="font-semibold text-hu-azul">
                                            {{ $caso->cuando()?->copy()->subHours($item['horas'])->translatedFormat('D H:i') }} hs
                                        </span>
                                        <span class="block text-xs text-hu-gris-medio">{{ $item['horas'] }} hs antes</span>
                                    </span>
                                @endif
                                @if (! $item['horas'])
                                    <x-estado tono="neutro">Sin hora definida</x-estado>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach

                @if ($observaciones = $caso->cirugia->preparacionPacientes->first()?->observacionesPreparacionPaciente)
                    <p class="mt-3 rounded-xl bg-hu-gris-tenue px-3 py-2 text-xs text-hu-gris-medio">
                        {{ $observaciones }}
                    </p>
                @endif
            @endif

            @if ($preparacionActual->isEmpty())
                <p class="py-6 text-center text-sm text-hu-gris-medio">
                    Sin indicaciones de preparación cargadas.
                    <button type="button"
                            @if(! $puedeEditarPreparacion) style="display:none" @endif
                            onclick="document.getElementById('modal-preparacion').showModal()"
                            class="ml-1 font-semibold text-hu-dorado-oscuro underline underline-offset-2 hover:text-hu-azul">
                        Agregar ahora
                    </button>
                </p>
            @endif
            </div>
        </section>
    @endif




    {{-- Estudios prequirúrgicos --}}
    @if ($tabActivo === 'estudios')
        <x-tarjeta titulo="Estudios prequirúrgicos" icono="science">
            <x-slot:acciones>
                @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano', 'Anestesista'))
                    <x-boton tipo="button" variante="contorno" class="!px-3 !py-1.5 !text-xs" onclick="document.getElementById('modal-agregar-estudio').showModal()">
                        <x-icono nombre="add" class="text-sm" /> Agregar estudio
                    </x-boton>
                @endif
            </x-slot:acciones>

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

                    <div class="flex items-center gap-1.5">
                        <x-estado :tono="$estudio->fechaSubidaCirugiaTipoEstudio ? 'exito' : 'aviso'">
                            {{ $estudio->fechaSubidaCirugiaTipoEstudio ? 'Subido' : 'Pendiente' }}
                        </x-estado>
                        
                        @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano', 'Anestesista'))
                            {{-- Botón Subir Archivo --}}
                            <form 
                                action="{{ route('cirugias.estudios.update', [$caso->cirugia, $estudio]) }}" 
                                method="POST" 
                                enctype="multipart/form-data" 
                                class="inline-flex"
                            >
                                @csrf
                                @method('PATCH')
                                <input 
                                    type="file" 
                                    name="archivoResultadoEstudio" 
                                    id="file-upload-{{ $estudio->idCirugiaTipoEstudio }}" 
                                    class="hidden" 
                                    accept=".pdf,image/jpeg,image/png"
                                    onchange="this.form.submit()"
                                >
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-full text-hu-gris-medio transition-colors hover:bg-hu-gris-tenue hover:text-hu-azul"
                                    title="Subir resultado"
                                    onclick="document.getElementById('file-upload-{{ $estudio->idCirugiaTipoEstudio }}').click()"
                                >
                                    <x-icono nombre="upload_file" class="text-[18px]" />
                                </button>
                            </form>

                            {{-- Botón Ver Archivo --}}
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full transition-colors {{ $estudio->fechaSubidaCirugiaTipoEstudio ? 'text-hu-azul hover:bg-hu-azul-tenue' : 'text-hu-gris-suave cursor-not-allowed' }}"
                                title="{{ $estudio->fechaSubidaCirugiaTipoEstudio ? 'Ver resultado' : 'Resultado no subido' }}"
                                @if(! $estudio->fechaSubidaCirugiaTipoEstudio) disabled @endif
                                onclick="alert('Se abrirá el visor del gestor documental externo.')"
                            >
                                <x-icono nombre="visibility" class="text-[18px]" />
                            </button>

                            {{-- Botón Gestionar / Editar datos --}}
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full text-hu-gris-medio transition-colors hover:bg-hu-gris-tenue hover:text-hu-azul"
                                title="Editar datos del estudio"
                                onclick="abrirModalEstudio(
                                    '{{ $estudio->idCirugiaTipoEstudio }}',
                                    '{{ $estudio->tipoEstudio?->nombreTipoEstudio }}',
                                    '{{ $estudio->fechaEsperadaResultadoCirugiaTipoEstudio?->format('Y-m-d') }}',
                                    '{{ $estudio->resultadoCirugiaTipoEstudio }}'
                                )"
                            >
                                <x-icono nombre="edit" class="text-[18px]" />
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-hu-gris-medio">Sin estudios indicados.</p>
            @endforelse
        </x-tarjeta>
    @endif

    {{-- Materiales y presupuesto --}}
    @if ($tabActivo === 'materiales')
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-hu-azul">Materiales y presupuesto</h2>
            @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
                <x-boton tipo="button" onclick="document.getElementById('modal-agregar-material').showModal()">
                    <x-icono nombre="add" class="text-sm" /> Agregar material
                </x-boton>
            @endif
        </div>

        @if ($caso->requiereMateriales())
            <x-tarjeta class="!p-0 overflow-hidden">
                <div class="flex items-center justify-between border-b border-hu-gris-suave/60 bg-hu-gris-tenue/50 px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-8 items-center justify-center rounded-full bg-white text-hu-azul shadow-sm">
                            <x-icono nombre="inventory_2" class="text-lg" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-hu-azul">Detalle de presupuesto</h3>
                        </div>
                    </div>
                    <x-estado :tono="$caso->materialesAprobados() ? 'exito' : 'aviso'">
                        {{ $caso->materiales() }}
                    </x-estado>
                </div>

                <div class="-mx-5 overflow-x-auto px-5 pt-3 pb-5">
                    <table class="w-full min-w-160 text-sm">
                        <thead>
                            <tr class="border-b border-hu-gris-suave/70 text-left text-xs uppercase tracking-wide text-hu-gris-medio">
                                <th class="px-5 pb-2 font-semibold">Material</th>
                                <th class="px-3 pb-2 font-semibold">Proveedor</th>
                                <th class="px-3 pb-2 font-semibold">Cantidad</th>
                                <th class="px-5 pb-2 text-right font-semibold">Subtotal</th>
                                @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
                                    <th class="px-5 pb-2 text-right font-semibold"></th>
                                @endif
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
                                    @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
                                        <td class="px-5 py-2.5 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" onclick="document.getElementById('modal-editar-material-{{ $pedido->idPedidoMaterial }}').showModal()" class="text-hu-gris-medio hover:text-hu-azul transition-colors" title="Editar cantidad">
                                                    <x-icono nombre="edit" class="text-lg" />
                                                </button>
                                                
                                                <form action="{{ route('cirugias.materiales.destroy', [$caso->cirugia, $pedido]) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Está seguro de eliminar este material del presupuesto?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-hu-gris-medio hover:text-red-500 transition-colors" title="Eliminar">
                                                        <x-icono nombre="delete" class="text-lg" />
                                                    </button>
                                                </form>
                                            </div>

                                            <dialog id="modal-editar-material-{{ $pedido->idPedidoMaterial }}" class="m-auto w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95">
                                                <div class="mb-4 flex items-center justify-between">
                                                    <h2 class="text-lg font-bold text-hu-azul">Editar cantidad</h2>
                                                    <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                                                        <x-icono nombre="close" class="text-2xl" />
                                                    </button>
                                                </div>

                                                <form method="POST" action="{{ route('cirugias.materiales.update', [$caso->cirugia, $pedido]) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="mb-3">
                                                        <p class="text-sm font-semibold text-hu-azul">{{ $pedido->material?->nombreMaterial }}</p>
                                                        <p class="text-xs text-hu-gris-medio">{{ $pedido->proveedor?->nombreProveedor }}</p>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-semibold text-hu-azul mb-1">Nueva Cantidad</label>
                                                        <input type="number" name="cantidadPedidoMaterial" min="1" value="{{ $pedido->cantidadPedidoMaterial }}" required class="w-full rounded-md border-hu-gris-suave px-3 py-2 text-sm focus:border-hu-azul focus:ring-hu-azul">
                                                    </div>
                                                    <div class="mt-6 flex justify-end gap-3">
                                                        <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                                                        <x-boton tipo="submit" icono="save">Guardar</x-boton>
                                                    </div>
                                                </form>
                                            </dialog>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-hu-azul/20">
                                <td colspan="{{ auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano') ? 4 : 3 }}" class="px-5 pt-3 text-right text-sm font-semibold">Total</td>
                                <td class="px-5 pt-3 text-right text-base font-black text-hu-azul">
                                    USD {{ number_format($caso->importeMateriales(), 2, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-tarjeta>
        @else
            <x-tarjeta>
                <p class="py-6 text-center text-sm text-hu-gris-medio">Esta cirugía no tiene materiales asociados.</p>
            </x-tarjeta>
        @endif
        
        <dialog id="modal-agregar-material" class="m-auto w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Agregar Material</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form method="POST" action="{{ route('cirugias.materiales.store', $caso->cirugia) }}" class="space-y-4">
                @csrf
                
                <div>
                    <label class="block text-sm font-semibold text-hu-azul mb-1">Buscar Material</label>
                    <input type="text" id="buscador-material" placeholder="Escriba para buscar..." autocomplete="off" class="w-full rounded-md border-hu-gris-suave px-3 py-2 text-sm focus:border-hu-azul focus:ring-hu-azul">
                    <div id="resultados-materiales" class="absolute z-10 w-full max-w-md bg-white border border-hu-gris-suave rounded-md shadow-lg max-h-60 overflow-y-auto hidden"></div>
                </div>

                <div id="material-seleccionado-info" class="hidden rounded-lg bg-hu-gris-tenue p-3 border border-hu-gris-suave/60 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-hu-azul" id="mat-nombre"></p>
                        <p class="text-xs text-hu-gris-medio" id="mat-codigo"></p>
                    </div>
                    <button type="button" class="text-hu-gris-medio hover:text-red-500" onclick="limpiarMaterial()">
                        <x-icono nombre="close" class="text-lg" />
                    </button>
                </div>
                
                <input type="hidden" name="idMaterial" id="idMaterial">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-hu-azul mb-1">Proveedor</label>
                        <select name="idProveedor" id="select-proveedor" required disabled class="w-full rounded-md border-hu-gris-suave px-3 py-2 text-sm focus:border-hu-azul focus:ring-hu-azul">
                            <option value="">Seleccione material primero</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-hu-azul mb-1">Tipo de Medida</label>
                        <select name="idTipoMedida" id="select-medida" required disabled class="w-full rounded-md border-hu-gris-suave px-3 py-2 text-sm focus:border-hu-azul focus:ring-hu-azul">
                            <option value="">Seleccione proveedor primero</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-hu-azul mb-1">Cantidad</label>
                    <input type="number" name="cantidadPedidoMaterial" min="1" value="1" required class="w-full rounded-md border-hu-gris-suave px-3 py-2 text-sm focus:border-hu-azul focus:ring-hu-azul">
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" icono="save" id="btn-submit-material" disabled class="opacity-50 cursor-not-allowed">Agregar</x-boton>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Hemoderivados --}}
    @if ($tabActivo === 'hemoderivados')
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-hu-azul">Pedidos de hemoderivados</h2>
            @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano', 'Anestesista'))
                <x-boton tipo="button" onclick="abrirModalNuevoPedido()">
                    <x-icono nombre="add" class="text-sm" /> Nuevo pedido
                </x-boton>
            @endif
        </div>

        <div class="space-y-6">
            @forelse ($caso->pedidosHemoderivados() as $pedido)
                @php
                    $estadoActual = $pedido->pedidoHemoderivadoEstados->last()?->estadoPedidoHemoderivado?->nombreEstadoPedidoHemoderivado ?? 'Sin estado';
                    $colorEstado = match ($estadoActual) {
                        'Solicitado' => 'info',
                        'Confirmado' => 'exito',
                        'En preparación' => 'aviso',
                        'Anulado' => 'error',
                        default => 'info',
                    };
                @endphp
                <x-tarjeta class="!p-0 overflow-hidden">
                    {{-- Header del pedido --}}
                    <div class="flex items-center justify-between border-b border-hu-gris-suave/60 bg-hu-gris-tenue/50 px-5 py-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-8 items-center justify-center rounded-full bg-white text-hu-azul shadow-sm">
                                <x-icono nombre="bloodtype" class="text-lg" />
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-hu-azul">
                                    Pedido del {{ $pedido->fechaPedidoHemoderivado->translatedFormat('d \d\e F, Y \a \l\a\s H:i') }}
                                </h3>
                                @if ($pedido->observacionesPedidoHemoderivados)
                                    <p class="text-xs text-hu-gris-medio mt-0.5">{{ $pedido->observacionesPedidoHemoderivados }}</p>
                                @endif
                            </div>
                        </div>
                        <x-estado :tono="$colorEstado">
                            {{ $estadoActual }}
                        </x-estado>
                    </div>

                    {{-- Lista de componentes --}}
                    <div class="px-5 py-2">
                        @forelse ($pedido->pedidoTipoHemoderivados as $item)
                            <div class="flex items-center justify-between border-b border-hu-gris-suave/60 py-3 last:border-0">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-hu-azul">
                                        {{ $item->tipoHemoderivado?->nombreTipoHemoderivado }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-hu-gris-medio">
                                        {{ $item->establecimiento?->nombreEstablecimiento }}
                                    </p>
                                    @if ($item->descripcionPedidoTipoHemoderivado)
                                        <p class="mt-1 text-xs text-hu-gris-medio italic">
                                            "{{ $item->descripcionPedidoTipoHemoderivado }}"
                                        </p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <x-estado tono="info" class="mb-1 inline-flex">
                                        {{ $item->cantidadPedidoTipoHemoderivado }} {{ str('unidad')->plural($item->cantidadPedidoTipoHemoderivado) }}
                                    </x-estado>
                                    @php
                                        $estadoItemObj = $item->pedidoTipoHemoderivadoEstados()->latest('idPedidoTipoHemoderivadoEstado')->first();
                                        $estadoItemId = $estadoItemObj?->idEstadoPedidoTipoHemoderivado;
                                        $estadoItemStr = $estadoItemObj?->estadoPedidoTipoHemoderivado?->nombreEstadoPedidoTipoHemoderivado ?? 'Solicitado';
                                    @endphp
                                    @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano', 'Anestesista'))
                                        <form action="{{ route('cirugias.hemoderivados.componente.estado', [$caso->cirugia, $item]) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <select
                                                name="idEstadoPedidoTipoHemoderivado"
                                                onchange="this.form.submit()"
                                                class="mt-1 block w-[110px] rounded-md border-hu-gris-suave px-2 py-1 text-[11px] font-bold text-hu-gris-medio uppercase tracking-wider focus:border-hu-azul focus:ring-hu-azul bg-hu-gris-tenue/20 cursor-pointer"
                                            >
                                                @foreach ($estadosHemoderivados as $id => $nombre)
                                                    <option value="{{ $id }}" @selected($id == $estadoItemId || ($estadoItemId === null && $nombre === 'Solicitado'))>
                                                        {{ $nombre }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <p class="mt-2 text-[11px] font-semibold text-hu-gris-medio uppercase tracking-wider">{{ $estadoItemStr }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-hu-gris-medio">No hay componentes en este pedido.</p>
                        @endforelse
                    </div>

                    {{-- Footer del pedido (Agregar componente) --}}
                    {{-- Removido para asegurar la trazabilidad del pedido en el tiempo --}}
                </x-tarjeta>
            @empty
                <x-tarjeta titulo="Reserva de hemoderivados" icono="bloodtype">
                    <p class="py-6 text-center text-sm text-hu-gris-medio">Sin reserva de hemoderivados indicada.</p>
                </x-tarjeta>
            @endforelse
        </div>
    @endif

    {{-- Profilaxis ATB / SAMR --}}
    @if ($tabActivo === 'profilaxis')
        @php
            $hisopado = $caso->hisopadoSarm();
            $tonoHisopado = match ($hisopado['estado'] ?? null) {
                'Negativo' => 'exito',
                'Positivo' => 'error',
                default => 'aviso',
            };
        @endphp

        <div class="grid gap-5 lg:grid-cols-2">
            <x-tarjeta titulo="Hisopado SAMR" icono="science">
                @if ($hisopado)
                    <x-slot:acciones>
                        @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
                            <button
                                type="button"
                                onclick="document.getElementById('modal-hisopado').showModal()"
                                class="rounded-lg p-1 text-hu-gris-medio transition hover:bg-hu-gris-suave hover:text-hu-azul"
                                title="Editar datos del hisopado"
                            >
                                <x-icono nombre="edit" class="text-xl" />
                            </button>
                        @endif
                    </x-slot:acciones>

                    <dl class="divide-y divide-hu-gris-suave/60 text-sm">
                        <div class="flex items-center justify-between gap-3 py-2.5 first:pt-0">
                            <dt class="text-hu-gris-medio">Estado</dt>
                            <dd><x-estado :tono="$tonoHisopado">{{ $hisopado['estado'] }}</x-estado></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <dt class="text-hu-gris-medio">Solicitado</dt>
                            <dd class="font-semibold text-hu-azul">
                                {{ $hisopado['fechaSolicitacion']?->translatedFormat('j/m') ?? 's/d' }}
                            </dd>
                        </div>
                        @if ($hisopado['fechaEstimada'])
                            <div class="flex items-center justify-between gap-3 py-2.5">
                                <dt class="text-hu-gris-medio">Resultado esperado</dt>
                                <dd class="font-semibold text-hu-azul">
                                    {{ $hisopado['fechaEstimada']->translatedFormat('j/m') }}
                                </dd>
                            </div>
                        @endif
                        @if ($hisopado['establecimiento'])
                            <div class="flex items-center justify-between gap-3 py-2.5">
                                <dt class="text-hu-gris-medio">Laboratorio</dt>
                                <dd class="font-semibold text-hu-azul">{{ $hisopado['establecimiento'] }}</dd>
                            </div>
                        @endif
                        @if ($hisopado['observaciones'])
                            <div class="flex items-start justify-between gap-3 py-2.5 last:pb-0">
                                <dt class="text-hu-gris-medio">Observaciones</dt>
                                <dd class="max-w-[60%] text-right text-hu-azul">{{ $hisopado['observaciones'] }}</dd>
                            </div>
                        @endif
                    </dl>

                    {{-- Botones de resultado: solo visibles si está Pendiente y el usuario puede registrar --}}
                    @if ($hisopado['estado'] === 'Pendiente' && auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
                        <div class="mt-4 flex gap-2 border-t border-hu-gris-suave/60 pt-4">
                            <button
                                type="button"
                                onclick="abrirModalResultado('Negativo')"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-green-700 px-4 py-2 text-sm font-semibold text-green-700 transition hover:bg-green-50"
                            >
                                <x-icono nombre="check_circle" class="text-lg" />
                                Negativo
                            </button>
                            <button
                                type="button"
                                onclick="abrirModalResultado('Positivo')"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-red-700 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50"
                            >
                                <x-icono nombre="cancel" class="text-lg" />
                                Positivo
                            </button>
                        </div>
                    @endif
                @else
                    <p class="py-6 text-center text-sm text-hu-gris-medio">Sin hisopado solicitado.</p>
                @endif
            </x-tarjeta>

            <x-tarjeta titulo="Profilaxis antibiótica" icono="vaccines">
                <x-slot:acciones>
                    @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
                        <button
                            type="button"
                            onclick="document.getElementById('modal-agregar-profilaxis').showModal()"
                            class="rounded-lg p-1 text-hu-gris-medio transition hover:bg-hu-gris-suave hover:text-hu-azul"
                            title="Agregar profilaxis"
                        >
                            <x-icono nombre="add" class="text-xl" />
                        </button>
                    @endif
                </x-slot:acciones>

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
                        @if ($item->indicacionesProfilaxisAtbHisopadoSarmProfilaxis)
                            <p class="mt-0.5 text-xs text-hu-gris-medio">
                                {{ $item->indicacionesProfilaxisAtbHisopadoSarmProfilaxis }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-hu-gris-medio">Sin profilaxis indicada.</p>
                @endforelse
            </x-tarjeta>
        </div>
    @endif

    {{-- Autorización financiador --}}
    @if ($tabActivo === 'autorizacion')
        @php
            $autCirugia = $caso->cirugia->autCirugias->first();
            $historial = $caso->historialAutorizacion();
        @endphp
        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Columna izquierda: Timeline --}}
            <div class="lg:col-span-2 space-y-5">
                <x-tarjeta titulo="Timeline autorización" icono="monitoring">
                    <x-slot:acciones>
                        @if (auth()->user()->tieneRol('Gestor de quirófano', 'Cirujano'))
                            <x-boton tipo="button" variante="contorno" class="!px-3 !py-1.5 !text-xs" onclick="document.getElementById('modal-estado-autorizacion').showModal()">
                                Actualizar estado
                            </x-boton>
                        @endif
                    </x-slot:acciones>

                    @if ($historial->isEmpty())
                        <p class="py-6 text-center text-sm text-hu-gris-medio">Sin trámite de autorización iniciado.</p>
                    @else
                        <div class="relative border-l border-hu-gris-suave/80 ml-3 mt-2 space-y-6 pb-2">
                            @foreach ($historial as $index => $item)
                                @php
                                    $esActual = $index === 0;
                                    $estado = $item->estadoAutCirugia?->nombreEstadoAutCirugia;
                                    
                                    $color = match($estado) {
                                        'Aprobada' => 'bg-green-500',
                                        'Rechazada' => 'bg-red-500',
                                        'En auditoría médica' => 'bg-yellow-500',
                                        default => 'bg-hu-gris-medio',
                                    };
                                    $iconName = match($estado) {
                                        'Aprobada' => 'check_circle',
                                        'Rechazada' => 'cancel',
                                        'En auditoría médica' => 'pending',
                                        default => 'schedule',
                                    };
                                @endphp
                                <div class="relative pl-6">
                                    <span class="absolute -left-[13px] top-0.5 flex h-6 w-6 items-center justify-center rounded-full {{ $color }} text-white ring-4 ring-white">
                                        <x-icono :nombre="$iconName" class="text-sm" />
                                    </span>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-semibold text-hu-gris-medio uppercase tracking-wider">
                                            {{ $item->fechaInicioAutoCirugiaEstado->translatedFormat('D d/m/Y - H:i') }}
                                        </span>
                                        <h3 class="mt-0.5 text-sm font-bold {{ $esActual ? 'text-hu-azul' : 'text-hu-gris' }}">
                                            {{ $estado }}
                                        </h3>
                                        @if ($item->observacionesAutoCirugiaEstado)
                                            <p class="mt-1 text-sm text-hu-gris-medio">
                                                {{ $item->observacionesAutoCirugiaEstado }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-tarjeta>
            </div>

            {{-- Columna derecha: Datos --}}
            <div class="space-y-5">
                <x-tarjeta titulo="Datos del trámite" icono="description">
                    <x-slot:acciones>
                        <x-estado :tono="$caso->autorizacionAprobada() ? 'exito' : 'aviso'">
                            {{ $caso->autorizacion() }}
                        </x-estado>
                    </x-slot:acciones>
        
                    <dl class="divide-y divide-hu-gris-suave/60 text-sm">
                        @php
                            $datos = [
                                'Plan / obra social' => $caso->plan
                                    ? $caso->plan->obrasocial?->nombreObraSocial.' · '.$caso->plan->nombrePlan
                                    : null,
                                'N° de autorización' => $caso->nroAutorizacion(),
                                'Fecha límite de envío' => $autCirugia?->fechaLimiteEnvioAutorizacion?->translatedFormat('j/m/Y'),
                                'Enviada el' => $autCirugia?->fechaHoraEnvioAutorizacionAutCirugia?->translatedFormat('j/m/Y H:i'),
                                'Verificada el' => $autCirugia?->fechaHoraVerificacionAutCirugia?->translatedFormat('j/m/Y H:i'),
                            ];
                        @endphp
        
                        @forelse (array_filter($datos, fn ($v) => trim((string) $v) !== '') as $etiqueta => $valor)
                            <div class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                                <dt class="text-xs font-semibold text-hu-gris-medio">{{ $etiqueta }}</dt>
                                <dd class="text-sm font-semibold text-hu-azul">{{ $valor }}</dd>
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-hu-gris-medio">Sin trámite de autorización iniciado.</p>
                        @endforelse
                    </dl>
                </x-tarjeta>
            </div>
        </div>
    @endif

    {{-- Modal editar hisopado SAMR --}}
    @php $hisopadoModal = $caso->hisopadoSarm(); @endphp
    @if ($hisopadoModal)
        <dialog
            id="modal-hisopado"
            class="m-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Editar hisopado SAMR</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form method="POST" action="{{ route('cirugias.hisopado.actualizar', $caso->cirugia) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-select
                    nombre="idEstablecimiento"
                    etiqueta="Laboratorio"
                    :opciones="$establecimientosHisopado"
                    :valor="$caso->cirugia->hisopadoSarms->first()?->idEstablecimiento"
                    placeholder="Sin asignar"
                />

                <x-input
                    tipo="date"
                    nombre="fechaEstimadaResultadosHisopadoSarm"
                    etiqueta="Resultado esperado"
                    :valor="$hisopadoModal['fechaEstimada']?->format('Y-m-d')"
                />

                <x-input
                    nombre="observacionesHisopadoSarm"
                    etiqueta="Observaciones"
                    :valor="$hisopadoModal['observaciones']"
                    placeholder="Aclaraciones o indicaciones adicionales"
                />

                <div class="border-t border-hu-gris-suave/60 pt-4">
                    <x-input
                        tipo="file"
                        nombre="archivoHisopadoSarm"
                        etiqueta="Archivo adjunto (PDF, JPG, PNG)"
                        ayuda="El archivo se guardará en el gestor documental externo."
                        accept=".pdf,image/jpeg,image/png"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" icono="save">Guardar</x-boton>
                </div>
            </form>
        </dialog>

        {{-- Modal registrar resultado (Positivo / Negativo) --}}
        <dialog
            id="modal-resultado-hisopado"
            class="m-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 id="titulo-modal-resultado" class="text-lg font-bold text-hu-azul">Registrar resultado</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form method="POST" action="{{ route('cirugias.hisopado.estado', $caso->cirugia) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <input type="hidden" name="estado" id="input-estado-resultado" value="">

                {{-- Pastilla visual que muestra el resultado elegido --}}
                <div id="badge-resultado" class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold">
                    <x-icono nombre="check_circle" class="texto-resultado text-xl" />
                    <span class="texto-resultado"></span>
                </div>

                <x-input
                    nombre="observacionesHisopadoSarm"
                    etiqueta="Observaciones (opcional)"
                    placeholder="Ej: muestra recibida el 03/08, sin anomalías"
                />

                <div class="border-t border-hu-gris-suave/60 pt-4">
                    <x-input
                        tipo="file"
                        nombre="archivoResultado"
                        etiqueta="Adjuntar resultado (PDF, JPG, PNG)"
                        ayuda="El archivo se guardará en el gestor documental externo."
                        accept=".pdf,image/jpeg,image/png"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" id="btn-confirmar-resultado" icono="save">Confirmar resultado</x-boton>
                </div>
            </form>
        </dialog>
        {{-- Modal agregar profilaxis --}}
        <dialog
            id="modal-agregar-profilaxis"
            class="m-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Agregar Profilaxis Antibiótica</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form method="POST" action="{{ route('cirugias.profilaxis.store', $caso->cirugia) }}" class="space-y-4">
                @csrf

                <x-select
                    nombre="idProfilaxis"
                    etiqueta="Antibiótico"
                    :opciones="$profilaxisOpciones"
                    vacio="Seleccionar antibiótico..."
                    requerido
                />

                <x-select
                    nombre="idProfilaxisRol"
                    etiqueta="Rol"
                    :opciones="$profilaxisRoles"
                    vacio="Seleccionar rol..."
                    requerido
                />

                <x-input
                    nombre="indicaciones"
                    etiqueta="Indicaciones / Observaciones (opcional)"
                    placeholder="Ej: Administrar 30 min antes de la incisión"
                />

                <div class="flex justify-end gap-3 pt-4 border-t border-hu-gris-suave/60">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" icono="save">Agregar</x-boton>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Modal actualizar estado de autorización --}}
    @if ($tabActivo === 'autorizacion')
        <dialog
            id="modal-estado-autorizacion"
            class="m-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Actualizar estado</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form method="POST" action="{{ route('cirugias.autorizacion.estado', $caso->cirugia) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-select
                    nombre="idEstadoAutCirugia"
                    etiqueta="Nuevo estado"
                    :opciones="$estadosAutorizacion"
                    :valor="$caso->historialAutorizacion()->first()?->idEstadoAutCirugia"
                    vacio="Seleccionar..."
                    requerido
                />

                <x-input
                    nombre="observacionesAutoCirugiaEstado"
                    etiqueta="Motivo / Observación (Opcional)"
                    placeholder="Escribí el motivo del cambio de estado..."
                />

                <div class="flex justify-end gap-3 pt-2">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" icono="save">Actualizar</x-boton>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Modal agregar estudio prequirúrgico --}}
    @if ($tabActivo === 'estudios')
        <dialog
            id="modal-agregar-estudio"
            class="m-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Agregar estudio prequirúrgico</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form method="POST" action="{{ route('cirugias.estudios.store', $caso->cirugia) }}" class="space-y-4">
                @csrf

                <x-select
                    nombre="idTipoEstudio"
                    etiqueta="Tipo de estudio"
                    :opciones="$tiposEstudios"
                    vacio="Seleccionar estudio..."
                    requerido
                />

                <div class="flex justify-end gap-3 pt-2">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" icono="save">Agregar</x-boton>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Modal gestionar estudio prequirúrgico --}}
    @if ($tabActivo === 'estudios')
        <dialog
            id="modal-gestionar-estudio"
            class="m-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Gestionar estudio</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <p id="titulo-estudio-gestionar" class="mb-4 text-sm font-semibold text-hu-azul"></p>

            <form method="POST" id="form-gestionar-estudio" action="" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-input
                    tipo="date"
                    nombre="fechaEsperadaResultadoCirugiaTipoEstudio"
                    id="input-fecha-estudio"
                    etiqueta="Fecha esperada de resultado"
                />

                <x-input
                    nombre="resultadoCirugiaTipoEstudio"
                    id="input-resultado-estudio"
                    etiqueta="Resultado / Observación"
                    placeholder="Escribí un resumen del resultado..."
                />

                <div class="flex justify-end gap-3 pt-4">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" icono="save">Guardar</x-boton>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Modal nuevo pedido hemoderivados --}}
    @if ($tabActivo === 'hemoderivados')
        <dialog
            id="modal-nuevo-pedido-hemoderivado"
            class="m-auto w-full max-w-4xl rounded-2xl bg-white p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:animate-in open:fade-in open:zoom-in-95"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-hu-azul">Nuevo pedido de hemoderivados</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="text-hu-gris-medio hover:text-hu-azul">
                    <x-icono nombre="close" class="text-2xl" />
                </button>
            </div>

            <form method="POST" action="{{ route('cirugias.hemoderivados.store', $caso->cirugia) }}" class="space-y-4 max-h-[80vh] overflow-y-auto pr-2">
                @csrf
                <x-input
                    nombre="observacionesPedidoHemoderivados"
                    etiqueta="Observaciones generales (Opcional)"
                    placeholder="Ej. Requerido para backup..."
                />
                
                <div class="mt-4 border-t border-hu-gris-suave/60 pt-4">
                    <div class="flex items-end gap-3 mb-4">
                        <div class="flex-1">
                            <x-select
                                nombre="select_tipo_hemoderivado"
                                id="select_tipo_hemoderivado"
                                etiqueta="Buscar componente"
                                :opciones="$tiposHemoderivados"
                                vacio="Seleccionar componente para agregar..."
                            />
                        </div>
                        <x-boton tipo="button" variante="secundario" onclick="agregarComponenteSeleccionado()">
                            <x-icono nombre="add" class="text-sm" /> Agregar
                        </x-boton>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-hu-gris-suave/60 mt-2">
                        <table class="w-full text-left text-sm hidden" id="tabla-componentes">
                            <thead class="bg-hu-gris-tenue/50 text-hu-azul border-b border-hu-gris-suave/60">
                                <tr>
                                    <th class="px-4 py-2 font-bold w-1/3">Tipo Hemoderivado</th>
                                    <th class="px-4 py-2 font-bold w-24">Cantidad</th>
                                    <th class="px-4 py-2 font-bold w-1/4">Banco</th>
                                    <th class="px-4 py-2 font-bold">Justificación (Opcional)</th>
                                    <th class="px-4 py-2 font-bold w-12 text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="contenedor-componentes" class="divide-y divide-hu-gris-suave/60">
                                {{-- Las filas se agregan acá dinámicamente --}}
                            </tbody>
                        </table>
                        <p id="mensaje-sin-componentes" class="text-center text-sm text-hu-gris-medio py-6 bg-hu-gris-tenue/30">No se agregaron componentes aún.</p>
                    </div>
                </div>

                <template id="template-componente-fila">
                    <table>
                        <tbody>
                            <tr class="componente-fila bg-white hover:bg-hu-gris-tenue/20 transition-colors">
                                <td class="px-4 py-3 align-top">
                                    <span class="componente-titulo font-bold text-hu-azul block mt-1"></span>
                                    <input type="hidden" name="componentes[INDEX][idTipoHemoderivado]" class="input-id-tipo">
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <input
                                        type="number"
                                        name="componentes[INDEX][cantidad]"
                                        value="1"
                                        min="1"
                                        required
                                        class="w-full rounded-md border-hu-gris-suave px-3 py-1.5 text-sm focus:border-hu-azul focus:ring-hu-azul"
                                    >
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <select
                                        name="componentes[INDEX][idEstablecimiento]"
                                        required
                                        class="w-full rounded-md border-hu-gris-suave px-3 py-1.5 text-sm focus:border-hu-azul focus:ring-hu-azul"
                                    >
                                        <option value="">Seleccionar...</option>
                                        @foreach($establecimientosHisopado as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <input
                                        type="text"
                                        name="componentes[INDEX][descripcion]"
                                        placeholder="Escribí una justificación..."
                                        class="w-full rounded-md border-hu-gris-suave px-3 py-1.5 text-sm focus:border-hu-azul focus:ring-hu-azul"
                                    >
                                </td>
                                <td class="px-4 py-3 align-top text-center">
                                    <button type="button" class="btn-eliminar-fila mt-1 text-hu-gris-medio hover:text-red-500 transition-colors" onclick="this.closest('.componente-fila').remove(); actualizarIndicesComponentes(); verificarComponentesVacios();" title="Eliminar fila">
                                        <x-icono nombre="delete" class="text-[20px]" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>

                <div class="sticky bottom-0 flex justify-end gap-3 bg-white pt-4">
                    <x-boton tipo="button" variante="fantasma" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton tipo="submit" icono="save" id="btn-submit-pedido" disabled class="opacity-50 cursor-not-allowed">Crear pedido y componentes</x-boton>
                </div>
            </form>
        </dialog>
    @endif

@endsection

@push('scripts')
    <script>
        // Cierra modales al hacer clic en el backdrop.
        ['modal-hisopado', 'modal-resultado-hisopado', 'modal-estado-autorizacion', 'modal-agregar-estudio', 'modal-gestionar-estudio', 'modal-nuevo-pedido-hemoderivado', 'modal-agregar-material'].forEach(function (id) {
            document.getElementById(id)?.addEventListener('click', function (e) {
                if (e.target === this) this.close();
            });
        });

        /**
         * Abre el modal de resultado pre-configurado para Positivo o Negativo.
         * - Pone el valor en el input oculto.
         * - Actualiza el título y la pastilla de color.
         */
        function abrirModalResultado(resultado) {
            const modal   = document.getElementById('modal-resultado-hisopado');
            const input   = document.getElementById('input-estado-resultado');
            const titulo  = document.getElementById('titulo-modal-resultado');
            const badge   = document.getElementById('badge-resultado');
            const textos  = badge.querySelectorAll('.texto-resultado');

            input.value = resultado;
            titulo.textContent = 'Registrar resultado: ' + resultado;

            // Limpiar clases anteriores del badge.
            badge.className = badge.className.replace(/bg-\S+|text-\S+/g, '').trim();
            textos.forEach(el => el.className = el.className.replace(/text-\S+/g, '').trim());

            if (resultado === 'Negativo') {
                badge.classList.add('bg-green-50', 'text-green-800');
                textos.forEach(el => el.classList.add('text-green-700'));
                textos[1].textContent = 'Negativo — sin presencia de SAMR';
            } else {
                badge.classList.add('bg-red-50', 'text-red-800');
                textos.forEach(el => el.classList.add('text-red-700'));
                textos[1].textContent = 'Positivo — presencia de SAMR detectada';
            }

            // Limpiar el campo de observaciones al abrir.
            modal.querySelector('[name="observacionesHisopadoSarm"]').value = '';

            modal.showModal();
        }

        /**
         * Abre el modal para gestionar un estudio prequirúrgico.
         */
        function abrirModalEstudio(idEstudio, nombre, fechaEsperada, resultado) {
            const modal = document.getElementById('modal-gestionar-estudio');
            const form = document.getElementById('form-gestionar-estudio');
            
            document.getElementById('titulo-estudio-gestionar').textContent = nombre;
            
            // Actualizar action del form
            const actionBase = '{{ route("cirugias.estudios.update", ["cirugia" => $caso->cirugia, "estudio" => "ID_PLACEHOLDER"]) }}';
            form.action = actionBase.replace('ID_PLACEHOLDER', idEstudio);

            document.getElementById('input-fecha-estudio').value = fechaEsperada || '';
            document.getElementById('input-resultado-estudio').value = resultado || '';

            modal.showModal();
        }

        function agregarComponenteSeleccionado() {
            const select = document.getElementById('select_tipo_hemoderivado');
            const idTipo = select.value;
            
            if (!idTipo) {
                // Pequeño efecto visual si intentan agregar sin seleccionar
                select.classList.add('ring-2', 'ring-red-500', 'border-red-500');
                setTimeout(() => select.classList.remove('ring-2', 'ring-red-500', 'border-red-500'), 1000);
                return;
            }
            
            const nombreTipo = select.options[select.selectedIndex].text;
            const template = document.getElementById('template-componente-fila');
            // Extraer específicamente el <tr> para evitar problemas de parseo en algunos navegadores
            const trNode = template.content.querySelector('tr');
            const clone = trNode.cloneNode(true);
            
            clone.querySelector('.componente-titulo').textContent = nombreTipo;
            clone.querySelector('.input-id-tipo').value = idTipo;
            
            document.getElementById('contenedor-componentes').appendChild(clone);
            actualizarIndicesComponentes();
            verificarComponentesVacios();
            
            // Limpiar select
            select.value = '';
        }

        function abrirModalNuevoPedido() {
            const modal = document.getElementById('modal-nuevo-pedido-hemoderivado');
            // Limpiar componentes agregados
            document.getElementById('contenedor-componentes').innerHTML = '';
            // Limpiar select
            document.getElementById('select_tipo_hemoderivado').value = '';
            // Limpiar observaciones
            modal.querySelector('input[name="observacionesPedidoHemoderivados"]').value = '';
            
            verificarComponentesVacios();
            modal.showModal();
        }

        function actualizarIndicesComponentes() {
            const filas = document.querySelectorAll('.componente-fila');
            filas.forEach((fila, index) => {
                fila.querySelectorAll('input, select, textarea').forEach(el => {
                    const name = el.getAttribute('name');
                    if (name) {
                        el.setAttribute('name', name.replace(/componentes\[.*?\]/, `componentes[${index}]`));
                    }
                });
            });
        }

        function verificarComponentesVacios() {
            const btnSubmit = document.getElementById('btn-submit-pedido');
            const filas = document.querySelectorAll('.componente-fila');
            const tabla = document.getElementById('tabla-componentes');
            const mensaje = document.getElementById('mensaje-sin-componentes');
            
            if (filas.length > 0) {
                btnSubmit.removeAttribute('disabled');
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
                tabla.classList.remove('hidden');
                mensaje.classList.add('hidden');
            } else {
                btnSubmit.setAttribute('disabled', 'disabled');
                btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
                tabla.classList.add('hidden');
                mensaje.classList.remove('hidden');
            }
        }
    </script>

    <script>
        // Preparacion: checkbox habilita/deshabilita el input de horas
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.prep-check').forEach(function (chk) {
                chk.addEventListener('change', function () {
                    var input = document.getElementById(this.dataset.target);
                    if (input) {
                        input.disabled = !this.checked;
                        if (!this.checked) { input.value = ''; }
                    }
                });
            });
        });
    </script>
    <script>
        // Material Modal Logic
        let proveedoresCache = [];
        let searchTimeout = null;

        const inputBuscar = document.getElementById('buscador-material');
        const listResultados = document.getElementById('resultados-materiales');
        
        if (inputBuscar) {
            inputBuscar.addEventListener('input', function() {
                const q = this.value.trim();
                if (q.length < 2) {
                    listResultados.classList.add('hidden');
                    return;
                }
                
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetch(`{{ route('cirugias.materiales.buscar') }}?q=${encodeURIComponent(q)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.length === 0) {
                                listResultados.innerHTML = '<div class="px-3 py-2 text-sm text-hu-gris-medio">No se encontraron resultados.</div>';
                            } else {
                                listResultados.innerHTML = data.map(m => `
                                    <div class="px-3 py-2 cursor-pointer hover:bg-hu-gris-tenue border-b border-hu-gris-suave last:border-0" onclick="seleccionarMaterial(${m.idMaterial}, '${m.nombreMaterial.replace(/'/g, "\\'")}', '${m.codMaterial || ''}')">
                                        <p class="text-sm font-semibold text-hu-azul">${m.nombreMaterial}</p>
                                        <p class="text-xs text-hu-gris-medio">Cód. ${m.codMaterial || 'N/A'}</p>
                                    </div>
                                `).join('');
                            }
                            listResultados.classList.remove('hidden');
                        });
                }, 300);
            });
            
            // Cerrar resultados click fuera
            document.addEventListener('click', function(e) {
                if (!inputBuscar.contains(e.target) && !listResultados.contains(e.target)) {
                    listResultados.classList.add('hidden');
                }
            });
        }

        function seleccionarMaterial(id, nombre, cod) {
            document.getElementById('idMaterial').value = id;
            document.getElementById('mat-nombre').textContent = nombre;
            document.getElementById('mat-codigo').textContent = cod ? `Cód. ${cod}` : '';
            
            document.getElementById('buscador-material').classList.add('hidden');
            document.getElementById('resultados-materiales').classList.add('hidden');
            document.getElementById('material-seleccionado-info').classList.remove('hidden');
            document.getElementById('material-seleccionado-info').classList.add('flex');
            
            cargarProveedores(id);
        }

        function limpiarMaterial() {
            document.getElementById('idMaterial').value = '';
            document.getElementById('buscador-material').value = '';
            document.getElementById('buscador-material').classList.remove('hidden');
            document.getElementById('material-seleccionado-info').classList.add('hidden');
            document.getElementById('material-seleccionado-info').classList.remove('flex');
            
            const selectProv = document.getElementById('select-proveedor');
            selectProv.innerHTML = '<option value="">Seleccione material primero</option>';
            selectProv.disabled = true;
            
            const selectMedida = document.getElementById('select-medida');
            selectMedida.innerHTML = '<option value="">Seleccione proveedor primero</option>';
            selectMedida.disabled = true;
            
            checkMaterialSubmit();
        }

        // El precio depende de la unidad, así que se muestra al elegirla: es lo
        // que se va a valorizar en el pedido.
        function etiquetaPrecio(medida) {
            const precio = medida.precioExternoMaterialProveedorTipoMedida;

            if (precio === null || precio === undefined) {
                return ' — sin precio cargado';
            }

            return ' — USD ' + Number(precio).toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        function cargarProveedores(idMaterial) {
            const selectProv = document.getElementById('select-proveedor');
            selectProv.innerHTML = '<option value="">Cargando...</option>';
            selectProv.disabled = true;
            
            fetch(`{{ url('/cirugias/api/materiales') }}/${idMaterial}/proveedores`)
                .then(res => res.json())
                .then(data => {
                    proveedoresCache = data;
                    if (data.length === 0) {
                        selectProv.innerHTML = '<option value="">Sin proveedores asignados</option>';
                    } else {
                        selectProv.innerHTML = '<option value="">Seleccione proveedor</option>' + data.map(p => `
                            <option value="${p.idProveedor}">${p.proveedor.nombreProveedor}</option>
                        `).join('');
                        selectProv.disabled = false;
                    }
                    checkMaterialSubmit();
                });
        }

        const selectProveedor = document.getElementById('select-proveedor');
        if (selectProveedor) {
            selectProveedor.addEventListener('change', function() {
                const idProv = this.value;
                const selectMedida = document.getElementById('select-medida');
                
                if (!idProv) {
                    selectMedida.innerHTML = '<option value="">Seleccione proveedor primero</option>';
                    selectMedida.disabled = true;
                    checkMaterialSubmit();
                    return;
                }
                
                const proveedor = proveedoresCache.find(p => p.idProveedor == idProv);
                if (proveedor && proveedor.material_proveedor_tipo_medidas && proveedor.material_proveedor_tipo_medidas.length > 0) {
                    selectMedida.innerHTML = '<option value="">Seleccione medida</option>' + proveedor.material_proveedor_tipo_medidas.map(m => `
                        <option value="${m.idTipoMedida}">${m.tipo_medida.nombreTipoMedida}${etiquetaPrecio(m)}</option>
                    `).join('');
                    selectMedida.disabled = false;
                } else if (proveedor && proveedor.material_proveedor_tipo_medidas) {
                    selectMedida.innerHTML = '<option value="">Sin medidas disponibles</option>';
                    selectMedida.disabled = true;
                } else {
                    // Si viene con keys en camelCase (depende de Laravel Resource o array conversion)
                    const medidas = proveedor.materialProveedorTipoMedidas || [];
                    if(medidas.length > 0) {
                        selectMedida.innerHTML = '<option value="">Seleccione medida</option>' + medidas.map(m => `
                            <option value="${m.idTipoMedida}">${m.tipoMedida.nombreTipoMedida}${etiquetaPrecio(m)}</option>
                        `).join('');
                        selectMedida.disabled = false;
                    } else {
                        selectMedida.innerHTML = '<option value="">Sin medidas disponibles</option>';
                        selectMedida.disabled = true;
                    }
                }
                checkMaterialSubmit();
            });
        }

        const selectMedidaMat = document.getElementById('select-medida');
        if (selectMedidaMat) {
            selectMedidaMat.addEventListener('change', checkMaterialSubmit);
        }
        
        function checkMaterialSubmit() {
            const btn = document.getElementById('btn-submit-material');
            const idMat = document.getElementById('idMaterial').value;
            const idProv = document.getElementById('select-proveedor').value;
            const idMedida = document.getElementById('select-medida').value;
            
            if (idMat && idProv && idMedida) {
                btn.removeAttribute('disabled');
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btn.setAttribute('disabled', 'disabled');
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
    </script>
@endpush
