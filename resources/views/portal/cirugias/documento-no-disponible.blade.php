@extends('layouts.portal')

{{--
    Lo que ve el paciente cuando "Ver el resultado" no puede entregar el archivo.
    A cargo de: Leandro. Recibe $caso y $estudio.

    Es una pagina propia y no un redirect al detalle con un flash: "Ver" abre
    una pestaña nueva, y el redirect dejaba ahi una segunda copia del portal
    entera con el error arriba. No ofrece volver a subir: el estudio figura
    como recibido y el paciente no reemplaza (ver Portal\EstudioController).
--}}

@section('titulo', 'Archivo no disponible')

@section('contenido')
    <x-tarjeta>
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <x-icono nombre="error" class="text-4xl text-hu-dorado" />
            <h1 class="text-lg font-semibold text-hu-azul">
                No pudimos abrir el resultado de {{ $estudio->tipoEstudio?->nombreTipoEstudio ?? 'tu estudio' }}
            </h1>
            <p class="max-w-md text-sm text-hu-gris">
                Puede ser algo pasajero: probá de nuevo en unos minutos. Si sigue pasando,
                avisale al equipo del hospital. Tu estudio figura como recibido, así que no
                hace falta que lo vuelvas a subir.
            </p>

            <div class="mt-3 flex flex-wrap justify-center gap-2">
                <x-boton :href="route('portal.cirugias.estudios.archivo', [$caso->cirugia, $estudio])" icono="refresh">
                    Probar de nuevo
                </x-boton>
                <x-boton :href="route('portal.cirugias.show', [$caso->cirugia, 'estudios'])" variante="contorno" icono="arrow_back">
                    Volver a mis estudios
                </x-boton>
            </div>
        </div>
    </x-tarjeta>
@endsection
