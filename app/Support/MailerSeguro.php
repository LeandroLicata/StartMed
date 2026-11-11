<?php

namespace App\Support;

/**
 * ¿El mailer por defecto entrega los mails, o los deja legibles en el servidor?
 *
 * Con `log` el mail entero se escribe en el log, y con `array` queda en
 * memoria: si ese mail lleva un link que da acceso a algo (entrar al portal,
 * confirmar un email nuevo), quien lea el log lo usa. Por eso todo mail que
 * lleve un link de ese tipo pregunta aca antes de emitirlo, y fuera de `local`
 * y `testing` no se manda nada si el mailer los expone.
 *
 * Vivia dentro de `EnlaceAccesoPaciente`; salio de ahi cuando aparecio un
 * segundo link sensible (el cambio de email del perfil) que necesita la misma
 * pregunta.
 *
 * Limite conocido: solo reconoce los transportes `log` y `array`. Un mailer
 * propio que escriba el mail en otro lado no se detecta.
 */
class MailerSeguro
{
    /** Transportes que dejan el mail legible en el servidor en vez de entregarlo. */
    private const TRANSPORTES_QUE_EXPONEN = ['log', 'array'];

    /** Transportes que delegan en otros mailers de `config('mail.mailers')`. */
    private const TRANSPORTES_COMPUESTOS = ['failover', 'roundrobin'];

    /**
     * En `local` y `testing` siempre es false: ahi leer el mail en el log es
     * justamente como se prueba. `failover` y `roundrobin` valen lo que valga
     * el peor de sus mailers.
     */
    public static function exponeLosMails(): bool
    {
        if (app()->environment('local', 'testing')) {
            return false;
        }

        return self::expone((string) config('mail.default'));
    }

    /** @param  list<string>  $visitados  corta los ciclos entre mailers compuestos */
    private static function expone(string $mailer, array $visitados = []): bool
    {
        if (in_array($mailer, $visitados, true)) {
            return false;
        }

        $config = config("mail.mailers.{$mailer}") ?? [];
        $transporte = $config['transport'] ?? $mailer;

        if (in_array($transporte, self::TRANSPORTES_QUE_EXPONEN, true)) {
            return true;
        }

        if (in_array($transporte, self::TRANSPORTES_COMPUESTOS, true)) {
            foreach ($config['mailers'] ?? [] as $hijo) {
                if (self::expone((string) $hijo, [...$visitados, $mailer])) {
                    return true;
                }
            }
        }

        return false;
    }
}
