<?php

namespace App\Http\Middleware;

use App\Models\Cirugia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las escrituras sobre una cirugia: el rol deja entrar, esto decide si la
 * cirugia de la ruta es de quien la quiere modificar.
 *
 * Uso: `cirugia.del.equipo:Cirujano,Anestesista`, con los roles del equipo
 * que la accion admite. La regla vive en `Usuario::puedeModificarCirugia()`.
 *
 * Responde 403, no 404 como `cirugia.del.paciente`: el expediente se puede
 * consultar desde cualquier cuenta del staff, asi que la existencia de la
 * cirugia no es un secreto. Va en las rutas de escritura de routes/web.php,
 * para que ningun controlador tenga que acordarse de chequearlo.
 */
class CirugiaDelEquipo
{
    public function handle(Request $request, Closure $next, string ...$rolesDelEquipo): Response
    {
        $cirugia = $request->route('cirugia');

        abort_unless($cirugia instanceof Cirugia, 404);

        abort_unless(
            $request->user()?->puedeModificarCirugia($cirugia, ...$rolesDelEquipo),
            403,
            'Esta cirugía no te está asignada.',
        );

        return $next($request);
    }
}
