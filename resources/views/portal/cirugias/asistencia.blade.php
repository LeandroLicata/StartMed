{{--
    Confirmacion de asistencia. A cargo de: Leandro. Recibe $caso.

    Va arriba de las pestañas y no dentro de Resumen: es lo unico del detalle
    que vence, asi que tiene que verse desde cualquier pestaña. Cuando ya hay
    respuesta, en lugar de los botones queda lo que se decidio.
--}}

@if ($caso->puedeConfirmarAsistencia())
    <section class="mt-4 rounded-2xl border border-hu-dorado/50 bg-hu-dorado-tenue px-5 py-4">
        <div class="flex items-start gap-3">
            <x-icono nombre="notifications" class="mt-0.5 text-2xl text-hu-dorado-oscuro" relleno />
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold text-hu-azul">Confirmá tu asistencia</h2>
                <p class="mt-1 text-sm text-hu-gris">
                    Tu cirugía es el {{ $caso->cuando()->translatedFormat('l j \d\e F') }}
                    a las {{ $caso->cuando()->format('H:i') }} hs
                    (tenés que llegar a las {{ $caso->horaLlegada()->format('H:i') }}).
                    ¿Vas a poder venir?
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('portal.cirugias.asistencia', $caso->cirugia) }}">
                        @csrf
                        <input type="hidden" name="respuesta" value="confirmar">
                        <x-boton icono="check_circle">Sí, voy a estar</x-boton>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('portal.cirugias.asistencia', $caso->cirugia) }}"
                        data-confirmar-titulo="¿Necesitás reprogramar?"
                        data-confirmar="La cirugía queda para reprogramar y el equipo del hospital se va a comunicar con vos para coordinar otra fecha. Desde el portal no se puede deshacer."
                        data-confirmar-accion="Sí, necesito reprogramar"
                    >
                        @csrf
                        <input type="hidden" name="respuesta" value="reprogramar">
                        <x-boton variante="contorno">Necesito reprogramar</x-boton>
                    </form>
                </div>
            </div>
        </div>
    </section>
@elseif ($caso->respuestaAsistencia() === 'confirmada')
    <x-alerta tipo="exito" titulo="Confirmaste tu asistencia" class="mt-4">
        Te esperamos el {{ $caso->cuando()?->translatedFormat('l j \d\e F') }}
        a las {{ $caso->horaLlegada()?->format('H:i') }} hs.
    </x-alerta>
@elseif ($caso->respuestaAsistencia() === 'reprogramar')
    {{-- Sin "pediste": este estado tambien lo pone el sistema cuando falta algo. --}}
    <x-alerta tipo="aviso" titulo="Tu cirugía está para reprogramar" class="mt-4">
        El equipo del hospital se va a comunicar con vos para coordinar una nueva fecha.
    </x-alerta>
@endif
