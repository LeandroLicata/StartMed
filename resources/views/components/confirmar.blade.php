{{--
    Dialogo de confirmacion, uno solo para toda la aplicacion. Lo abre
    resources/js/app.js cuando se envia un formulario con data-confirmar, y le
    escribe el texto de ese formulario.

    Va uno por pagina, no uno por fila: un listado de 25 registros tendria si no
    25 dialogos en el DOM.

    Con <dialog> el navegador ya se ocupa de atrapar el foco, cerrar con Escape
    y dejar inerte lo que hay detras.
--}}
<dialog
    id="dialogo-confirmar"
    aria-labelledby="dialogo-confirmar-titulo"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-hu-gris-suave/70 bg-white
           p-0 text-hu-gris shadow-xl backdrop:bg-hu-azul-oscuro/50"
>
    <div class="flex items-start gap-4 px-6 pt-6">
        <span
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700"
            aria-hidden="true"
        >
            <x-icono nombre="warning" class="text-2xl" relleno />
        </span>

        <div class="min-w-0 space-y-1 pt-0.5">
            <h2 id="dialogo-confirmar-titulo" class="text-base font-semibold text-hu-azul">
                ¿Confirmás la acción?
            </h2>
            <p data-dialogo-detalle class="text-sm text-hu-gris-medio"></p>
        </div>
    </div>

    <div class="mt-6 flex items-center justify-end gap-2 rounded-b-2xl bg-hu-gris-tenue/50 px-6 py-4">
        <x-boton tipo="button" variante="fantasma" forma="grupo" data-dialogo-cancelar>
            Cancelar
        </x-boton>

        <x-boton tipo="button" variante="peligro" forma="grupo" data-dialogo-aceptar>
            Confirmar
        </x-boton>
    </div>
</dialog>
