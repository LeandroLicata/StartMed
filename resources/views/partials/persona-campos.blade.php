@php
    $dniPorDefecto = array_search('DNI', $tiposDocumento, true);
    $tipoDocValor = $persona?->tipo_documento_id ?? ($dniPorDefecto !== false ? $dniPorDefecto : null);
@endphp

<div class="space-y-4">
    <h3 class="text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">Identificación</h3>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-select
            nombre="tipo_documento_id"
            etiqueta="Tipo de documento"
            :opciones="$tiposDocumento"
            :valor="$tipoDocValor"
            requerido
        />

        <x-input
            nombre="documento"
            etiqueta="Documento (DNI)"
            :valor="$persona?->documento"
            placeholder="Ej: 30123456"
            requerido
        />

        <x-input
            nombre="apellidos"
            etiqueta="Apellidos"
            :valor="$persona?->apellidos"
            requerido
        />

        <x-input
            nombre="nombres"
            etiqueta="Nombres"
            :valor="$persona?->nombres"
            requerido
        />

        <x-input
            nombre="apellido_materno"
            etiqueta="Apellido materno"
            :valor="$persona?->apellido_materno"
        />

        <x-input
            nombre="cuil"
            etiqueta="CUIL"
            :valor="$persona?->cuil"
        />
    </div>
</div>

<div class="space-y-4">
    <h3 class="text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">Contacto</h3>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-input
            nombre="contacto_email_direccion"
            etiqueta="Mail personal"
            tipo="email"
            :valor="$persona?->contacto_email_direccion"
            ayuda="Sirve para que la persona entre al portal de pacientes."
            requerido
        />

        <x-input
            nombre="telefono_numero"
            etiqueta="Teléfono"
            :valor="$persona?->telefono_numero"
            placeholder="Ej: 2614551122"
            requerido
        />

        <div class="sm:col-span-2">
            <x-input
                nombre="direccionPersona"
                etiqueta="Dirección"
                :valor="$persona?->direccionPersona"
                placeholder="Calle, número, piso/depto, localidad"
                requerido
            />
        </div>

        <x-input
            nombre="contacto_celular_numero"
            etiqueta="Celular alternativo"
            :valor="$persona?->contacto_celular_numero"
        />
    </div>
</div>

<div class="space-y-4">
    <h3 class="text-xs font-semibold uppercase tracking-widest text-hu-gris-medio">Datos clínicos</h3>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-input
            nombre="fecha_nacimiento"
            etiqueta="Fecha de nacimiento"
            tipo="date"
            :valor="$persona?->fecha_nacimiento?->format('Y-m-d')"
        />

        <x-select
            nombre="genero"
            etiqueta="Género"
            :opciones="$generos"
            :valor="$persona?->genero"
            vacio="Sin especificar"
        />

        <x-select
            nombre="grupo_sanguineo_id"
            etiqueta="Grupo sanguíneo"
            :opciones="$gruposSanguineos"
            :valor="$persona?->grupo_sanguineo_id"
            vacio="Sin especificar"
        />
    </div>

    <div class="grid gap-3 pt-1 sm:grid-cols-3">
        <x-checkbox
            nombre="es_cronico"
            etiqueta="Paciente crónico"
            :valor="$persona?->es_cronico ?? false"
        />

        <x-checkbox
            nombre="tiene_incapacidad"
            etiqueta="Tiene incapacidad"
            :valor="$persona?->tiene_incapacidad ?? false"
        />

        <x-checkbox
            nombre="no_acepta_donacion_sanguinea"
            etiqueta="No acepta donación sanguínea"
            :valor="$persona?->no_acepta_donacion_sanguinea ?? false"
        />
    </div>

    <x-textarea
        nombre="observaciones"
        etiqueta="Observaciones"
        :valor="$persona?->observaciones"
        :filas="3"
    />
</div>