<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al email ANTERIOR de que el del portal cambio. Es la unica señal que
 * recibe el dueño si alguien con su sesion abierta lo cambio por otro: desde
 * ese momento los enlaces de acceso van al buzon nuevo.
 *
 * No dice cual es el email nuevo ni lleva ningun link.
 */
class AvisoEmailCambiado extends Mailable
{
    public function __construct(
        public string $nombre,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cambió tu email en el portal del paciente');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.aviso-email-cambiado');
    }
}
