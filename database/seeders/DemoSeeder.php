<?php

namespace Database\Seeders;

use App\Models\AutCirugia;
use App\Models\AutoCirugiaEstado;
use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\CirugiaPersonal;
use App\Models\CirugiaQuirofano;
use App\Models\CirugiaTipoEstudio;
use App\Models\EstadoAutCirugia;
use App\Models\EstadoCirugia;
use App\Models\EstadoEvaluacionAnestesica;
use App\Models\EstadoHisopadoSarm;
use App\Models\EstadoPedidoMaterial;
use App\Models\EvaluacionAnestesica;
use App\Models\EvaluacionAnestesicaEstado;
use App\Models\EvaluacionTipoAnestesia;
use App\Models\EvaluacionTipoAsa;
use App\Models\GrupoSanguineo;
use App\Models\HisopadoSarm;
use App\Models\HisopadoSarmEstado;
use App\Models\Material;
use App\Models\MaterialProveedor;
use App\Models\MaterialProveedorTipoMedida;
use App\Models\ObraSocial;
use App\Models\PedidoHemoderivado;
use App\Models\PedidoMaterial;
use App\Models\PedidoMaterialEstado;
use App\Models\PedidoTipoHemoderivado;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use App\Models\Profilaxis;
use App\Models\ProfilaxisAtbHisopadoSarm;
use App\Models\ProfilaxisAtbHisopadoSarmProfilaxis;
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
use App\Models\TipoMedida;
use App\Models\Usuario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de demostracion: una semana de quirofano con casos en distintos
 * estados, para que las pantallas tengan algo real que mostrar.
 *
 * Las fechas son relativas a hoy, asi que el tablero siempre tiene actividad
 * sin importar cuando se corra el seeder.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $hoy = Carbon::today();

        $quirofanos = $this->quirofanos();
        $planes = $this->obrasSociales();
        $equipo = $this->equipo();
        $materiales = $this->materiales();

        $casos = [
            [
                'paciente' => ['García', 'María', '28456789', 'F', '1976-03-14', 'A+'],
                'tipo' => 'Colecistectomía laparoscópica',
                'cirujano' => 'perez',
                'anestesista' => 'ramos',
                'quirofano' => 1,
                'cuando' => $hoy->copy()->setTime(7, 30),
                'plan' => 'OSDE 410',
                'estado' => 'Confirmada',
                'implante' => false,
                'autorizacion' => 'Aprobada',
                'estudios' => [['Hemograma', -6], ['Electrocardiograma', -5], ['Coagulograma', -6]],
                'evaluacion' => ['ASA I', 'General', 'Completada'],
                'profilaxis' => [['Cefazolina 2g IV', 'Principal']],
            ],
            [
                'paciente' => ['López', 'Ramiro', '31220145', 'M', '1984-11-02', '0+'],
                'tipo' => 'Hernioplastia inguinal',
                'cirujano' => 'perez',
                'anestesista' => 'ramos',
                'quirofano' => 1,
                'cuando' => $hoy->copy()->setTime(9, 15),
                'plan' => 'OSDE 410',
                'estado' => 'En riesgo',
                'implante' => false,
                'autorizacion' => 'Enviada',
                'estudios' => [['Hemograma', -3], ['Electrocardiograma', null]],
                'evaluacion' => ['ASA II', 'Regional / peridural', 'Cuestionario recibido'],
                'profilaxis' => [['Cefazolina 1g IV', 'Principal']],
            ],
            [
                'paciente' => ['Fernández', 'Carla', '27889450', 'F', '1974-07-21', 'B+'],
                'tipo' => 'Tiroidectomía',
                'cirujano' => 'lopez',
                'anestesista' => 'ramos',
                'quirofano' => 2,
                'cuando' => $hoy->copy()->setTime(10, 30),
                'plan' => 'SMG 20',
                'estado' => 'Confirmada',
                'implante' => false,
                'autorizacion' => 'Aprobada',
                'estudios' => [['Hemograma', -8], ['Riesgo quirúrgico cardiológico', -7]],
                'evaluacion' => ['ASA II', 'General', 'Completada'],
                'profilaxis' => [['Cefazolina 2g IV', 'Principal']],
            ],
            [
                'paciente' => ['Ramírez', 'Luis', '14320876', 'M', '1957-01-30', 'A+'],
                'tipo' => 'Prótesis total de rodilla',
                'cirujano' => 'perez',
                'anestesista' => 'ramos',
                'quirofano' => 2,
                'cuando' => $hoy->copy()->addDays(2)->setTime(8, 0),
                'plan' => 'OSDE 410',
                'estado' => 'En riesgo',
                'implante' => true,
                'autorizacion' => 'En auditoría médica',
                'estudios' => [['Hemograma', -5], ['Radiografía de tórax', -5]],
                'evaluacion' => ['ASA III', 'Regional / peridural', 'Completada'],
                'profilaxis' => [['Vancomicina 1g IV', 'Alternativa por alergia'], ['Cefazolina 2g IV', 'Complementaria']],
                'materiales' => [
                    ['Sistema tibial de rodilla', 1],
                    ['Componente femoral de rodilla', 1],
                    ['Inserto de polietileno 10mm', 1],
                ],
                'hemoderivados' => [['Glóbulos rojos desplasmatizados', 2]],
                'observaciones' => 'HTA + diabetes tipo 2. Alergia a penicilina documentada.',
            ],
            [
                'paciente' => ['Vidal', 'Jorge', '20114523', 'M', '1968-09-09', '0-'],
                'tipo' => 'Hernioplastia inguinal',
                'cirujano' => 'lopez',
                'anestesista' => 'ramos',
                'quirofano' => 3,
                'cuando' => $hoy->copy()->addDays(4)->setTime(11, 0),
                'plan' => 'SMG 20',
                'estado' => 'Programada',
                'implante' => false,
                'autorizacion' => 'Pendiente de envío',
                'estudios' => [['Hemograma', null]],
                'evaluacion' => ['ASA II', 'Sedación + local', 'Pendiente'],
                'profilaxis' => [],
            ],
            [
                'paciente' => ['Rodríguez', 'Ana', '35678211', 'F', '1990-05-17', 'AB+'],
                'tipo' => 'Apendicectomía',
                'cirujano' => 'lopez',
                'anestesista' => 'ramos',
                'quirofano' => 3,
                'cuando' => $hoy->copy()->setTime(14, 0),
                'plan' => 'Particular',
                'estado' => 'En riesgo',
                'implante' => false,
                'autorizacion' => 'Pendiente de envío',
                'estudios' => [['Hemograma', -1]],
                'evaluacion' => ['ASA I', 'General', 'Pendiente'],
                'profilaxis' => [['Cefazolina 1g IV', 'Principal']],
            ],
        ];

        foreach ($casos as $caso) {
            $this->crearCaso($caso, $quirofanos, $planes, $equipo, $materiales);
        }
    }

    /** @return array<int, Quirofano> */
    private function quirofanos(): array
    {
        $quirofanos = [];

        foreach ([1 => 'Quirófano central', 2 => 'Quirófano traumatología', 3 => 'Quirófano ambulatorio', 4 => 'Quirófano de urgencias'] as $nro => $nombre) {
            $quirofanos[$nro] = Quirofano::firstOrCreate(
                ['nroQuirofano' => $nro],
                ['nombreQuirofano' => $nombre],
            );
        }

        return $quirofanos;
    }

    /** @return array<string, Plan> */
    private function obrasSociales(): array
    {
        $osde = ObraSocial::firstOrCreate(
            ['nombreObraSocial' => 'OSDE'],
            ['telefonoObraSocial' => '08103333763', 'emailObraSocial' => 'auditoria@osde.com.ar', 'diasVigenciaOrden' => 30],
        );

        $swiss = ObraSocial::firstOrCreate(
            ['nombreObraSocial' => 'Swiss Medical'],
            ['telefonoObraSocial' => '08003333828', 'emailObraSocial' => 'autorizaciones@swissmedical.com.ar', 'diasVigenciaOrden' => 30],
        );

        // 'Sin obra social' / Plan 'Particular' ya los crea CatalogosSeeder.
        $sinCobertura = ObraSocial::where('nombreObraSocial', 'Sin obra social')->firstOrFail();

        return [
            'OSDE 410' => Plan::firstOrCreate(
                ['nombrePlan' => 'OSDE 410', 'idobrasocial' => $osde->idObraSocial],
                ['habilitado_autorizaciones' => true],
            ),
            'SMG 20' => Plan::firstOrCreate(
                ['nombrePlan' => 'SMG 20', 'idobrasocial' => $swiss->idObraSocial],
                ['habilitado_autorizaciones' => true],
            ),
            'Particular' => Plan::firstOrCreate(
                ['nombrePlan' => 'Particular', 'idobrasocial' => $sinCobertura->idObraSocial],
                ['es_sin_cobertura' => true, 'habilitado_autorizaciones' => false],
            ),
        ];
    }

    /** @return array<string, Personal> */
    private function equipo(): array
    {
        $this->crearPacienteDemo();

        $definiciones = [
            'perez' => ['Pérez', 'Daniel', '17223456', 'Cirujano', 'MP 4821', 'd.perez@hospital.uncuyo.edu.ar'],
            'lopez' => ['López', 'Silvia', '19887432', 'Cirujano', 'MP 5109', 's.lopez@hospital.uncuyo.edu.ar'],
            'ramos' => ['Ramos', 'Hernán', '18554120', 'Anestesista', 'MP 4677', 'h.ramos@hospital.uncuyo.edu.ar'],
            'gonzalez' => ['González', 'Romina', '30112987', 'Gestor de quirófano', null, 'r.gonzalez@hospital.uncuyo.edu.ar'],
            'panel' => ['Gestión', 'Panel', '00000001', 'Gestor de quirófano', null, 'L390585@gmail.com'],
        ];

        $equipo = [];

        foreach ($definiciones as $clave => [$apellidos, $nombres, $documento, $rol, $matricula, $mail]) {
            $persona = $this->persona($apellidos, $nombres, $documento);

            $personal = Personal::firstOrCreate(
                ['idPersona' => $persona->idPersona],
                ['matriculaProvincial' => $matricula, 'mailInstitucional' => $mail],
            );

            $personal->roles()->syncWithoutDetaching([
                Rol::where('nombreRol', $rol)->value('idRol') => [
                    'fechaHoraAsignacionRolPersonal' => now(),
                ],
            ]);

            // 'panel' es una cuenta real (no demo): el login es el email, en
            // minusculas para que coincida sin importar como lo tipeen.
            $nombreUsuario = $clave === 'panel' ? mb_strtolower($mail) : $clave;
            $password = $clave === 'panel' ? $mail : 'demo1234';

            Usuario::firstOrCreate(
                ['nombreUsuario' => $nombreUsuario],
                ['idPersonal' => $personal->idPersonal, 'passwordUsuario' => $password],
            );

            $equipo[$clave] = $personal;
        }

        return $equipo;
    }

    /**
     * Usuario de demo para el portal del paciente: María García, que ademas es
     * la paciente de una de las cirugias sembradas.
     *
     * Le crea una fila en Personal sin legajo, porque Usuario cuelga de ahi y
     * un paciente es una Persona sin legajo. Es el atajo que hace navegable el
     * portal, no la solucion: como se autentica un paciente de verdad sigue
     * siendo una decision de esquema pendiente.
     */
    private function crearPacienteDemo(): void
    {
        $persona = $this->persona('García', 'María', '28456789');
        $personal = Personal::firstOrCreate(['idPersona' => $persona->idPersona]);

        $personal->roles()->syncWithoutDetaching([
            Rol::where('nombreRol', 'Paciente')->value('idRol') => [
                'fechaHoraAsignacionRolPersonal' => now(),
            ],
        ]);

        Usuario::firstOrCreate(
            ['nombreUsuario' => 'mgarcia'],
            ['idPersonal' => $personal->idPersonal, 'passwordUsuario' => 'paciente1234'],
        );
    }

    /** @return array<string, MaterialProveedor> */
    private function materiales(): array
    {
        $proveedor = Proveedor::firstOrCreate(
            ['nombreProveedor' => 'Implantes Cuyo S.A.'],
            ['cuitProveedor' => '30712345678', 'telefonoProveedor' => '2614567890'],
        );

        // Cada material se vende en una o mas unidades, y cada una tiene su
        // precio y su codigo en el catalogo del proveedor: la malla suelta y
        // la caja de nueve no son el mismo articulo facturable.
        $catalogo = [
            'Sistema tibial de rodilla' => ['7.02.01', ['Unidad' => 3200.00, 'Set' => 8900.00]],
            'Componente femoral de rodilla' => ['7.02.02', ['Unidad' => 2800.00]],
            'Inserto de polietileno 10mm' => ['7.02.03', ['Unidad' => 1100.00]],
            'Malla de polipropileno' => ['5.01.04', ['Unidad' => 180.00, 'Caja' => 1620.00]],
        ];

        $unidades = TipoMedida::pluck('idTipoMedida', 'nombreTipoMedida');
        $materiales = [];

        foreach ($catalogo as $nombre => [$codigo, $precios]) {
            $material = Material::firstOrCreate(
                ['nombreMaterial' => $nombre],
                ['codMaterial' => $codigo],
            );

            $vinculo = MaterialProveedor::firstOrCreate([
                'idMaterial' => $material->idMaterial,
                'idProveedor' => $proveedor->idProveedor,
            ]);

            foreach ($precios as $unidad => $precio) {
                MaterialProveedorTipoMedida::firstOrCreate(
                    [
                        'idMaterialProveedor' => $vinculo->idMaterialProveedor,
                        'idTipoMedida' => $unidades[$unidad],
                    ],
                    [
                        'fechaAsignacionMaterialTipoMedida' => now()->subMonths(3),
                        'disponibleMaterialTipoMedida' => true,
                        'codExternoMaterialProveedorTipoMedida' => $codigo.'-'.mb_substr($unidad, 0, 1),
                        'precioExternoMaterialProveedorTipoMedida' => $precio,
                        'fechaActualizacionPrecioMaterialProveedorTipoMedida' => now()->subMonths(3),
                    ],
                );
            }

            $materiales[$nombre] = $vinculo;
        }

        return $materiales;
    }

    private function persona(string $apellidos, string $nombres, string $documento, ?string $genero = null, ?string $nacimiento = null, ?string $grupo = null, ?string $observaciones = null): Persona
    {
        $dni = TipoDocumento::where('nombreTipoDocumento', 'DNI')->firstOrFail();

        return Persona::firstOrCreate(
            ['tipo_documento_id' => $dni->idTipoDocumento, 'documento' => $documento],
            [
                'apellidos' => $apellidos,
                'nombres' => $nombres,
                'genero' => $genero,
                'fecha_nacimiento' => $nacimiento,
                'grupo_sanguineo_id' => $grupo ? GrupoSanguineo::where('nombreGrupoSanguineo', $grupo)->value('idGrupoSanguineo') : null,
                'observaciones' => $observaciones,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $caso
     * @param  array<int, Quirofano>  $quirofanos
     * @param  array<string, Plan>  $planes
     * @param  array<string, Personal>  $equipo
     * @param  array<string, MaterialProveedor>  $materiales
     */
    private function crearCaso(array $caso, array $quirofanos, array $planes, array $equipo, array $materiales): void
    {
        [$apellidos, $nombres, $documento, $genero, $nacimiento, $grupo] = $caso['paciente'];

        $paciente = $this->persona(
            $apellidos, $nombres, $documento, $genero, $nacimiento, $grupo,
            $caso['observaciones'] ?? null,
        );

        $plan = $planes[$caso['plan']];

        PlanObraSocial::firstOrCreate(
            ['idPersona' => $paciente->idPersona, 'idPlan' => $plan->idPlan],
            ['nroBeneficiaroPlanObraSocial' => '62'.$documento, 'fechaInicioPlanObraSocial' => now()->subYears(3)],
        );

        $cirugia = Cirugia::firstOrCreate(
            [
                'idPersonaPaciente' => $paciente->idPersona,
                'fechaHoraCirugia' => $caso['cuando'],
            ],
            [
                'idTipoCirugia' => TipoCirugia::where('nombreTipoCirugia', $caso['tipo'])->value('idTipoCirugia'),
                'idPersonalCirujano' => $equipo[$caso['cirujano']]->idPersonal,
                'idPersonalAnestesista' => $equipo[$caso['anestesista']]->idPersonal,
                'descripcionCirugia' => $caso['tipo'],
                'observacionesPaciente' => $caso['observaciones'] ?? null,
                'requiereImplante' => $caso['implante'],
            ],
        );

        // Estado vigente de la cirugia (sin fecha de desasignacion).
        CirugiaEstado::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia, 'fechaDesasignacionCirugiaEstado' => null],
            [
                'idEstadoCirugia' => EstadoCirugia::where('nombreEstadoCirugia', $caso['estado'])->value('idEstadoCirugia'),
                'fechaAsignacionCirugiaEstado' => now()->subDays(3),
            ],
        );

        CirugiaQuirofano::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia, 'fechaHoraDesasignacion' => null],
            [
                'idQuirofano' => $quirofanos[$caso['quirofano']]->idQuirofano,
                'fechaHoraAsignacion' => $caso['cuando'],
            ],
        );

        // Equipo asignado, ademas del cirujano y anestesista titulares.
        foreach (['cirujano' => 'Cirujano', 'anestesista' => 'Anestesista'] as $clave => $rol) {
            CirugiaPersonal::firstOrCreate(
                [
                    'idCirugia' => $cirugia->idCirugia,
                    'idPersonal' => $equipo[$caso[$clave]]->idPersonal,
                    'idRol' => Rol::where('nombreRol', $rol)->value('idRol'),
                ],
                ['fechaInicioAsignacionCirugiaPersonal' => now()->subDays(3)],
            );
        }

        $this->autorizacion($cirugia, $plan, $caso['autorizacion']);
        $this->estudios($cirugia, $caso['estudios']);
        $this->evaluacion($cirugia, ...$caso['evaluacion']);
        $this->profilaxis($cirugia, $caso['profilaxis'], $caso['observaciones'] ?? null);

        if (! empty($caso['materiales'])) {
            $this->materialesDelCaso($cirugia, $plan, $caso['materiales'], $materiales);
        }

        if (! empty($caso['hemoderivados'])) {
            $this->hemoderivados($cirugia, $caso['hemoderivados']);
        }
    }

    private function autorizacion(Cirugia $cirugia, Plan $plan, string $estado): void
    {
        if ($plan->es_sin_cobertura) {
            return;
        }

        $aprobada = $estado === 'Aprobada';

        $autorizacion = AutCirugia::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia, 'idPlan' => $plan->idPlan],
            [
                'fechaLimiteEnvioAutorizacion' => $cirugia->fechaHoraCirugia?->copy()->subDays(3),
                'fechaHoraEnvioAutorizacionAutCirugia' => $estado === 'Pendiente de envío' ? null : now()->subDays(6),
                'fechaHoraVerificacionAutCirugia' => $aprobada ? now()->subDays(5) : null,
                'nroAprobacionAutCirugia' => $aprobada ? '2024-'.random_int(10000, 99999) : null,
            ],
        );

        AutoCirugiaEstado::firstOrCreate(
            ['idAutCirugia' => $autorizacion->idAutCirugia, 'fechaFinAutoCirugiaEstado' => null],
            [
                'idEstadoAutCirugia' => EstadoAutCirugia::where('nombreEstadoAutCirugia', $estado)->value('idEstadoAutCirugia'),
                'fechaInicioAutoCirugiaEstado' => now()->subDays(5),
            ],
        );
    }

    /** @param  list<array{0:string,1:int|null}>  $estudios */
    private function estudios(Cirugia $cirugia, array $estudios): void
    {
        $puntero = $this->archivoDemostracion();

        foreach ($estudios as [$nombre, $diasAtras]) {
            CirugiaTipoEstudio::firstOrCreate(
                [
                    'idCirugia' => $cirugia->idCirugia,
                    'idTipoEstudio' => TipoEstudio::where('nombreTipoEstudio', $nombre)->value('idTipoEstudio'),
                ],
                [
                    'fechaAsignacionCirugiaTipoEstudio' => now()->subDays(10),
                    // Sin fecha de subida = todavia pendiente del paciente.
                    'fechaSubidaCirugiaTipoEstudio' => $diasAtras ? now()->addDays($diasAtras) : null,
                    'fechaEsperadaResultadoCirugiaTipoEstudio' => $cirugia->fechaHoraCirugia?->copy()->subDays(2),
                    'resultadoCirugiaTipoEstudio' => $diasAtras ? 'Sin particularidades' : null,
                    'urlArchivoCirugiaTipoEstudio' => $diasAtras ? $puntero : null,
                ],
            );
        }
    }

    /**
     * Deja un archivo real en el disco local y devuelve su puntero.
     *
     * Antes acá había un `'estudios/demo.pdf'` que no apuntaba a nada: alcanzaba
     * cuando el botón «Ver» era un alert(), pero ahora abre el documento. Un PNG
     * de 1x1 (el archivo válido más corto que existe) es suficiente para que el
     * circuito completo se pueda demostrar sin meter un binario en el repo.
     *
     * Lleva el prefijo `local:` a propósito: si la instalación tiene Cloudinary
     * configurado, este puntero no le pertenece y la pantalla avisa que el
     * archivo no está, en vez de resolverlo contra la cuenta equivocada.
     */
    private function archivoDemostracion(): string
    {
        $ruta = 'estudios/demo.png';

        if (! Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->put($ruta, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
            ));
        }

        return 'local:'.$ruta;
    }

    private function evaluacion(Cirugia $cirugia, string $asa, string $anestesia, string $estado): void
    {
        $evaluacion = EvaluacionAnestesica::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            ['observacionesEquipoEvaluacion' => 'Evaluación cargada por el seeder de demostración.'],
        );

        EvaluacionAnestesicaEstado::firstOrCreate(
            ['idEvaluacionAnestesica' => $evaluacion->idEvaluacionAnestesica, 'fechaFinEvaluacionAnestesicaEstado' => null],
            [
                'idEstadoEvaluacionAnestesica' => EstadoEvaluacionAnestesica::where('nombreEstadoEvaluacionAnestesica', $estado)->value('idEstadoEvaluacionAnestesica'),
                'fechaInicioEvaluacionAnestesicaEstado' => now()->subDays(4),
            ],
        );

        if ($estado !== 'Completada') {
            return;
        }

        EvaluacionTipoAsa::firstOrCreate(
            ['idEvaluacionAnestesica' => $evaluacion->idEvaluacionAnestesica, 'fechaFinTipoAsa' => null],
            [
                'idTipoAsa' => TipoASA::where('nombreTipoAsa', $asa)->value('idTipoAsa'),
                'fechaInicioTipoAsa' => now()->subDays(4),
            ],
        );

        EvaluacionTipoAnestesia::firstOrCreate(
            ['idEvaluacionAnestesica' => $evaluacion->idEvaluacionAnestesica, 'fechaFinTipoAnestesia' => null],
            [
                'idTipoAnestesia' => TipoAnestesia::where('nombreTipoAnestesia', $anestesia)->value('idTipoAnestesia'),
                'fechaInicioTipoAnestesia' => now()->subDays(4),
            ],
        );
    }

    /**
     * Todas las cirugías piden Hisopado SAMR. Los casos que ya traían una
     * profilaxis antibiótica cargada quedan con resultado "Positivo" (es lo
     * que dispara esa profilaxis); el resto queda "Pendiente", sin resultado
     * todavía.
     *
     * @param  list<array{0:string,1:string}>  $profilaxis
     */
    private function profilaxis(Cirugia $cirugia, array $profilaxis, ?string $alerta): void
    {
        $hisopado = HisopadoSarm::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            ['fechaSolicitacionHisopadoSarm' => now()->subDays(6)],
        );

        $estadoNombre = $profilaxis === [] ? 'Pendiente' : 'Positivo';

        HisopadoSarmEstado::firstOrCreate(
            ['idHisopadoSarm' => $hisopado->idHisopadoSarm, 'fechaFinAsignacionHisopadoSarmEstado' => null],
            [
                'idEstadoHisopadoSarm' => EstadoHisopadoSarm::where('nombreEstadoHisopadoSarm', $estadoNombre)->value('idEstadoHisopadoSarm'),
                'fechaInicioAsignacionHisopadoSarmEstado' => now()->subDays($profilaxis === [] ? 2 : 4),
            ],
        );

        if ($profilaxis === []) {
            return;
        }

        $cabecera = ProfilaxisAtbHisopadoSarm::firstOrCreate(
            ['idHisopadoSarm' => $hisopado->idHisopadoSarm],
            [
                'alertaProfilaxisAtbHisopadoSarm' => $alerta && str_contains($alerta, 'penicilina')
                    ? 'NO administrar ampicilina ni amoxicilina — alergia a penicilina documentada'
                    : null,
                'motivoProfilaxisAtbHisopadoSarm' => 'Profilaxis prequirúrgica estándar',
            ],
        );

        foreach ($profilaxis as [$droga, $rol]) {
            ProfilaxisAtbHisopadoSarmProfilaxis::firstOrCreate(
                [
                    'idProfilaxisAtbHisopadoSarm' => $cabecera->idProfilaxisAtbHisopadoSarm,
                    'idProfilaxis' => Profilaxis::where('nombreProfilaxis', $droga)->value('idProfilaxis'),
                ],
                [
                    'idProfilaxisRol' => ProfilaxisRol::where('nombreProfilaxisRol', $rol)->value('idProfilaxisRol'),
                    'indicacionesProfilaxisAtbHisopadoSarmProfilaxis' => 'Administrar 30-60 min antes de la incisión.',
                ],
            );
        }
    }

    /**
     * @param  list<array{0:string,1:int}>  $items
     * @param  array<string, MaterialProveedor>  $materiales
     */
    private function materialesDelCaso(Cirugia $cirugia, Plan $plan, array $items, array $materiales): void
    {
        $unidad = TipoMedida::where('nombreTipoMedida', 'Unidad')->value('idTipoMedida');
        $enAuditoria = EstadoPedidoMaterial::where('nombreEstadoPedidoMaterial', 'En auditoría')->value('idEstadoPedidoMaterial');

        foreach ($items as [$nombre, $cantidad]) {
            $materialProveedor = $materiales[$nombre];

            // El precio sale de la unidad pedida, no del proveedor, y queda
            // copiado en el pedido: es lo que se cotizo ese dia.
            $precio = MaterialProveedorTipoMedida::query()
                ->where('idMaterialProveedor', $materialProveedor->idMaterialProveedor)
                ->where('idTipoMedida', $unidad)
                ->value('precioExternoMaterialProveedorTipoMedida');

            $pedido = PedidoMaterial::firstOrCreate(
                [
                    'idCirugia' => $cirugia->idCirugia,
                    'idMaterial' => $materialProveedor->idMaterial,
                ],
                [
                    'idPlan' => $plan->idPlan,
                    'idProveedor' => $materialProveedor->idProveedor,
                    'idTipoMedida' => $unidad,
                    'cantidadPedidoMaterial' => $cantidad,
                    'precioUnitarioPedidoMaterial' => $precio,
                    'subtotalPedidoMaterial' => $precio * $cantidad,
                    'fechaPedidoMaterial' => now()->subDays(5),
                ],
            );

            PedidoMaterialEstado::firstOrCreate(
                ['idPedidoMaterial' => $pedido->idPedidoMaterial],
                [
                    'idEstadoPedidoMaterial' => $enAuditoria,
                    'observacionesPedidoMaterialEstado' => 'Presupuesto enviado al financiador.',
                ],
            );
        }
    }

    /** @param  list<array{0:string,1:int}>  $items */
    private function hemoderivados(Cirugia $cirugia, array $items): void
    {
        $pedido = PedidoHemoderivado::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            [
                'observacionesPedidoHemoderivados' => 'Reserva preventiva indicada por el anestesista.',
                'fechaPedidoHemoderivado' => now()->subDays(4),
            ],
        );

        foreach ($items as [$nombre, $cantidad]) {
            PedidoTipoHemoderivado::firstOrCreate(
                [
                    'idPedidoHemoderivado' => $pedido->idPedidoHemoderivado,
                    'idTipoHemoderivado' => TipoHemoderivado::where('nombreTipoHemoderivado', $nombre)->value('idTipoHemoderivado'),
                ],
                [
                    'cantidadPedidoTipoHemoderivado' => $cantidad,
                    'descripcionPedidoTipoHemoderivado' => 'Umbral transfusional: Hb < 8 g/dL o inestabilidad hemodinámica.',
                ],
            );
        }
    }
}
