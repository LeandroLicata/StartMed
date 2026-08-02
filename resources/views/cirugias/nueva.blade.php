@extends('layouts.app')

@section('titulo', 'Nueva cirugía')
@section('subtitulo', 'Paso 2 de 2 · '.$persona->documento.' — '.$persona->nombre_completo)

@section('contenido')

    <x-boton variante="fantasma" forma="grupo" icono="arrow_back" :href="route('cirugias.crear')" class="mb-6 px-3">
        Buscar otro paciente
    </x-boton>

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

        <x-tarjeta titulo="Paciente" icono="person">
            <p class="text-base font-semibold text-hu-azul">
                {{ $persona->documento }} — {{ $persona->nombre_completo }}
            </p>
            @if ($persona->fecha_nacimiento)
                <p class="text-sm text-hu-gris-medio">
                    {{ $persona->fecha_nacimiento->translatedFormat('j/m/Y') }} · {{ $persona->fecha_nacimiento->age }} años
                </p>
            @endif
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
                    requerido
                />
                <x-input
                    nombre="fechaHoraFinCirugia"
                    etiqueta="Hora estimada de fin (opcional)"
                    tipo="datetime-local"
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

            <label class="mt-4 flex items-center gap-2 text-sm text-hu-gris">
                <input
                    type="checkbox"
                    name="requiereImplante"
                    value="1"
                    @checked(old('requiereImplante'))
                    class="rounded border-hu-gris-suave text-hu-azul focus:ring-hu-azul"
                >
                Requiere implante
            </label>
        </x-tarjeta>

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

        <x-boton tipo="submit" icono="check_circle">Crear cirugía</x-boton>
    </form>

@endsection
