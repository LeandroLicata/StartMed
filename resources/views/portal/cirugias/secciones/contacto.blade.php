{{--
    Pestaña "contacto" del detalle. Recibe $caso (App\Support\ResumenCirugia) y $soloLectura.
    No hay tablas de contacto ni de mensajes: los datos del hospital vienen de config
    y los mensajes se mandan por correo a los gestores de quirófano.
--}}

@php
    // wa.me solo acepta dígitos: se limpian espacios, guiones o el "+" que alguien cargue en el .env.
    $whatsapp = preg_replace('/\D/', '', (string) config('portal.whatsapp_hospital'));
    $telefono = config('portal.telefono_hospital');
    $email = config('portal.email_hospital');
    $direccion = config('portal.direccion_hospital');

    $hayContactoHospital = filled($whatsapp) || filled($telefono) || filled($email) || filled($direccion);

    $mensajeWa = $caso->cuando()
        ? 'Hola, tengo una consulta sobre mi cirugía del '.$caso->cuando()->translatedFormat('d/m/Y')
        : 'Hola, tengo una consulta sobre mi cirugía';
    $waUrl = filled($whatsapp) ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($mensajeWa) : null;

    $equipo = array_filter([
        'Cirujano/a' => $caso->cirujano(),
        'Anestesista' => $caso->anestesista(),
    ]);
@endphp

<div class="space-y-6">
    <div class="grid items-start gap-4 lg:grid-cols-2">
        <x-tarjeta titulo="Hablá con el hospital" icono="call">
            @if (! $hayContactoHospital)
                <p class="py-6 text-center text-sm text-hu-gris-medio">
                    Consultá en recepción del hospital.
                </p>
            @else
                <div class="space-y-4">
                    @if ($waUrl)
                        <div>
                            <x-boton
                                :href="$waUrl"
                                target="_blank"
                                rel="noopener"
                                variante="primario"
                                icono="chat"
                            >
                                Contactar por WhatsApp
                            </x-boton>
                        </div>
                    @endif

                    <dl class="divide-y divide-hu-gris-suave/70 text-sm">
                        @if (filled($telefono))
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2.5">
                                <dt class="text-hu-gris-medio">Teléfono</dt>
                                <dd class="text-right font-semibold text-hu-azul">
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $telefono) }}" class="hover:underline">
                                        {{ $telefono }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        @if (filled($email))
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2.5">
                                <dt class="text-hu-gris-medio">Email</dt>
                                <dd class="text-right font-semibold text-hu-azul">
                                    <a href="mailto:{{ $email }}" class="hover:underline">
                                        {{ $email }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        @if (filled($direccion))
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2.5">
                                <dt class="text-hu-gris-medio">Dirección</dt>
                                <dd class="text-right font-semibold text-hu-azul">
                                    {{ $direccion }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </x-tarjeta>

        <x-tarjeta titulo="Tu equipo" icono="person">
            @if (empty($equipo))
                <p class="py-6 text-center text-sm text-hu-gris-medio">
                    Tu equipo médico todavía no fue asignado.
                </p>
            @else
                <dl class="divide-y divide-hu-gris-suave/70 text-sm">
                    @foreach ($equipo as $rol => $nombre)
                        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2.5">
                            <dt class="text-hu-gris-medio">{{ $rol }}</dt>
                            <dd class="text-right font-semibold text-hu-azul">{{ $nombre }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-tarjeta>
    </div>

    @if (! $soloLectura)
        <x-tarjeta titulo="Mensaje para el equipo" icono="mail">
            <p class="mb-4 text-sm text-hu-gris-medio">
                Escribile a los gestores de quirófano sobre tu cirugía. Te van a responder por teléfono o email.
            </p>

            <form method="POST" action="{{ route('portal.cirugias.contacto', $caso->cirugia) }}" class="space-y-4">
                @csrf

                <x-textarea
                    nombre="mensaje"
                    etiqueta="Mensaje"
                    ayuda="Escribí tu consulta (entre 10 y 2000 caracteres)."
                    :filas="4"
                    requerido
                />

                <div>
                    <x-boton tipo="submit" variante="primario" icono="send">
                        Enviar mensaje
                    </x-boton>
                </div>
            </form>
        </x-tarjeta>
    @endif
</div>
