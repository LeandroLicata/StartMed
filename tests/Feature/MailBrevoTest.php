<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

/**
 * El mailer `brevo` del deploy. Solo se arma el transporte: nada de esto
 * manda un mail ni sale a la red.
 */
class MailBrevoTest extends TestCase
{
    public function test_el_mailer_brevo_va_por_la_api_y_no_por_smtp(): void
    {
        config(['services.brevo.key' => 'xkeysib-de-prueba']);

        $transporte = Mail::mailer('brevo')->getSymfonyTransport();

        // Por la API (HTTPS): el plan free de Render bloquea los puertos SMTP.
        $this->assertInstanceOf(BrevoApiTransport::class, $transporte);
        $this->assertSame('brevo+api://api.brevo.com', (string) $transporte);
    }

    public function test_sin_api_key_falla_diciendo_que_variable_falta(): void
    {
        config(['services.brevo.key' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BREVO_API_KEY');

        Mail::mailer('brevo');
    }
}
