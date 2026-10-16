<?php

namespace App\Support;

use App\Models\Persona;
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
 * `consumir()` es lo unico que quema el nonce, y lo llama el POST del boton
 * «Entrar», no el GET del link. Hay clientes de correo que abren los links para
 * analizarlos: si el GET lo quemara, el paciente haria clic en un link ya usado.
 */
class EnlaceAccesoPaciente
{
    public const VIGENCIA_MINUTOS = 15;

    /** Devuelve la URL firmada que se manda por mail. */
    public static function emitir(Persona $persona): string
    {
        $nonce = Str::random(40);

        Cache::put(self::clave($persona->idPersona), hash('sha256', $nonce), now()->addMinutes(self::VIGENCIA_MINUTOS));

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
     * Lo usa una sola vez: `pull` lee y borra de una, asi que si dos pedidos
     * llegan juntos solo uno se lleva el valor.
     */
    public static function consumir(int $idPersona, string $nonce): bool
    {
        // Se mira antes de sacar: un link viejo (reemplazado por otro mas
        // nuevo) no tiene que llevarse el nonce del nuevo al fallar.
        if (! self::vigente($idPersona, $nonce)) {
            return false;
        }

        $guardado = Cache::pull(self::clave($idPersona));

        return is_string($guardado) && hash_equals($guardado, hash('sha256', $nonce));
    }

    private static function clave(int $idPersona): string
    {
        return "portal:acceso:{$idPersona}";
    }
}
