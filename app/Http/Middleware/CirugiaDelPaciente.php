<?php

namespace App\Http\Middleware;

use App\Models\Cirugia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La unica verificacion de pertenencia del portal del paciente: la cirugia de
 * la ruta tiene que ser del paciente logueado.
 *
 * Responde 404, no 403: un 403 confirmaria que esa cirugia existe, y el id es
 * un numero correlativo que cualquiera puede ir probando. Va en el subgrupo
 * `portal/cirugias/{cirugia}` de routes/web.php, asi que toda ruta que se
 * agregue ahi la hereda sin que nadie la reimplemente en su controlador.
 */
class CirugiaDelPaciente
{
    public function handle(Request $request, Closure $next): Response
    {
        $cirugia = $request->route('cirugia');
        $paciente = $request->user('paciente');

        if (! $cirugia instanceof Cirugia
            || ! $paciente
            || (int) $cirugia->idPersonaPaciente !== (int) $paciente->getAuthIdentifier()) {
            abort(404);
        }

        return $next($request);
    }
}
