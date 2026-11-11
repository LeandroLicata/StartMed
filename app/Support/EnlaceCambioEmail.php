<?php

namespace App\Support;

use App\Models\Persona;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * El link de un solo uso que confirma el email nuevo de un paciente.
 *
 * Mismo esquema que EnlaceAccesoPaciente, por las mismas razones: la firma
 * prueba que el link lo armo la aplicacion y no vencio, el nonce en Cache lo
 * hace de un solo uso, y emitir/consumir van dentro de un lock por persona.
 * Pedir otro cambio reemplaza al anterior, asi que un link viejo nunca puede
 * pisar un email pedido despues.
 *
 * La diferencia: el email nuevo se guarda en Cache junto al hash del nonce, no
 * en la URL. El link solo lleva persona y nonce, y lo que se confirma es lo
 * que el paciente pidio, no lo que diga el link.
 *
 * Confirmar no exige sesion: el paciente puede abrir el mail en el celular,
 * donde no entro al portal. Lo que prueba que el pedido es legitimo es que el
 * link llego a ese buzon; el pedido en si ya lo hizo con la sesion abierta.
 * Como el link alcanza para cambiar el email, se emite solo si el mailer no lo
 * deja legible en el servidor (MailerSeguro).
 */
class EnlaceCambioEmail
{
    public const VIGENCIA_MINUTOS = 60;

    /** Cuanto puede quedar tomado el lock si el proceso muere a la mitad. */
    private const LOCK_SEGUNDOS = 5;

    /** Cuanto espera un pedido por el lock antes de rendirse. */
    private const ESPERA_LOCK_SEGUNDOS = 2;

    /**
     * Devuelve la URL firmada que se manda al email nuevo.
     *
     * @throws LockTimeoutException si otro pedido de la misma persona tiene el lock
     */
    public static function emitir(Persona $persona, string $emailNuevo): string
    {
        $nonce = Str::random(40);

        self::conLock($persona->idPersona, fn () => Cache::put(
            self::clave($persona->idPersona),
            ['nonce' => hash('sha256', $nonce), 'email' => $emailNuevo],
            now()->addMinutes(self::VIGENCIA_MINUTOS),
        ));

        return URL::temporarySignedRoute(
            'portal.perfil.email.confirmar',
            now()->addMinutes(self::VIGENCIA_MINUTOS),
            ['persona' => $persona->idPersona, 'nonce' => $nonce],
        );
    }

    /** El email pendiente si el link sigue vigente. No lo quema: es lo que mira el GET. */
    public static function pendiente(int $idPersona, string $nonce): ?string
    {
        $guardado = Cache::get(self::clave($idPersona));

        if (! is_array($guardado) || ! is_string($guardado['nonce'] ?? null)) {
            return null;
        }

        return hash_equals($guardado['nonce'], hash('sha256', $nonce))
            ? $guardado['email']
            : null;
    }

    /**
     * Lo usa una sola vez y devuelve el email a guardar, o null si el link ya
     * no sirve. Verificar y borrar van juntos dentro del lock.
     */
    public static function consumir(int $idPersona, string $nonce): ?string
    {
        try {
            return self::conLock($idPersona, function () use ($idPersona, $nonce) {
                $email = self::pendiente($idPersona, $nonce);

                if ($email !== null) {
                    Cache::forget(self::clave($idPersona));
                }

                return $email;
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    /** @throws LockTimeoutException */
    private static function conLock(int $idPersona, Closure $accion): mixed
    {
        return Cache::lock(self::clave($idPersona).':lock', self::LOCK_SEGUNDOS)
            ->block(self::ESPERA_LOCK_SEGUNDOS, $accion);
    }

    private static function clave(int $idPersona): string
    {
        return "portal:cambio-email:{$idPersona}";
    }
}
