<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Link firmado para confirmar un email nuevo del paciente. Se manda al correo
 * NUEVO: si llega y lo abre, el correo es suyo. No es ShouldQueue: se manda
 * con defer(), como EnlaceAccesoPortal.
 */
class ConfirmarCambioEmail extends Mailable
{
    /** Cuánto dura el link antes de vencer. */
    public const VIGENCIA_MINUTOS = 60;

    public function __construct(
        public string $enlace,
        public string $nombre,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirmá tu nuevo email en el portal del paciente');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.confirmar-cambio-email');
    }
}