<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cookie de sesion para el portal del paciente y otra para el staff.
 *
 * Los dos guards (`web` y `paciente`) compartian una sola sesion, y con ella
 * un solo token CSRF: salir del portal regeneraba el token y daba 419 en el
 * login del staff abierto en otra pestaña, y salir del staff invalidaba la
 * sesion entera y cerraba tambien la del paciente. Con una cookie por area,
 * lo que pasa en una no toca a la otra.
 *
 * Decide por la URL: todo lo del paciente vive bajo /portal (la vista previa
 * del staff, /cirugias/{cirugia}/portal-paciente, es del staff). Una ruta del
 * paciente fuera de /portal no encontraria su sesion.
 *
 * Tiene que correr antes de StartSession, que es quien lee la cookie por el
 * nombre del store (ver bootstrap/app.php). Se fija el nombre en cada request,
 * no solo en las del portal: el store se reusa entre requests (los tests lo
 * hacen) y no puede quedar con el nombre del area anterior.
 */
class SesionPorArea
{
    public const SUFIJO_PORTAL = '-portal';

    public function __construct(private readonly SessionManager $sesiones) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->sesiones->driver()->setName(self::cookiePara($request));

        return $next($request);
    }

    public static function cookiePara(Request $request): string
    {
        $base = config('session.cookie');

        return $request->is('portal', 'portal/*') ? $base.self::SUFIJO_PORTAL : $base;
    }
}
