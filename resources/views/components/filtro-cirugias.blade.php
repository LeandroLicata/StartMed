@props([
    'action',
    'filtros' => [],
    'estadosCirugia',
    'quirofanosCatalogo',
    'obrasSocialesCatalogo',
    'hayFiltrosActivos' => false,
    'limpiarHref' => null,
    'conFechas' => true,
])

<form method="GET" action="{{ $action }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
    <x-input nombre="q" etiqueta="Buscar" :valor="$filtros['q'] ?? null" placeholder="Paciente o DNI" />

    <x-select
        nombre="estado"
        etiqueta="Estado"
        :opciones="$estadosCirugia->pluck('nombreEstadoCirugia', 'nombreEstadoCirugia')"
        :valor="$filtros['estado'] ?? null"
    />

    <x-select
        nombre="idQuirofano"
        etiqueta="Quirófano"
        :opciones="$quirofanosCatalogo->mapWithKeys(fn ($q) => [$q->idQuirofano => 'Nº '.$q->nroQuirofano.' — '.$q->nombreQuirofano])"
        :valor="$filtros['idQuirofano'] ?? null"
    />

    <x-select
        nombre="idObraSocial"
        etiqueta="Obra social"
        :opciones="$obrasSocialesCatalogo->pluck('nombreObraSocial', 'idObraSocial')"
        :valor="$filtros['idObraSocial'] ?? null"
    />

    @if ($conFechas)
        <x-input nombre="desde" etiqueta="Desde" tipo="date" :valor="$filtros['desde'] ?? null" />
        <x-input nombre="hasta" etiqueta="Hasta" tipo="date" :valor="$filtros['hasta'] ?? null" />
    @endif

    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
        <x-boton tipo="submit" forma="grupo">Filtrar</x-boton>
        @if ($hayFiltrosActivos)
            <x-boton variante="fantasma" forma="grupo" :href="$limpiarHref ?? $action">Limpiar filtros</x-boton>
        @endif
    </div>
</form>
