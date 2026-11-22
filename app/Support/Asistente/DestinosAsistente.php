<?php

namespace App\Support\Asistente;

use App\Models\Usuario;
use App\Support\Navegacion;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Las pantallas a las que el asistente puede ofrecer un boton «Ir a …».
 *
 * El modelo nunca decide a donde lleva un boton: responde texto plano, y el
 * widget agrega un boton solo por cada direccion de esta lista que aparezca
 * en la respuesta. Una direccion inventada, una URL externa o una pantalla a
 * la que el usuario no puede entrar no generan nada; lo peor que puede pasar
 * es que falte un boton, nunca que lleve a un 404 ni a otro sitio.
 *
 * La lista sale de las mismas fuentes que el prompt (el menu del rol y las
 * direcciones que nombran sus guias), y cada destino se confirma contra la
 * ruta real: GET, sin parametros, con el guard del area y, en el staff, con
 * los roles del middleware `rol`. Esa ultima es la pregunta de permiso
 * (tieneRol, con el comodin del administrador), no la de pertenencia.
 */
final class DestinosAsistente
{
    /**
     * Etiquetas de las pantallas que las guias nombran y no estan en ningun
     * menu. Una direccion nueva en una guia sin etiqueta aca ni en el menu
     * hace fallar AsistenteTest.
     */
    public const ETIQUETAS = [
        '/cirugias/nueva' => 'Nueva cirugía',
        '/portal/ingresar' => 'Ingresar al portal',
    ];

    /**
     * @return array<string, string> direccion => etiqueta
     */
    public static function paraStaff(Usuario $usuario): array
    {
        $roles = $usuario->roles();

        return self::filtrar(
            self::candidatos(InstruccionesAsistente::archivosStaff($roles), Navegacion::para($roles)),
            fn (Ruta $ruta) => self::exigeGuard($ruta, 'web') && self::rolesPermiten($ruta, $usuario),
        );
    }

    /**
     * @return array<string, string> direccion => etiqueta
     */
    public static function paraPaciente(): array
    {
        $menu = array_map(
            fn (array $seccion) => [...$seccion, 'etiqueta' => $seccion['descripcion']],
            Navegacion::PORTAL,
        );

        return self::filtrar(
            self::candidatos(['paciente'], $menu),
            fn (Ruta $ruta) => self::exigeGuard($ruta, 'paciente'),
        );
    }

    /**
     * Todas las etiquetas conocidas, sin filtrar por permiso. La usa el test
     * que exige que cada direccion de las guias tenga una.
     *
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return self::candidatos(InstruccionesAsistente::archivos(), [...Navegacion::SECCIONES, ...array_map(
            fn (array $seccion) => [...$seccion, 'etiqueta' => $seccion['descripcion']],
            Navegacion::PORTAL,
        )])->all();
    }

    /**
     * Las direcciones que una guia nombra entre comillas invertidas.
     *
     * @return list<string>
     */
    public static function direccionesDe(string $archivo): array
    {
        preg_match_all('/`(\/[^`\s]*)`/', InstruccionesAsistente::ayuda($archivo), $coincidencias);

        return array_values(array_unique($coincidencias[1]));
    }

    /**
     * El menu primero (sus etiquetas son las que el usuario reconoce), despues
     * lo que nombran las guias. Una direccion sin etiqueta queda afuera.
     *
     * @param  list<string>  $archivos
     * @param  list<array<string, mixed>>  $menu
     * @return Collection<string, string>
     */
    private static function candidatos(array $archivos, array $menu): Collection
    {
        $delMenu = collect($menu)
            ->filter(fn (array $seccion) => $seccion['ruta'])
            ->mapWithKeys(fn (array $seccion) => [route($seccion['ruta'], [], false) => $seccion['etiqueta']]);

        $deLasGuias = collect($archivos)
            ->flatMap(fn (string $archivo) => self::direccionesDe($archivo))
            ->unique()
            ->mapWithKeys(fn (string $direccion) => [$direccion => self::ETIQUETAS[$direccion] ?? null]);

        return $delMenu
            ->union($deLasGuias)
            ->map(fn (?string $etiqueta, string $direccion) => $etiqueta ?? self::etiquetaDeOtroMenu($direccion))
            ->filter();
    }

    /**
     * Una guia puede nombrar una pantalla que esta en el menu de otro rol
     * (staff.md nombra el panel de cada uno): toma la etiqueta de ese menu.
     */
    private static function etiquetaDeOtroMenu(string $direccion): ?string
    {
        foreach ([...Navegacion::SECCIONES, ...Navegacion::PORTAL] as $seccion) {
            if ($seccion['ruta'] && route($seccion['ruta'], [], false) === $direccion) {
                return $seccion['descripcion'] ?? $seccion['etiqueta'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<string, string>  $candidatos
     * @param  callable(Ruta): bool  $permite
     * @return array<string, string>
     */
    private static function filtrar(Collection $candidatos, callable $permite): array
    {
        $permitidas = $candidatos->keys()->filter(function (string $direccion) use ($permite) {
            $ruta = self::rutaGet($direccion);

            return $ruta !== null && $ruta->parameterNames() === [] && $permite($ruta);
        });

        return $candidatos->only($permitidas->all())->all();
    }

    private static function rutaGet(string $direccion): ?Ruta
    {
        $uri = $direccion === '/' ? '/' : ltrim($direccion, '/');

        foreach (Route::getRoutes()->get('GET') as $ruta) {
            if ($ruta->uri() === $uri) {
                return $ruta;
            }
        }

        return null;
    }

    private static function exigeGuard(Ruta $ruta, string $guard): bool
    {
        return in_array("auth:$guard", $ruta->gatherMiddleware(), true);
    }

    /**
     * Cada middleware `rol:` de la ruta (de grupo o propio) tiene que pasar:
     * los anidados se suman, igual que en VerificarRol.
     */
    private static function rolesPermiten(Ruta $ruta, Usuario $usuario): bool
    {
        foreach ($ruta->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'rol:')
                && ! $usuario->tieneRol(...explode(',', substr($middleware, 4)))) {
                return false;
            }
        }

        return true;
    }
}
