<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Models\EstadoAutCirugia;
use App\Models\EstadoCirugia;
use App\Models\EstadoEvaluacionAnestesica;
use App\Models\EstadoPedidoHemoderivado;
use App\Models\EstadoPedidoMaterial;
use App\Models\EstadoPedidoTipoHemoderivado;
use App\Models\EstadoQuirofano;
use App\Models\GrupoSanguineo;
use App\Models\Material;
use App\Models\ObraSocial;
use App\Models\Plan;
use App\Models\Profilaxis;
use App\Models\ProfilaxisRol;
use App\Models\Proveedor;
use App\Models\Quirofano;
use App\Models\Rol;
use App\Models\TipoAnestesia;
use App\Models\TipoASA;
use App\Models\TipoCirugia;
use App\Models\TipoDocumento;
use App\Models\TipoEstudio;
use App\Models\TipoHemoderivado;
use App\Models\TipoIndicacion;
use App\Models\TipoMedida;
use App\Models\TipoPreparacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Las tablas maestras que administra la seccion de administracion, declaradas
 * en un solo lugar. Casi todas tienen la misma forma (id<X> / nombre<X> /
 * fechaBaja<X>), asi que un controlador generico las cubre a todas y este mapa
 * es lo unico que hay que tocar para sumar una.
 *
 * Cada entrada:
 *   modelo   El modelo Eloquent. La PK y la tabla salen de ahi.
 *   singular Como se nombra un registro ("Tipo de estudio").
 *   plural   Titulo del listado ("Tipos de estudio").
 *   grupo    Con que otros catalogos se agrupa en /admin. Ver GRUPOS.
 *   baja     Columna de baja logica, o null si la tabla no tiene.
 *   campos   Columnas editables, en el orden en que van en el formulario.
 *
 * Cada campo:
 *   etiqueta  Lo que ve el usuario.
 *   tipo      Ver TIPOS. Por defecto 'texto'.
 *   requerido Si no lo es, se valida como nullable.
 *   unico     Si no puede repetirse en la tabla.
 *   max       Largo maximo, tomado de la migracion.
 *   opciones  Solo para 'select': [modelo, columna a mostrar].
 *   ayuda     Texto auxiliar debajo del campo.
 */
final class Catalogos
{
    /**
     * Los grupos del indice, en orden de presentacion, con su icono.
     * Todos estos iconos ya estan en la lista `icon_names` del layout.
     */
    public const GRUPOS = [
        'Personas y acceso' => 'badge',
        'Cirugías' => 'personal_injury',
        'Quirófanos' => 'meeting_room',
        'Cobertura' => 'shield',
        'Materiales' => 'inventory_2',
        'Hemoderivados' => 'bloodtype',
        'Anestesia' => 'stethoscope',
        'Preparación' => 'no_food',
        'Profilaxis' => 'vaccines',
    ];

    /**
     * Tipos de campo validos y, para los que son un <input>, su type HTML.
     * Los que valen null tienen componente propio (textarea, select, checkbox).
     */
    public const TIPOS = [
        'texto' => 'text',
        'numero' => 'number',
        'email' => 'email',
        'texto-largo' => null,
        'select' => null,
        'booleano' => null,
    ];

    /**
     * @return array<string, array{modelo: class-string<Model>, singular: string,
     *     plural: string, grupo: string, baja: ?string, campos: array<string, array<string, mixed>>}>
     */
    public static function todos(): array
    {
        return [
            // --- Personas y acceso ---
            'tipo-documento' => self::simple(TipoDocumento::class, 'Tipo de documento', 'Tipos de documento', 'Personas y acceso', 120),
            'grupo-sanguineo' => self::simple(GrupoSanguineo::class, 'Grupo sanguíneo', 'Grupos sanguíneos', 'Personas y acceso', 120),

            // El unico catalogo cuya columna de baja no sigue el patron fechaBaja*.
            'rol' => [
                ...self::simple(Rol::class, 'Rol', 'Roles', 'Personas y acceso', 120),
                'baja' => 'fechaHoraBajaRol',
                'protegidos' => ['Administrador'],
                'motivoProteccion' => 'Usuario::tieneRol() lo busca por este nombre exacto. Si se
                                       renombra, nadie entra más a Administración; si se da de baja,
                                       no se le puede dar permiso de administración a nadie nuevo.',
            ],

            // --- Cirugías ---
            'tipo-cirugia' => [
                'modelo' => TipoCirugia::class,
                'singular' => 'Tipo de cirugía',
                'plural' => 'Tipos de cirugía',
                'grupo' => 'Cirugías',
                'baja' => 'fechaBajaTipoCirugia',
                'campos' => [
                    'nombreTipoCirugia' => ['etiqueta' => 'Nombre', 'requerido' => true, 'unico' => true, 'max' => 180],
                    'descripcionTipoCirugia' => ['etiqueta' => 'Descripción', 'tipo' => 'texto-largo'],
                ],
            ],
            'tipo-estudio' => self::simple(TipoEstudio::class, 'Tipo de estudio', 'Tipos de estudio', 'Cirugías', 180),
            'estado-cirugia' => [
                ...self::simple(EstadoCirugia::class, 'Estado de cirugía', 'Estados de cirugía', 'Cirugías', 120),
                'protegidos' => ['Realizada', 'Suspendida', 'En riesgo'],
                'motivoProteccion' => 'Los paneles del cirujano y de Dirección cuentan las cirugías
                                       buscando estos estados por su nombre. Si se renombran, los
                                       indicadores y la tasa de suspensión quedan en cero sin avisar.',
            ],

            // --- Quirófanos ---
            'quirofano' => [
                'modelo' => Quirofano::class,
                'singular' => 'Quirófano',
                'plural' => 'Quirófanos',
                'grupo' => 'Quirófanos',
                'baja' => 'fechaBajaQuirofano',
                'campos' => [
                    'nroQuirofano' => ['etiqueta' => 'Número', 'tipo' => 'numero', 'requerido' => true, 'unico' => true],
                    'nombreQuirofano' => ['etiqueta' => 'Nombre', 'requerido' => true, 'max' => 120],
                ],
            ],
            'estado-quirofano' => self::simple(EstadoQuirofano::class, 'Estado de quirófano', 'Estados de quirófano', 'Quirófanos', 120),

            // --- Cobertura ---
            'obra-social' => [
                'modelo' => ObraSocial::class,
                'singular' => 'Obra social',
                'plural' => 'Obras sociales',
                'grupo' => 'Cobertura',
                'baja' => 'fechaBajaObraSocial',
                'campos' => [
                    'nombreObraSocial' => ['etiqueta' => 'Nombre', 'requerido' => true, 'unico' => true, 'max' => 180],
                    'telefonoObraSocial' => ['etiqueta' => 'Teléfono', 'max' => 30],
                    'emailObraSocial' => ['etiqueta' => 'Email', 'tipo' => 'email', 'max' => 120],
                    'diasVigenciaOrden' => [
                        'etiqueta' => 'Días de vigencia de la orden',
                        'tipo' => 'numero',
                        'ayuda' => 'Cuántos días vale una autorización emitida por esta obra social.',
                    ],
                ],
            ],
            'plan' => [
                'modelo' => Plan::class,
                'singular' => 'Plan',
                'plural' => 'Planes',
                'grupo' => 'Cobertura',
                'baja' => 'fechaBajaPlan',
                'campos' => [
                    // La FK va toda en minuscula en el esquema original.
                    'idobrasocial' => [
                        'etiqueta' => 'Obra social',
                        'tipo' => 'select',
                        'requerido' => true,
                        'opciones' => [ObraSocial::class, 'nombreObraSocial'],
                    ],
                    // El nombre se repite entre obras sociales, asi que no es unico.
                    'nombrePlan' => ['etiqueta' => 'Nombre', 'requerido' => true, 'max' => 180],
                    'es_sin_cobertura' => ['etiqueta' => 'Es plan sin cobertura', 'tipo' => 'booleano'],
                    // La tabla lo trae en true por defecto; el alta arranca igual.
                    'habilitado_autorizaciones' => ['etiqueta' => 'Habilitado para autorizaciones', 'tipo' => 'booleano', 'defecto' => true],
                    'observacion_alerta' => ['etiqueta' => 'Alerta', 'max' => 255, 'ayuda' => 'Se muestra al gestionar autorizaciones de este plan.'],
                ],
            ],
            'estado-autorizacion' => [
                ...self::simple(EstadoAutCirugia::class, 'Estado de autorización', 'Estados de autorización', 'Cobertura', 120),
                'protegidos' => ['Aprobada'],
                'motivoProteccion' => 'ResumenCirugia decide si una cirugía está autorizada buscando
                                       este nombre. Renombrarlo haría que ninguna llegue a estar lista.',
            ],

            // --- Materiales ---
            'material' => [
                'modelo' => Material::class,
                'singular' => 'Material',
                'plural' => 'Materiales',
                'grupo' => 'Materiales',
                'baja' => 'fechaBajaMaterial',
                'campos' => [
                    'nombreMaterial' => ['etiqueta' => 'Nombre', 'requerido' => true, 'unico' => true, 'max' => 180],
                    'codMaterial' => ['etiqueta' => 'Código', 'max' => 60],
                ],
            ],
            'proveedor' => [
                'modelo' => Proveedor::class,
                'singular' => 'Proveedor',
                'plural' => 'Proveedores',
                'grupo' => 'Materiales',
                'baja' => 'fechaBajaProveedor',
                'campos' => [
                    'nombreProveedor' => ['etiqueta' => 'Nombre', 'requerido' => true, 'unico' => true, 'max' => 180],
                    'cuitProveedor' => ['etiqueta' => 'CUIT', 'max' => 13],
                    'telefonoProveedor' => ['etiqueta' => 'Teléfono', 'max' => 30],
                ],
            ],
            'tipo-medida' => self::simple(TipoMedida::class, 'Tipo de medida', 'Tipos de medida', 'Materiales', 120),
            'estado-pedido-material' => [
                ...self::simple(EstadoPedidoMaterial::class, 'Estado de pedido de material', 'Estados de pedido de material', 'Materiales', 120),
                'protegidos' => ['Rechazado', 'Solicitado', 'Presupuestado', 'En auditoría', 'Aprobado', 'Entregado'],
                'motivoProteccion' => 'ResumenCirugia resuelve el estado consolidado de los materiales
                                       recorriendo estos nombres en ese orden, del menos al más avanzado.',
            ],

            // --- Hemoderivados ---
            'tipo-hemoderivado' => self::simple(TipoHemoderivado::class, 'Tipo de hemoderivado', 'Tipos de hemoderivado', 'Hemoderivados', 180),
            'establecimiento' => [
                'modelo' => Establecimiento::class,
                'singular' => 'Establecimiento',
                'plural' => 'Establecimientos',
                'grupo' => 'Hemoderivados',
                // La tabla trae una sola columna de fecha, sin "Baja" en el nombre.
                'baja' => 'fechaEstablecimiento',
                'campos' => [
                    'nombreEstablecimiento' => ['etiqueta' => 'Nombre', 'requerido' => true, 'unico' => true, 'max' => 180],
                    'descripcionEstablecimiento' => ['etiqueta' => 'Descripción', 'max' => 255],
                    'codTelefonoEstablecimiento' => ['etiqueta' => 'Código de área', 'max' => 10],
                    'numeroTelefonoEstablecimiento' => ['etiqueta' => 'Teléfono', 'max' => 20],
                    'emailEstablecimiento' => ['etiqueta' => 'Email', 'tipo' => 'email', 'max' => 120],
                ],
            ],
            'estado-pedido-hemoderivado' => self::simple(EstadoPedidoHemoderivado::class, 'Estado de pedido de hemoderivado', 'Estados de pedido de hemoderivado', 'Hemoderivados', 120),
            'estado-pedido-tipo-hemoderivado' => self::simple(EstadoPedidoTipoHemoderivado::class, 'Estado por hemoderivado pedido', 'Estados por hemoderivado pedido', 'Hemoderivados', 120),

            // --- Anestesia ---
            'tipo-asa' => [
                'modelo' => TipoASA::class,
                'singular' => 'Clasificación ASA',
                'plural' => 'Clasificaciones ASA',
                'grupo' => 'Anestesia',
                'baja' => 'fechaBajaTipoAsa',
                'campos' => [
                    'nombreTipoAsa' => ['etiqueta' => 'Nombre', 'requerido' => true, 'unico' => true, 'max' => 120],
                    'aliasTipoAsa' => ['etiqueta' => 'Alias', 'max' => 60],
                    'descripcionTipoAsa' => ['etiqueta' => 'Descripción', 'max' => 255],
                ],
            ],
            'tipo-anestesia' => self::simple(TipoAnestesia::class, 'Tipo de anestesia', 'Tipos de anestesia', 'Anestesia', 120),
            'estado-evaluacion' => [
                ...self::simple(EstadoEvaluacionAnestesica::class, 'Estado de evaluación', 'Estados de evaluación', 'Anestesia', 120),
                'protegidos' => ['Completada'],
                'motivoProteccion' => 'ResumenCirugia decide si la evaluación anestésica está hecha
                                       buscando este nombre.',
            ],

            // --- Preparación ---
            'tipo-preparacion' => self::simple(TipoPreparacion::class, 'Tipo de preparación', 'Tipos de preparación', 'Preparación', 120),
            'tipo-indicacion' => self::simple(TipoIndicacion::class, 'Tipo de indicación', 'Tipos de indicación', 'Preparación', 120),

            // --- Profilaxis ---
            'profilaxis' => self::simple(Profilaxis::class, 'Profilaxis', 'Profilaxis', 'Profilaxis', 180),
            'profilaxis-rol' => self::simple(ProfilaxisRol::class, 'Rol de profilaxis', 'Roles de profilaxis', 'Profilaxis', 120),
        ];
    }

    /**
     * Configuracion de un catalogo por su slug de URL, con el slug incluido.
     * Un slug que no existe es un 404, no un error de PHP.
     *
     * @return array<string, mixed>
     */
    public static function buscar(string $slug): array
    {
        $config = self::todos()[$slug] ?? abort(404);

        return [...$config, 'slug' => $slug];
    }

    /**
     * Los catalogos agrupados para el indice de /admin, en el orden de GRUPOS.
     *
     * @return Collection<string, array{icono: string, catalogos: Collection<int, array<string, mixed>>}>
     */
    public static function porGrupo(): Collection
    {
        $catalogos = collect(self::todos())
            ->map(fn (array $config, string $slug) => [...$config, 'slug' => $slug])
            ->groupBy('grupo');

        return collect(self::GRUPOS)->map(fn (string $icono, string $grupo) => [
            'icono' => $icono,
            'catalogos' => $catalogos->get($grupo, collect())->values(),
        ]);
    }

    /**
     * La columna que identifica al registro para el usuario: siempre la
     * primera del formulario.
     *
     * @param  array<string, mixed>  $config
     */
    public static function columnaTitulo(array $config): string
    {
        return array_key_first($config['campos']);
    }

    /**
     * Hay filas que no son datos: son parte del sistema. El codigo las busca
     * por su nombre literal —el rol 'Administrador' resuelve el acceso a la
     * seccion, los estados 'Realizada' y 'Suspendida' alimentan los
     * indicadores— asi que renombrarlas rompe la aplicacion en silencio, sin
     * un error que lo delate.
     *
     * Cada catalogo declara cuales son en 'protegidos'.
     *
     * @param  array<string, mixed>  $config
     */
    public static function estaProtegido(array $config, Model $registro): bool
    {
        return in_array(
            $registro->{self::columnaTitulo($config)},
            $config['protegidos'] ?? [],
            true,
        );
    }

    /**
     * Opciones de un campo 'select', resueltas recien cuando hacen falta para
     * que el mapa no dispare consultas al cargarse.
     *
     * @param  array<string, mixed>  $campo
     * @return array<int|string, string>
     */
    public static function opciones(array $campo): array
    {
        [$modelo, $columna] = $campo['opciones'];

        return $modelo::query()
            ->orderBy($columna)
            ->pluck($columna, (new $modelo)->getKeyName())
            ->all();
    }

    /**
     * Catalogo de una sola columna de nombre, que es la forma que tienen casi
     * todas las tablas maestras. La PK del modelo (idTipoEstudio) da el resto
     * de los nombres: nombreTipoEstudio y fechaBajaTipoEstudio.
     *
     * @param  class-string<Model>  $modelo
     * @return array<string, mixed>
     */
    private static function simple(string $modelo, string $singular, string $plural, string $grupo, int $max): array
    {
        $sufijo = substr((new $modelo)->getKeyName(), 2);

        return [
            'modelo' => $modelo,
            'singular' => $singular,
            'plural' => $plural,
            'grupo' => $grupo,
            'baja' => "fechaBaja$sufijo",
            'campos' => [
                "nombre$sufijo" => ['etiqueta' => 'Nombre', 'requerido' => true, 'unico' => true, 'max' => $max],
            ],
        ];
    }
}
