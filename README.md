# StartMed

Sistema de gestión de cirugías: agenda de quirófanos, autorizaciones ante obras
sociales, pedidos de materiales y hemoderivados, evaluación pre-anestésica,
preparación del paciente y consentimientos informados.

## Stack

| | |
|---|---|
| PHP | 8.3 |
| Laravel | 13.8 |
| Base de datos | MySQL 8.4 (`startmed`) |
| Frontend | Vite 8 + Tailwind CSS 4 |
| Tests | PHPUnit 12 |

## Puesta en marcha

Requiere PHP 8.3+, Composer, Node 20+ y un MySQL corriendo (en Laragon ya viene).

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Creá la base y ajustá el `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=startmed
DB_USERNAME=root
DB_PASSWORD=
```

```sql
CREATE DATABASE startmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate --seed
npm run build
```

## Levantar el proyecto

```bash
composer run dev
```

Arranca cuatro procesos en paralelo — servidor (`http://127.0.0.1:8000`), worker
de colas, logs en vivo (`pail`) y Vite. Se corta todo con `Ctrl+C`.

A mano, en dos terminales:

```bash
php artisan serve
npm run dev
```

En Laragon el virtual host queda en `http://startmed.test` automáticamente.

**Usuario del seeder:** `admin` / `admin1234`

## Autenticación

El login **no usa la tabla `users` de Laravel**, usa la tabla `Usuario` del
dominio. La tabla por defecto fue eliminada para que no queden dos fuentes de
verdad; de la migración original solo sobrevive `sessions`, que el driver de
sesión en base de datos necesita.

[`App\Models\Usuario`](app/Models/Usuario.php) extiende `Authenticatable` y
traduce el esquema propio a lo que espera el guard:

- `getAuthPassword()` devuelve `passwordUsuario`
- el cast `'passwordUsuario' => 'hashed'` hashea al asignar
- `getRememberTokenName()` devuelve `null` (no hay columna `remember_token`,
  así que "recordarme" está deshabilitado)

El login se hace por `nombreUsuario`, tiene `throttle:6,1` y rechaza a los
usuarios con `fechaBajaUsuario` seteada.

> **Pendiente:** el reseteo de contraseña por email no está configurado
> (`config/auth.php` tiene `'passwords' => []`). `Usuario` no tiene columna de
> email — el correo vive en `Personal.mailInstitucional` y en
> `Persona.contacto_email_direccion` — así que hay que decidir cuál es el canal
> oficial antes de habilitar un broker.

## Base de datos

**65 tablas** de dominio y **75 foreign keys**, agrupadas en 13 migraciones por
módulo. Cada migración crea sus tablas en orden de dependencia y las borra en
orden inverso, así que `migrate:rollback` funciona limpio.

| Migración | Módulo |
|---|---|
| `..._100100_create_persona_tables` | TipoDocumento, GrupoSanguineo, Persona |
| `..._100200_create_personal_rol_usuario_tables` | Rol, Personal, Usuario, RolPersonal |
| `..._100300_create_obra_social_tables` | ObraSocial, Plan, PlanObraSocial |
| `..._100400_create_quirofano_tables` | Quirofano y sus estados |
| `..._100500_create_cirugia_table` | TipoCirugia, Cirugia |
| `..._100600_create_cirugia_relacionadas_tables` | estados, quirófano asignado, equipo, autorizaciones, estudios |
| `..._100700_create_material_tables` | Material, Proveedor, TipoMedida, PedidoMaterial |
| `..._100800_create_hemoderivado_tables` | TipoHemoderivado, Establecimiento, PedidoHemoderivado |
| `..._100900_create_evaluacion_anestesica_tables` | TipoASA, TipoAnestesia, EvaluacionAnestesica |
| `..._101000_create_preparacion_paciente_tables` | TipoPreparacion, TipoIndicacion, PreparacionPaciente |
| `..._101100_create_examen_preanestesico_tables` | config de preguntas/respuestas y examen del paciente |
| `..._101200_create_profilaxis_tables` | Profilaxis, ProfilaxisRol, ProfilaxisAtbCirugia |
| `..._101300_create_consentimiento_tables` | ConfigConsentimiento, ConsentimientoPaciente |

El seeder carga catálogos base: tipos de documento, los 8 grupos sanguíneos,
roles (Administrador, Cirujano, Anestesista, Instrumentador, Enfermero) y un
usuario administrador.

### Convenciones del esquema

- **Nombres del modelo de datos, no de Laravel.** Tablas en `PascalCase`
  (`Cirugia`, `PedidoMaterial`), PKs `idXxx`, columnas en `camelCase`. `Persona`
  es la excepción: usa `snake_case`, tal como venía en el diseño original.
- **Sin `created_at` / `updated_at`.** Ninguna tabla los tiene, por eso todos los
  modelos declaran `public $timestamps = false`.
- **Bajas lógicas.** Las columnas `fechaBaja*` y `fechaHoraBaja*` marcan la baja;
  no se usa `SoftDeletes` de Laravel.
- **Historial por rangos de fecha.** Las tablas de estado (`CirugiaEstado`,
  `QuirofanoEstado`, etc.) guardan la línea de tiempo con
  `fechaInicio*` / `fechaFin*`, en vez de pisar un campo de estado.
- **Constraints con nombre explícito.** Todas las FK se llaman `fk_<tabla>_<ref>`
  porque los nombres autogenerados por Laravel superaban el límite de 64
  caracteres de MySQL en varias tablas.

### Nombres acortados

Tres tablas del examen pre-anestésico no entraban en el límite de 64 caracteres
de MySQL y se acortaron respecto del diseño original:

| Diseño original | En la base |
|---|---|
| `ExamenCirugiaPreAnestesicaConfigTipoExamenPreAnestesico` | `ExamenPreAnestesicoConfig` |
| `…ConfigTipoExamenPreAnestesicoPregunta` | `ExamenPreAnestesicoConfigPregunta` |
| `…ConfigTipoExamenPreAnestesicoPreguntaRespuesta` | `ExamenPreAnestesicoConfigPreguntaRespuesta` |

### Nombres en minúscula (Windows)

MySQL en Windows corre con `lower_case_table_names=1`, así que **guarda los
nombres de tabla en minúscula**: `Cirugia` queda como `cirugia`. Las columnas sí
conservan el `camelCase`. Por eso `php artisan db:table cirugia` funciona y
`db:table Cirugia` no.

No rompe nada en Windows porque MySQL compara sin distinguir mayúsculas, pero en
Linux sí distingue. Como migraciones y modelos usan siempre el mismo string, en
la práctica queda consistente — pero tenelo presente antes de un deploy.

### Inspeccionar la base

```bash
php artisan db:show           # todas las tablas y su tamaño
php artisan db:table cirugia  # columnas, tipos e índices
php artisan db                # cliente SQL interactivo
```

En Laragon, el botón **Database** abre HeidiSQL ya conectado.

## Modelos

**65 modelos** en [`app/Models/`](app/Models/), uno por tabla de dominio, con
**153 relaciones** (75 `belongsTo`, 75 `hasMany`/`hasOne`, 3 `belongsToMany`).

Los nombres de relación derivan de la columna, no de la tabla, para que varias
FK a la misma tabla no colisionen:

```php
// app/Models/Cirugia.php
public function paciente(): BelongsTo     // idPersonaPaciente  → Persona
public function cirujano(): BelongsTo     // idPersonalCirujano → Personal
public function anestesista(): BelongsTo  // idPersonalAnestesista → Personal
```

```php
$cirugia = Cirugia::with([
    'paciente.tipoDocumento',
    'cirujano.persona',
    'cirugiaEstados.estadoCirugia',
    'autCirugias.plan.obraSocial',
])->findOrFail($id);
```

`Personal::rolesVigentes()` es un `belongsToMany` que filtra por
`fechaHoraBajaAsignacionRolPersonal` nula.

## Tests

```bash
php artisan test
php artisan test --filter=ModelosTest
```

[`tests/Feature/ModelosTest.php`](tests/Feature/ModelosTest.php) recorre por
reflexión los 65 modelos y sus 153 relaciones y verifica que la tabla y la PK de
cada modelo existan, que cada relación compile a SQL y que todas las columnas que
usa (FK, owner key, local key y las del pivot) existan en la base. Si alguien
agrega una tabla sin su modelo, o renombra una columna, el test falla.

[`tests/Feature/Auth/LoginTest.php`](tests/Feature/Auth/LoginTest.php) cubre el
login correcto, contraseña incorrecta, hasheo, usuario dado de baja, ruta
protegida y logout.

Los tests corren sobre SQLite en memoria (ver `phpunit.xml`), no tocan la base de
desarrollo.

## Comandos útiles

```bash
php artisan migrate:fresh --seed   # recrear la base desde cero
php artisan migrate:rollback       # deshacer el último batch
php artisan route:list
./vendor/bin/pint                  # formateo de código
```
