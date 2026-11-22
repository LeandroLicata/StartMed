<?php

namespace App\Support;

/**
 * Las secciones del menu lateral del staff.
 *
 * Viven aca y no en partials/nav.blade.php porque las leen dos lugares: el
 * menu, que las dibuja, y el asistente (InstruccionesAsistente), que le
 * explica a cada usuario que pantallas tiene. Una sola lista hace que el
 * asistente no pueda quedar describiendo un menu que ya no existe.
 *
 * Cada item declara los roles que lo ven, y se resuelven con
 * Usuario::tieneRolPropio: el comodin del administrador es para *entrar* a
 * una ruta, no para llenarle el menu con el trabajo de los demas. Un
 * administrador ve su bloque; si ademas es gestor, ve tambien el de gestor.
 * Los items sin pantalla todavia ('ruta' => null) quedan atenuados y no
 * navegan.
 *
 * El menu es lo que el usuario *hace*, y administrar tambien es hacer: cada
 * modulo de administracion es su propia seccion. Lo que sigue entrando por
 * una sola puerta son los 27 catalogos, agrupados en /admin; no sumar atajos
 * sueltos aca, duplicarian ese indice.
 *
 * 'activaEn' permite marcar el item con un patron de rutas (admin.*) cuando
 * la seccion abarca mas de una pantalla; por defecto es su ruta.
 * 'titulo' abre un grupo: dibuja una linea y un rotulo encima del item.
 */
final class Navegacion
{
    public const SECCIONES = [
        [
            'etiqueta' => 'Tablero',
            'icono' => 'home',
            'ruta' => 'dashboard',
            'roles' => ['Gestor de quirófano', 'Dirección médica'],
        ],
        [
            'etiqueta' => 'Cirugías',
            'icono' => 'event',
            'ruta' => 'cirugias.index',
            'roles' => ['Gestor de quirófano'],
        ],
        [
            'etiqueta' => 'Agenda',
            'icono' => 'schedule',
            'ruta' => 'agenda',
            'roles' => ['Gestor de quirófano'],
        ],
        [
            'etiqueta' => 'Mis cirugías',
            'icono' => 'personal_injury',
            'ruta' => 'cirujano',
            'roles' => ['Cirujano'],
        ],
        [
            'etiqueta' => 'Evaluaciones',
            'icono' => 'stethoscope',
            'ruta' => 'anestesista',
            'roles' => ['Anestesista'],
        ],
        [
            'etiqueta' => 'Pacientes',
            'icono' => 'groups',
            'ruta' => 'pacientes.index',
            'activaEn' => 'pacientes.*',
            'roles' => ['Gestor de quirófano', 'Cirujano', 'Anestesista'],
        ],
        [
            'etiqueta' => 'Agenda',
            'icono' => 'schedule',
            'ruta' => 'cirujano.agenda',
            'roles' => ['Cirujano', 'Anestesista'],
        ],
        [
            'etiqueta' => 'Historial de cirugías',
            'icono' => 'history',
            'ruta' => 'cirujano.historial',
            'activaEn' => 'cirujano.historial',
            'roles' => ['Cirujano', 'Anestesista'],
        ],
        [
            'etiqueta' => 'Dirección',
            'icono' => 'monitoring',
            'ruta' => 'direccion',
            'roles' => ['Dirección médica'],
        ],

        /*
         * Administracion. Cada item queda marcado en todas las pantallas de su
         * modulo, no solo en el indice. 'Catálogos' va ultimo y apunta a
         * /admin, que quedo siendo eso: el indice de las tablas maestras.
         */
        [
            'etiqueta' => 'Usuarios',
            'icono' => 'manage_accounts',
            'ruta' => 'admin.usuarios.index',
            'activaEn' => 'admin.usuarios.*',
            'titulo' => 'Administración',
            'roles' => ['Administrador'],
        ],
        [
            'etiqueta' => 'Personas',
            'icono' => 'badge',
            'ruta' => 'admin.personas.index',
            'activaEn' => 'admin.personas.*',
            'roles' => ['Administrador'],
        ],
        [
            'etiqueta' => 'Consentimientos',
            'icono' => 'draw',
            'ruta' => 'admin.consentimientos.index',
            'activaEn' => 'admin.consentimientos.*',
            'roles' => ['Administrador'],
        ],
        /*
         * El unico item de este bloque que no es solo del administrador. Para
         * el anestesista no aparece bajo el rotulo 'Administración' —cuelga del
         * item Usuarios, que el no ve— sino al final de su propia lista, que es
         * donde corresponde: el cuestionario es su herramienta de trabajo.
         */
        [
            'etiqueta' => 'Cuestionario preanestésico',
            'icono' => 'assignment',
            'ruta' => 'admin.cuestionario.index',
            'activaEn' => 'admin.cuestionario.*',
            'roles' => ['Administrador', 'Anestesista'],
        ],
        [
            'etiqueta' => 'Proveedores y precios',
            'icono' => 'inventory_2',
            'ruta' => 'admin.precios.index',
            'activaEn' => 'admin.precios.*',
            'roles' => ['Administrador'],
        ],
        [
            'etiqueta' => 'Auditoría',
            'icono' => 'description',
            'ruta' => 'admin.auditoria',
            'roles' => ['Administrador'],
        ],
        [
            'etiqueta' => 'Catálogos',
            'icono' => 'folder_open',
            'ruta' => 'admin.inicio',
            // El indice y las pantallas de cada tabla, que cuelgan de el.
            'activaEn' => ['admin.inicio', 'admin.catalogos.*'],
            'roles' => ['Administrador'],
        ],
    ];

    /**
     * Las tres secciones de la barra superior del portal del paciente
     * (layouts/portal). 'descripcion' es el title del link y la etiqueta con
     * la que el asistente ofrece ir a cada una.
     */
    public const PORTAL = [
        [
            'etiqueta' => 'Próximas',
            'descripcion' => 'Próximas cirugías',
            'icono' => 'event',
            'ruta' => 'portal.inicio',
            'activaEn' => ['portal.inicio', 'portal.cirugias.*'],
        ],
        [
            'etiqueta' => 'Historial',
            'descripcion' => 'Historial de cirugías',
            'icono' => 'history',
            'ruta' => 'portal.historial',
            'activaEn' => ['portal.historial'],
        ],
        [
            'etiqueta' => 'Perfil',
            'descripcion' => 'Mi perfil',
            'icono' => 'person',
            'ruta' => 'portal.perfil',
            'activaEn' => ['portal.perfil'],
        ],
    ];

    /**
     * Las secciones que ve quien tiene estos roles, con la misma regla que el
     * menu: roles propios, sin el comodin del administrador.
     *
     * @param  iterable<string>  $roles
     * @return list<array<string, mixed>>
     */
    public static function para(iterable $roles): array
    {
        $roles = collect($roles);

        return array_values(array_filter(
            self::SECCIONES,
            fn (array $seccion) => $seccion['roles'] === [] || $roles->intersect($seccion['roles'])->isNotEmpty(),
        ));
    }
}
