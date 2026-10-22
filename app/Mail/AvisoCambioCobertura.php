<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al gestor: un paciente con cirugias pendientes cambio su obra social
 * predeterminada. Solo avisa; la autorizacion (AutCirugia) no se toca.
 * No es ShouldQueue: se manda con defer(), como EnlaceAccesoPortal.
 */
class AvisoCambioCobertura extends Mailable
{
    /**
     * @param  array<int, string>  $cirugias  Una linea por cirugia pendiente.
     */
    public function __construct(
        public string $paciente,
        public ?string $coberturaAnterior,
        public ?string $coberturaNueva,
        public array $cirugias,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cambio de obra social: '.$this->paciente);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.aviso-cambio-cobertura');
    }
}
