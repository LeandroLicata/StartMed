<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mensaje enviado por el paciente al equipo de quirófano desde el portal.
 * No se guarda en la base de datos: se manda por mail a los gestores vigentes.
 * No es ShouldQueue: se manda con defer().
 */
class MensajePacienteAlEquipo extends Mailable
{
    public function __construct(
        public string $paciente,
        public string $dni,
        public string $tipoCirugia,
        public ?string $fechaCirugia,
        public int $idCirugia,
        public string $mensaje,
        public ?string $replyToEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        if ($this->replyToEmail) {
            $replyTo[] = new Address($this->replyToEmail, $this->paciente);
        }

        return new Envelope(
            subject: 'Mensaje de paciente: '.$this->paciente,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.mensaje-paciente-al-equipo');
    }
}
