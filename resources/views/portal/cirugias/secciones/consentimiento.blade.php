{{--
    Pestaña "consentimiento" del detalle. Recibe $caso, $soloLectura y
    $documentoConsentimiento (el texto a leer, o null si no hay plantilla).

    En pantalla queda solo el recuadro "Mi firma". El documento completo se lee
    en una ventana; al final está la casilla "Leí el documento…", destildada,
    que habilita "Firmar consentimiento". Antes de guardar, el diálogo de
    confirmación de la app (components/confirmar) pide reconfirmar.
--}}

@php
    $consentimiento = $caso->consentimiento();
    $firmado = $caso->consentimientoFirmado();
    $documento = $documentoConsentimiento ?? null;
    $puedeFirmar = ! $firmado && ! $soloLectura && $documento !== null;
@endphp

<x-tarjeta titulo="Mi firma" icono="draw">
    @if ($firmado)
        <x-slot:acciones>
            <x-estado tono="exito" icono="check_circle">Firmado</x-estado>
        </x-slot:acciones>
    @elseif ($puedeFirmar)
        <x-slot:acciones>
            <x-estado tono="aviso" icono="schedule">Sin firmar</x-estado>
        </x-slot:acciones>
    @endif

    @if ($firmado)
        <div class="flex flex-col items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-8 text-center text-emerald-800">
            <x-icono nombre="check_circle" class="text-4xl" relleno />
            <p class="text-sm font-semibold">Firmaste el consentimiento informado</p>
            <p class="text-xs">
                El {{ $consentimiento->fechaFirmaConsentimiento->translatedFormat('j \d\e F \d\e Y, H:i') }} hs
            </p>
        </div>

        <div class="mt-4 text-center">
            <x-boton tipo="button" variante="fantasma" icono="visibility" data-abrir-consentimiento>
                Ver el documento firmado
            </x-boton>
        </div>
    @elseif ($soloLectura)
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <x-icono nombre="history" class="text-4xl text-hu-gris-medio" />
            <p class="text-sm text-hu-gris-medio">Esta cirugía ya pasó y no se firmó el consentimiento desde el portal.</p>
        </div>
    @elseif ($documento === null)
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <x-icono nombre="pending" class="text-4xl text-hu-dorado" />
            <p class="text-sm text-hu-gris-medio">Tu equipo todavía no cargó el consentimiento para este procedimiento.</p>
            <p class="text-xs text-hu-gris-medio">Si tenés dudas, consultá con el equipo del hospital.</p>
        </div>
    @else
        <button
            type="button"
            data-abrir-consentimiento
            class="flex min-h-40 w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed
                   border-hu-dorado bg-hu-dorado-tenue/40 px-5 py-8 text-center text-hu-gris-medio transition-colors
                   hover:bg-hu-dorado-tenue focus-visible:outline-2 focus-visible:outline-hu-azul"
        >
            <x-icono nombre="draw" class="text-4xl text-hu-dorado-oscuro" />
            <span class="text-sm font-semibold text-hu-azul">Tocá acá para leer y firmar</span>
            <span class="text-xs">Vas a ver el documento completo antes de firmar.</span>
        </button>

        @error('leido')
            <p class="mt-3 text-sm text-red-700">{{ $message }}</p>
        @enderror
    @endif
</x-tarjeta>

@if ($documento !== null && ($firmado || $puedeFirmar))
    <dialog
        id="dialogo-consentimiento"
        aria-labelledby="dialogo-consentimiento-titulo"
        class="m-auto flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl flex-col rounded-2xl border
               border-hu-gris-suave/70 bg-white p-0 text-hu-gris shadow-xl backdrop:bg-hu-azul-oscuro/50
               [&:not([open])]:hidden"
    >
        <header class="flex items-center justify-between gap-4 border-b border-hu-gris-suave/70 px-5 py-4">
            <h2 id="dialogo-consentimiento-titulo" class="text-base font-semibold text-hu-azul">
                Consentimiento informado
            </h2>
            <button type="button" data-cerrar-consentimiento class="text-sm font-semibold text-hu-gris-medio hover:text-hu-azul">
                Cerrar
            </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ $documento }}</div>

            @if ($puedeFirmar)
                <form
                    method="POST"
                    action="{{ route('portal.cirugias.consentimiento.firmar', $caso->cirugia) }}"
                    class="mt-6 space-y-4 border-t border-hu-gris-suave/70 pt-5"
                    data-confirmar-titulo="Firmar el consentimiento"
                    data-confirmar="Tu firma queda registrada con la fecha y hora de hoy y no se puede deshacer."
                    data-confirmar-accion="Sí, firmar"
                >
                    @csrf
                    <input type="hidden" name="hash" value="{{ hash('sha256', $documento) }}">

                    <label class="flex items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            name="leido"
                            value="1"
                            data-casilla-consentimiento
                            class="mt-0.5 size-4 shrink-0 rounded border-hu-gris-suave text-hu-azul"
                        >
                        <span>Leí el documento y comprendí el procedimiento, sus riesgos y alternativas.</span>
                    </label>

                    <x-boton class="w-full" icono="draw" disabled data-firmar-consentimiento>
                        Firmar consentimiento
                    </x-boton>
                </form>
            @endif
        </div>
    </dialog>

    @push('scripts')
        <script>
            (function () {
                const dialogo = document.getElementById('dialogo-consentimiento');
                if (!dialogo || typeof dialogo.showModal !== 'function') return;

                document.querySelectorAll('[data-abrir-consentimiento]').forEach(function (boton) {
                    boton.addEventListener('click', function () { dialogo.showModal(); });
                });
                dialogo.querySelector('[data-cerrar-consentimiento]').addEventListener('click', function () {
                    dialogo.close();
                });
                dialogo.addEventListener('click', function (e) {
                    if (e.target === dialogo) dialogo.close();
                });

                const casilla = dialogo.querySelector('[data-casilla-consentimiento]');
                const firmar = dialogo.querySelector('[data-firmar-consentimiento]');
                if (casilla && firmar) {
                    // Destildada al abrir, también si el navegador recuerda el estado.
                    casilla.checked = false;
                    casilla.addEventListener('change', function () { firmar.disabled = !casilla.checked; });
                }
            })();
        </script>
    @endpush
@endif
