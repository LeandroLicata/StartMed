@props([
    // Endpoint que responde: route('asistente') o route('portal.asistente').
    'url',
    // Clave de sessionStorage: tiene que distinguir area y usuario, para que
    // otra persona que entre en la misma pestaña no herede la conversacion.
    'clave',
    'subtitulo' => 'Te ayudo a usar StartMed',
    'bienvenida' => '¡Hola! Preguntame cómo hacer algo en el sistema o qué significa lo que ves en pantalla.',
    'aviso' => 'No escribas datos de pacientes. Respondo sobre el uso del sistema, no veo cirugías ni historias clínicas.',
])

{{--
    Asistente de uso. Lo maneja resources/js/app.js (sin librerias) y solo se
    dibuja si hay GEMINI_API_KEY: sin clave el endpoint responde 404, asi que
    un boton que no anda es peor que no mostrarlo.

    Los iconos (chat, close, restart_alt, send) tienen que estar en el
    icon_names del layout que lo incluye.
--}}
@if (\App\Http\Controllers\AsistenteController::habilitado())
    <div
        id="asistente"
        data-url="{{ $url }}"
        data-token="{{ csrf_token() }}"
        data-clave="{{ $clave }}"
        data-bienvenida="{{ $bienvenida }}"
        data-max-mensajes="{{ config('asistente.max_mensajes') }}"
        class="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-3 print:hidden"
    >
        <section
            id="asistente-panel"
            hidden
            aria-labelledby="asistente-titulo"
            class="flex h-[min(34rem,calc(100dvh-6rem))] w-[min(24rem,calc(100vw-2rem))] flex-col
                   overflow-hidden rounded-2xl border border-hu-gris-suave/70 bg-white shadow-xl"
        >
            <header class="flex items-center gap-3 bg-hu-azul px-4 py-3 text-white">
                <x-icono nombre="chat" class="text-xl text-hu-dorado" relleno />

                <div class="min-w-0 flex-1">
                    <h2 id="asistente-titulo" class="text-sm font-semibold">Asistente</h2>
                    <p class="truncate text-xs text-white/70">{{ $subtitulo }}</p>
                </div>

                <button
                    type="button"
                    data-asistente-reiniciar
                    class="flex size-8 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white"
                    title="Nueva conversación"
                    aria-label="Nueva conversación"
                >
                    <x-icono nombre="restart_alt" class="text-xl" />
                </button>

                <button
                    type="button"
                    data-asistente-cerrar
                    class="flex size-8 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white"
                    aria-label="Cerrar asistente"
                >
                    <x-icono nombre="close" class="text-xl" />
                </button>
            </header>

            <p class="border-b border-hu-dorado/30 bg-hu-dorado-tenue px-4 py-2 text-xs text-hu-gris">
                {{ $aviso }}
            </p>

            <ol
                id="asistente-mensajes"
                class="flex-1 space-y-3 overflow-y-auto px-4 py-4"
                aria-live="polite"
            ></ol>

            <form id="asistente-form" class="flex items-end gap-2 border-t border-hu-gris-suave/70 px-3 py-3">
                <label for="asistente-texto" class="sr-only">Tu pregunta</label>
                <textarea
                    id="asistente-texto"
                    rows="2"
                    maxlength="{{ config('asistente.max_caracteres') }}"
                    required
                    placeholder="Escribí tu pregunta…"
                    class="min-w-0 flex-1 resize-none rounded-xl border border-hu-gris-suave px-3 py-2 text-sm
                           text-hu-gris placeholder:text-hu-gris-medio focus:border-hu-dorado focus:outline-none
                           focus:ring-2 focus:ring-hu-dorado/30"
                ></textarea>

                <button
                    type="submit"
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-hu-azul text-white
                           transition-colors hover:bg-hu-azul-claro disabled:cursor-not-allowed disabled:opacity-50"
                    aria-label="Enviar"
                >
                    <x-icono nombre="send" class="text-xl" />
                </button>
            </form>
        </section>

        <button
            type="button"
            id="asistente-abrir"
            aria-controls="asistente-panel"
            aria-expanded="false"
            class="flex size-14 items-center justify-center rounded-full bg-hu-azul text-white shadow-lg
                   ring-2 ring-hu-dorado/60 transition-colors hover:bg-hu-azul-claro"
            title="Asistente"
        >
            <x-icono nombre="chat" class="text-2xl" relleno />
            <span class="sr-only">Abrir asistente</span>
        </button>
    </div>
@endif
