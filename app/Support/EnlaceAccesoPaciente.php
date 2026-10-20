<?php

namespace App\Support;

use App\Models\Persona;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * El link de un solo uso con el que un paciente entra al portal.
 *
 * La firma (`URL::temporarySignedRoute`) prueba que el link lo armo la
 * aplicacion y todavia no vencio; el nonce es lo que lo hace de un solo uso, y
 * vive en Cache para no sumar una tabla. Va atado al id de la Persona: pedir un
 * link nuevo reemplaza al anterior, asi que nunca hay dos vigentes a la vez.
 *
 * En Cache se guarda el hash del nonce, no el nonce: quien pueda leer la cache
 * no puede armar un link.
 *
 * Emitir y consumir se hacen dentro de un lock por persona (la misma clave en
 * los dos): sin el, un link viejo que pasa la verificacion en `consumir()` podia
 * borrar, antes de su `pull`, el nonce de un link que se acababa de pedir. Con
 * el lock, verificar y borrar son un solo paso. Si no se consigue el lock, el
 * link se trata como invalido. Requiere un CACHE_STORE con locks (database,
 * array, file, redis...): `database` usa la tabla `cache_locks` de la migracion
 * base, asi que no hay tabla nueva.
 *
 * `consumir()` es lo unico que quema el nonce, y lo llama el POST del boton
 * «Entrar», no el GET del link. Hay clientes de correo que abren los links para
 * analizarlos: si el GET lo quemara, el paciente haria clic en un link ya usado.
 */
class EnlaceAccesoPaciente
{
    public const VIGENCIA_MINUTOS = 15;

    /** Cuanto puede quedar tomado el lock si el proceso muere a la mitad. */
    private const LOCK_SEGUNDOS = 5;

    /** Cuanto espera un pedido por el lock antes de rendirse. */
    private const ESPERA_LOCK_SEGUNDOS = 2;

    /** Transportes que dejan el mail legible en el servidor en vez de entregarlo. */
    private const TRANSPORTES_QUE_EXPONEN = ['log', 'array'];

    /** Transportes que delegan en otros mailers de `config('mail.mailers')`. */
    private const TRANSPORTES_COMPUESTOS = ['failover', 'roundrobin'];

    /**
     * Devuelve la URL firmada que se manda por mail.
     *
     * @throws LockTimeoutException si otro pedido de la misma persona tiene el lock
     */
    public static function emitir(Persona $persona): string
    {
        $nonce = Str::random(40);

        self::conLock($persona->idPersona, fn () => Cache::put(
            self::clave($persona->idPersona),
            hash('sha256', $nonce),
            now()->addMinutes(self::VIGENCIA_MINUTOS),
        ));

        return URL::temporarySignedRoute(
            'portal.acceso.entrar',
            now()->addMinutes(self::VIGENCIA_MINUTOS),
            ['persona' => $persona->idPersona, 'nonce' => $nonce],
        );
    }

    /** ¿Sigue vigente? No lo quema: es lo que mira el GET antes de mostrar el boton. */
    public static function vigente(int $idPersona, string $nonce): bool
    {
        $guardado = Cache::get(self::clave($idPersona));

        return is_string($guardado) && hash_equals($guardado, hash('sha256', $nonce));
    }

    /**
     * Lo usa una sola vez. Verificar y borrar van juntos dentro del lock: un
     * link viejo (reemplazado por otro mas nuevo) falla en la verificacion sin
     * tocar el nonce del nuevo, y dos pedidos simultaneos del mismo link no
     * pueden entrar los dos.
     */
    public static function consumir(int $idPersona, string $nonce): bool
    {
        try {
            return self::conLock($idPersona, function () use ($idPersona, $nonce) {
                if (! self::vigente($idPersona, $nonce)) {
                    return false;
                }

                Cache::forget(self::clave($idPersona));

                return true;
            });
        } catch (LockTimeoutException) {
            // No se pudo verificar con seguridad: el link no sirve (se pide otro).
            return false;
        }
    }

    /**
     * ¿El mailer por defecto dejaria el link legible en el servidor? Con
     * `log` el mail entero, link incluido, se escribe en el log, y quien lo lea
     * entra como el paciente: por eso fuera de `local` y `testing` no se emite
     * ni se manda nada. Tambien mira `failover` y `roundrobin`, que valen lo que
     * valga el peor de sus mailers.
     *
     * Limite conocido: solo ve los transportes `log` y `array`. Un mailer
     * propio que escriba el mail en otro lado no se detecta.
     */
    public static function mailerExponeElEnlace(): bool
    {
        if (app()->environment('local', 'testing')) {
            return false;
        }

        return self::exponeElMail((string) config('mail.default'));
    }

    /** @param  list<string>  $visitados  corta los ciclos entre mailers compuestos */
    private static function exponeElMail(string $mailer, array $visitados = []): bool
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
                if (self::exponeElMail((string) $hijo, [...$visitados, $mailer])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @throws LockTimeoutException */
    private static function conLock(int $idPersona, Closure $accion): mixed
    {
        return Cache::lock(self::clave($idPersona).':lock', self::LOCK_SEGUNDOS)
            ->block(self::ESPERA_LOCK_SEGUNDOS, $accion);
    }

    private static function clave(int $idPersona): string
    {
        return "portal:acceso:{$idPersona}";
    }
}
