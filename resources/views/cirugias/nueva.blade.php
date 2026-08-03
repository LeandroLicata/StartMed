@extends('layouts.app')

@section('titulo', 'Nueva cirugía')
@section('subtitulo', $persona ? $persona->documento.' — '.$persona->nombre_completo : 'Buscar paciente')

@section('contenido')

    <x-boton variante="fantasma" forma="grupo" icono="arrow_back" :href="route('dashboard')" class="mb-6 px-3">
        Volver al tablero
    </x-boton>

    @unless ($persona)
        {{-- Paso 1: buscar o dar de alta al paciente --}}
        <x-tarjeta titulo="Buscar paciente" icono="badge">
            <form method="GET" action="{{ route('cirugias.crear') }}" class="flex flex-wrap items-end gap-3">
                @if ($fecha)
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                @endif
                <div class="w-full max-w-xs">
                    <x-input nombre="q" etiqueta="DNI o apellido" :valor="$q" requerido />
                </div>
                <x-boton tipo="submit" forma="grupo">Buscar</x-boton>
            </form>
        </x-tarjeta>

        @if (! is_null($resultados))
            <div class="mt-6">
                @if ($resultados->isNotEmpty())
                    <x-tarjeta titulo="Resultados" icono="groups">
                        <ul class="divide-y divide-hu-gris-suave/60">
                            @foreach ($resultados as $r)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div>
                                        <p class="text-sm font-semibold text-hu-azul">
                                            {{ $r->documento }} — {{ $r->nombre_completo }}
                                        </p>
                                        @if ($r->fechaHoraBajaPersona)
                                            <p class="text-xs font-semibold text-red-700">Dado de baja</p>
                                        @endif
                                    </div>
                                    @unless ($r->fechaHoraBajaPersona)
                                        <x-boton
                                            forma="grupo"
                                            :href="route('cirugias.crear', array_filter(['persona' => $r->idPersona, 'fecha' => $fecha]))"
                                        >
                                            Seleccionar
                                        </x-boton>
                                    @endunless
                                </li>
                            @endforeach
                        </ul>

                        @if ($resultados->count() === 15)
                            <p class="mt-3 text-xs text-hu-gris-medio">
                                Mostrando los primeros 15 resultados — afiná la búsqueda si no encontrás a quien buscás.
                            </p>
                        @endif
                    </x-tarjeta>
                @else
                    <x-alerta tipo="aviso" titulo="No se encontró ningún paciente para &quot;{{ $q }}&quot;" class="mb-6">
                        Podés darlo de alta ahora para continuar con la cirugía.
                    </x-alerta>

                    <x-tarjeta titulo="Dar de alta al paciente" icono="person">
                        <form method="POST" action="{{ route('cirugias.crear.paciente') }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            @if ($fecha)
                                <input type="hidden" name="fecha" value="{{ $fecha }}">
                            @endif

                            <x-input nombre="apellidos" etiqueta="Apellidos" requerido />
                            <x-input nombre="nombres" etiqueta="Nombres" requerido />
                            <x-input nombre="documento" etiqueta="DNI" :valor="ctype_digit((string) $q) ? $q : null" requerido />
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
    @else
        {{-- Paso 2: formulario completo --}}
        <x-tarjeta titulo="Paciente" icono="person" class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-base font-semibold text-hu-azul">
                        {{ $persona->documento }} — {{ $persona->nombre_completo }}
                    </p>
                    @if ($persona->fecha_nacimiento)
                        <p class="text-sm text-hu-gris-medio">
                            {{ $persona->fecha_nacimiento->translatedFormat('j/m/Y') }} · {{ $persona->fecha_nacimiento->age }} años
                        </p>
                    @endif
                </div>
                <x-boton variante="fantasma" forma="grupo" :href="route('cirugias.crear')">
                    Cambiar paciente
                </x-boton>
            </div>
        </x-tarjeta>

        @php
            $disp = session('disponibilidad');
            $puedeCrear = $disp && collect($disp)->every(fn ($ok) => $ok);
        @endphp

        @if ($disp)
            <x-alerta
                :tipo="$puedeCrear ? 'exito' : 'error'"
                titulo="Resultado de la comprobación"
                class="mb-6"
            >
                <ul class="space-y-0.5">
                    <li>Quirófano: {{ $disp['quirofano'] ? 'Disponible' : 'Ocupado en ese horario' }}</li>
                    @if (isset($disp['cirujano']))
                        <li>Cirujano: {{ $disp['cirujano'] ? 'Disponible' : 'Ocupado en ese horario' }}</li>
                    @endif
                    @if (isset($disp['anestesista']))
                        <li>Anestesista: {{ $disp['anestesista'] ? 'Disponible' : 'Ocupado en ese horario' }}</li>
                    @endif
                </ul>
            </x-alerta>
        @endif

        @php
            $opcionesTipoCirugia = $tiposCirugia->pluck('nombreTipoCirugia', 'idTipoCirugia');
            $opcionesQuirofano = $quirofanos->mapWithKeys(
                fn ($q) => [$q->idQuirofano => 'Nº '.$q->nroQuirofano.' — '.$q->nombreQuirofano],
            );
            $opcionesCirujano = $cirujanos->mapWithKeys(fn ($p) => [$p->idPersonal => $p->persona?->nombre_completo]);
            $opcionesAnestesista = $anestesistas->mapWithKeys(fn ($p) => [$p->idPersonal => $p->persona?->nombre_completo]);
            $opcionesCobertura = $coberturas->mapWithKeys(fn ($c) => [
                $c->idPlanObraSocial => $c->plan?->obrasocial?->nombreObraSocial.' · '.$c->plan?->nombrePlan
                    .($c->nroBeneficiaroPlanObraSocial ? ' (N° '.$c->nroBeneficiaroPlanObraSocial.')' : ''),
            ]);

            $opcionesPlanNuevo = [];
            foreach ($obrasSociales as $obraSocial) {
                foreach ($obraSocial->planes as $plan) {
                    $opcionesPlanNuevo[$plan->idPlan] = $obraSocial->nombreObraSocial.' · '.$plan->nombrePlan;
                }
            }

            $coberturaPorDefecto = old('cobertura', $coberturas->isNotEmpty() ? 'existente' : 'particular');
        @endphp

        <form method="POST" action="{{ route('cirugias.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="idPersona" value="{{ $persona->idPersona }}">
            @if ($fecha)
                <input type="hidden" name="fecha" value="{{ $fecha }}">
            @endif

            <x-tarjeta titulo="Cobertura" icono="shield">
                <div class="space-y-4">
                    @if ($opcionesCobertura->isNotEmpty())
                        <label class="flex items-start gap-3">
                            <input
                                type="radio"
                                name="cobertura"
                                value="existente"
                                @checked($coberturaPorDefecto === 'existente')
                                class="mt-1 text-hu-azul focus:ring-hu-azul"
                            >
                            <div class="w-full max-w-md">
                                <span class="block text-sm font-semibold text-hu-azul">Usar una cobertura ya cargada</span>
                                <x-select nombre="idPlanObraSocial" :opciones="$opcionesCobertura" class="mt-1.5" />
                            </div>
                        </label>
                    @endif

                    <label class="flex items-start gap-3">
                        <input
                            type="radio"
                            name="cobertura"
                            value="nueva"
                            @checked($coberturaPorDefecto === 'nueva')
                            class="mt-1 text-hu-azul focus:ring-hu-azul"
                        >
                        <div class="w-full max-w-md space-y-3">
                            <span class="block text-sm font-semibold text-hu-azul">Cargar una obra social nueva</span>
                            <x-select nombre="idPlan" etiqueta="Obra social y plan" :opciones="$opcionesPlanNuevo" />
                            <x-input nombre="nroBeneficiario" etiqueta="N° de beneficiario (opcional)" />
                        </div>
                    </label>

                    <label class="flex items-center gap-3">
                        <input
                            type="radio"
                            name="cobertura"
                            value="particular"
                            @checked($coberturaPorDefecto === 'particular')
                            class="text-hu-azul focus:ring-hu-azul"
                        >
                        <span class="text-sm font-semibold text-hu-azul">Particular — sin obra social</span>
                    </label>
                </div>
            </x-tarjeta>

            <x-tarjeta titulo="Datos de la cirugía" icono="assignment">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select
                        nombre="idTipoCirugia"
                        etiqueta="Tipo de procedimiento"
                        :opciones="$opcionesTipoCirugia"
                        requerido
                    />
                    <x-select
                        nombre="idQuirofano"
                        etiqueta="Quirófano"
                        :opciones="$opcionesQuirofano"
                        requerido
                        ayuda="Solo se listan los quirófanos disponibles."
                    />
                    <x-input
                        nombre="fechaHoraCirugia"
                        etiqueta="Fecha y hora de inicio"
                        tipo="datetime-local"
                        :valor="$fecha ? $fecha.'T08:00' : null"
                        requerido
                    />
                    <x-input
                        nombre="fechaHoraFinCirugia"
                        etiqueta="Hora estimada de fin (opcional)"
                        tipo="datetime-local"
                        ayuda="Si no se carga, se asume una duración de 2 hs para chequear superposiciones."
                    />
                    <x-select
                        nombre="idPersonalCirujano"
                        etiqueta="Cirujano (opcional)"
                        :opciones="$opcionesCirujano"
                        ayuda="Si todavía no se sabe, se puede dejar sin asignar."
                    />
                    <x-select
                        nombre="idPersonalAnestesista"
                        etiqueta="Anestesista (opcional)"
                        :opciones="$opcionesAnestesista"
                    />
                </div>

                <div class="mt-4 space-y-2">
                    <label class="flex items-center gap-2 text-sm text-hu-gris">
                        <input
                            type="checkbox"
                            name="requiereImplante"
                            value="1"
                            @checked(old('requiereImplante'))
                            class="rounded border-hu-gris-suave text-hu-azul focus:ring-hu-azul"
                        >
                        Requiere implante
                    </label>

                    <label class="flex items-center gap-2 text-sm text-hu-gris">
                        <input
                            type="checkbox"
                            name="requiereHemoderivados"
                            value="1"
                            @checked(old('requiereHemoderivados'))
                            class="rounded border-hu-gris-suave text-hu-azul focus:ring-hu-azul"
                        >
                        Requiere hemoderivados
                        <span class="text-xs text-hu-gris-medio">(el detalle lo carga después el gestor o el cirujano)</span>
                    </label>

                    <label class="flex items-center gap-2 text-sm text-hu-gris">
                        <input
                            type="checkbox"
                            name="requiereHisopadoSarm"
                            value="1"
                            @checked(old('requiereHisopadoSarm'))
                            class="rounded border-hu-gris-suave text-hu-azul focus:ring-hu-azul"
                        >
                        Requiere Hisopado SAMR
                        <span class="text-xs text-hu-gris-medio">(el resultado y la profilaxis se cargan después)</span>
                    </label>
                </div>
            </x-tarjeta>

            <div class="flex flex-wrap gap-3">
                <x-boton
                    tipo="submit"
                    variante="contorno"
                    icono="schedule"
                    formaction="{{ route('cirugias.crear.comprobar') }}"
                    formmethod="POST"
                >
                    Comprobar disponibilidad
                </x-boton>
                <x-boton tipo="submit" icono="check_circle" :disabled="! $puedeCrear">Crear cirugía</x-boton>
            </div>

            @if (! $puedeCrear)
                <p class="mt-2 text-xs text-hu-gris-medio">
                    Comprobá la disponibilidad de quirófano, cirujano y anestesista antes de crear la cirugía.
                </p>
            @endif
        </form>
    @endunless

@endsection
