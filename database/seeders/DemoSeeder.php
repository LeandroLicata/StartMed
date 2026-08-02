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
use App\Models\EstadoPedidoMaterial;
use App\Models\EvaluacionAnestesica;
use App\Models\EvaluacionAnestesicaEstado;
use App\Models\EvaluacionTipoAnestesia;
use App\Models\EvaluacionTipoAsa;
use App\Models\GrupoSanguineo;
use App\Models\Material;
use App\Models\MaterialProveedor;
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
use App\Models\ProfilaxisAtbCirugia;
use App\Models\ProfilaxisAtbCirugiaProfilaxis;
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
                'estudios' => [['Hemograma', -5], ['Hisopado SAMR', null], ['Radiografía de tórax', -5]],
                'evaluacion' => ['ASA III', 'Regional / peridural', 'Completada'],
                'profilaxis' => [['Vancomicina 1g IV', 'Alternativa por alergia'], ['Cefazolina 2g IV', 'Complementaria']],
                'materiales' => [
                    ['Sistema tibial de rodilla', 1, 3200.00],
                    ['Componente femoral de rodilla', 1, 2800.00],
                    ['Inserto de polietileno 10mm', 1, 1100.00],
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

    /** @return array<string, MaterialProveedor> */
    private function materiales(): array
    {
        $proveedor = Proveedor::firstOrCreate(
            ['nombreProveedor' => 'Implantes Cuyo S.A.'],
            ['cuitProveedor' => '30712345678', 'telefonoProveedor' => '2614567890'],
        );

        $catalogo = [
            'Sistema tibial de rodilla' => ['7.02.01', 3200.00],
            'Componente femoral de rodilla' => ['7.02.02', 2800.00],
            'Inserto de polietileno 10mm' => ['7.02.03', 1100.00],
            'Malla de polipropileno' => ['5.01.04', 180.00],
        ];

        $materiales = [];

        foreach ($catalogo as $nombre => [$codigo, $precio]) {
            $material = Material::firstOrCreate(
                ['nombreMaterial' => $nombre],
                ['codMaterial' => $codigo],
            );

            $materiales[$nombre] = MaterialProveedor::firstOrCreate(
                ['idMaterial' => $material->idMaterial, 'idProveedor' => $proveedor->idProveedor],
                ['codExternoMaterialProveedor' => $codigo, 'precioExternoMaterialProveedor' => $precio, 'fechaActualizacionPrecio' => now()],
            );
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
                    'urlArchivoCirugiaTipoEstudio' => $diasAtras ? 'estudios/demo.pdf' : null,
                ],
            );
        }
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

    /** @param  list<array{0:string,1:string}>  $profilaxis */
    private function profilaxis(Cirugia $cirugia, array $profilaxis, ?string $alerta): void
    {
        if ($profilaxis === []) {
            return;
        }

        $cabecera = ProfilaxisAtbCirugia::firstOrCreate(
            ['idCirugia' => $cirugia->idCirugia],
            [
                'alertaProfilaxisAtbCirugia' => $alerta && str_contains($alerta, 'penicilina')
                    ? 'NO administrar ampicilina ni amoxicilina — alergia a penicilina documentada'
                    : null,
                'motivoProfilaxisAtbCirugia' => 'Profilaxis prequirúrgica estándar',
            ],
        );

        foreach ($profilaxis as [$droga, $rol]) {
            ProfilaxisAtbCirugiaProfilaxis::firstOrCreate(
                [
                    'idProfilaxisAtbCirugia' => $cabecera->idProfilaxisAtbCirugia,
                    'idProfilaxis' => Profilaxis::where('nombreProfilaxis', $droga)->value('idProfilaxis'),
                ],
                [
                    'idProfilaxisRol' => ProfilaxisRol::where('nombreProfilaxisRol', $rol)->value('idProfilaxisRol'),
                    'indicacionesProfilaxisAtbCirugiaProfilaxis' => 'Administrar 30-60 min antes de la incisión.',
                ],
            );
        }
    }

    /**
     * @param  list<array{0:string,1:int,2:float}>  $items
     * @param  array<string, MaterialProveedor>  $materiales
     */
    private function materialesDelCaso(Cirugia $cirugia, Plan $plan, array $items, array $materiales): void
    {
        $unidad = TipoMedida::where('nombreTipoMedida', 'Unidad')->value('idTipoMedida');
        $enAuditoria = EstadoPedidoMaterial::where('nombreEstadoPedidoMaterial', 'En auditoría')->value('idEstadoPedidoMaterial');

        foreach ($items as [$nombre, $cantidad, $precio]) {
            $materialProveedor = $materiales[$nombre];

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
