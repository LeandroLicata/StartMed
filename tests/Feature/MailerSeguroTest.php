<?php

namespace Tests\Feature;

use App\Support\MailerSeguro;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La deteccion del mailer que deja los mails legibles en el servidor. Que el
 * acceso al portal no emita nada en ese caso se prueba en PortalAccesoTest;
 * aca solo la pregunta en si.
 */
class MailerSeguroTest extends TestCase
{
    public static function mailers(): array
    {
        return [
            'produccion con log' => ['production', 'log', true],
            'produccion con array' => ['production', 'array', true],
            'staging con log' => ['staging', 'log', true],
            'failover que incluye log' => ['production', 'failover', true],
            'roundrobin sin log ni array' => ['production', 'roundrobin', false],
            'produccion con smtp' => ['production', 'smtp', false],
            'produccion con brevo' => ['production', 'brevo', false],
            'local con log sigue enviando' => ['local', 'log', false],
            'testing con log sigue enviando' => ['testing', 'log', false],
        ];
    }

    #[DataProvider('mailers')]
    public function test_detecta_si_el_mailer_deja_los_mails_legibles(string $entorno, string $mailer, bool $expone): void
    {
        $this->app['env'] = $entorno;
        config(['mail.default' => $mailer]);

        $this->assertSame($expone, MailerSeguro::exponeLosMails());
    }

    public function test_un_failover_o_roundrobin_se_revisa_mailer_por_mailer(): void
    {
        $this->app['env'] = 'production';
        config([
            'mail.default' => 'compuesto',
            'mail.mailers.compuesto' => ['transport' => 'failover', 'mailers' => ['smtp', 'anidado']],
            'mail.mailers.anidado' => ['transport' => 'roundrobin', 'mailers' => ['ses', 'log']],
        ]);

        $this->assertTrue(MailerSeguro::exponeLosMails());

        config(['mail.mailers.anidado.mailers' => ['ses', 'postmark']]);
        $this->assertFalse(MailerSeguro::exponeLosMails());

        // Un ciclo entre mailers no cuelga la verificacion.
        config(['mail.mailers.anidado.mailers' => ['compuesto']]);
        $this->assertFalse(MailerSeguro::exponeLosMails());
    }
}
