<?php

namespace App\Mail;

use App\Support\EnlaceAccesoPaciente;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * El link para entrar al portal. No es ShouldQueue a proposito: se manda
 * despues de la respuesta (ver AccesoController::solicitar), porque en el
 * deploy no hay worker de cola que lo saque.
 */
class EnlaceAccesoPortal extends Mailable
{
    public function __construct(
        public string $enlace,
        public string $nombre,
        public int $minutos = EnlaceAccesoPaciente::VIGENCIA_MINUTOS,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu enlace para ingresar al portal del paciente');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.enlace-acceso-portal');
    }
}
