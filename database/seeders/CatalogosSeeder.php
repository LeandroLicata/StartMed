<?php

namespace Database\Seeders;

use App\Models\EstadoAutCirugia;
use App\Models\EstadoCirugia;
use App\Models\EstadoEvaluacionAnestesica;
use App\Models\EstadoHisopadoSarm;
use App\Models\EstadoPedidoHemoderivado;
use App\Models\EstadoPedidoMaterial;
use App\Models\EstadoPedidoTipoHemoderivado;
use App\Models\EstadoQuirofano;
use App\Models\GrupoSanguineo;
use App\Models\ObraSocial;
use App\Models\Plan;
use App\Models\Profilaxis;
use App\Models\ProfilaxisRol;
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
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Tablas de catalogo: valores estables que el resto del sistema referencia.
 * Es idempotente, se puede volver a correr sin duplicar.
 */
class CatalogosSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->cargar(TipoDocumento::class, 'nombreTipoDocumento', [
            'DNI', 'Pasaporte', 'Libreta Cívica', 'Libreta de Enrolamiento',
        ]);

        $this->cargar(GrupoSanguineo::class, 'nombreGrupoSanguineo', [
            '0-', '0+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+',
        ]);

        $this->cargar(Rol::class, 'nombreRol', [
            'Administrador', 'Gestor de quirófano', 'Cirujano', 'Anestesista',
            'Instrumentador', 'Enfermero', 'Dirección médica', 'Paciente',
        ]);

        $this->cargar(EstadoCirugia::class, 'nombreEstadoCirugia', [
            'Programada', 'Confirmada', 'En riesgo', 'Suspendida', 'Realizada',
            'En espera de confirmación', 'En espera', 'A reprogramar', 'Reprogramada',
        ]);

        $this->cargar(EstadoQuirofano::class, 'nombreEstadoQuirofano', [
            'Disponible', 'Ocupado', 'En mantenimiento', 'Fuera de servicio',
        ]);

        $this->cargar(EstadoAutCirugia::class, 'nombreEstadoAutCirugia', [
            'Pendiente de envío', 'Enviada', 'En auditoría médica', 'Aprobada', 'Rechazada',
            'Presupuesto enviado', 'Pendiente de documentación',
        ]);

        $this->cargar(EstadoPedidoMaterial::class, 'nombreEstadoPedidoMaterial', [
            'Solicitado', 'Presupuestado', 'En auditoría', 'Aprobado', 'Entregado', 'Rechazado',
        ]);

        $this->cargar(EstadoPedidoHemoderivado::class, 'nombreEstadoPedidoHemoderivado', [
            'Solicitado', 'En preparación', 'Confirmado', 'Anulado',
        ]);

        $this->cargar(EstadoPedidoTipoHemoderivado::class, 'nombreEstadoPedidoTipoHemoderivado', [
            'Solicitado', 'Reservado', 'Condicional', 'Entregado',
        ]);

        $this->cargar(EstadoEvaluacionAnestesica::class, 'nombreEstadoEvaluacionAnestesica', [
            'Pendiente', 'Cuestionario recibido', 'Completada', 'Diferida',
        ]);

        $this->cargar(TipoAnestesia::class, 'nombreTipoAnestesia', [
            'General', 'Regional / peridural', 'Sedación + local', 'Raquídea',
        ]);

        $this->cargar(TipoEstudio::class, 'nombreTipoEstudio', [
            'Hemograma', 'Coagulograma', 'Electrocardiograma', 'Radiografía de tórax',
            'Riesgo quirúrgico cardiológico',
        ]);

        $this->cargar(EstadoHisopadoSarm::class, 'nombreEstadoHisopadoSarm', [
            'Pendiente', 'Negativo', 'Positivo',
        ]);

        $this->cargar(TipoMedida::class, 'nombreTipoMedida', [
            'Unidad', 'Caja', 'Par', 'Set',
        ]);

        $this->cargar(TipoHemoderivado::class, 'nombreTipoHemoderivado', [
            'Glóbulos rojos desplasmatizados', 'Plasma fresco congelado',
            'Plaquetas', 'Crioprecipitados',
        ]);

        $this->cargar(TipoPreparacion::class, 'nombreTipoPreparacion', [
            'Ayuno', 'Higiene prequirúrgica', 'Medicación habitual', 'Documentación',
        ]);

        $this->cargar(TipoIndicacion::class, 'nombreTipoIndicacion', [
            'Sólidos', 'Líquidos claros', 'Tabaco', 'Ducha con antiséptico',
            'Suspender anticoagulantes',
        ]);

        $this->cargar(ProfilaxisRol::class, 'nombreProfilaxisRol', [
            'Principal', 'Complementaria', 'Alternativa por alergia',
        ]);

        $this->cargar(Profilaxis::class, 'nombreProfilaxis', [
            'Cefazolina 1g IV', 'Cefazolina 2g IV', 'Vancomicina 1g IV',
            'Clindamicina 600mg IV', 'Mupirocina nasal',
        ]);

        // Clasificacion ASA: alias y descripcion vienen del estandar.
        foreach ([
            ['ASA I', 'ASA I', 'Paciente sano'],
            ['ASA II', 'ASA II', 'Enfermedad sistémica leve controlada'],
            ['ASA III', 'ASA III', 'Enfermedad sistémica severa'],
            ['ASA IV', 'ASA IV', 'Enfermedad sistémica severa con riesgo vital constante'],
            ['ASA V', 'ASA V', 'Paciente moribundo, no se espera supervivencia sin cirugía'],
        ] as [$nombre, $alias, $descripcion]) {
            TipoASA::firstOrCreate(
                ['nombreTipoAsa' => $nombre],
                ['aliasTipoAsa' => $alias, 'descripcionTipoAsa' => $descripcion],
            );
        }

        foreach ([
            ['Colecistectomía laparoscópica', 'Extirpación de la vesícula biliar por laparoscopía.'],
            ['Prótesis total de rodilla', 'Reemplazo articular de rodilla con implante.'],
            ['Hernioplastia inguinal', 'Reparación de hernia inguinal con malla.'],
            ['Tiroidectomía', 'Extirpación total o parcial de la glándula tiroides.'],
            ['Artrodesis lumbar', 'Fusión vertebral con tornillos y barras.'],
            ['Apendicectomía', 'Extirpación del apéndice cecal.'],
        ] as [$nombre, $descripcion]) {
            TipoCirugia::firstOrCreate(
                ['nombreTipoCirugia' => $nombre],
                ['descripcionTipoCirugia' => $descripcion],
            );
        }

        // Opcion "Particular": sin obra social, no requiere autorizacion. Tiene que
        // existir siempre (no solo cuando corre la demo) para el alta de cirugias.
        $sinCobertura = ObraSocial::firstOrCreate(
            ['nombreObraSocial' => 'Sin obra social'],
            ['diasVigenciaOrden' => 0],
        );

        Plan::firstOrCreate(
            ['nombrePlan' => 'Particular', 'idobrasocial' => $sinCobertura->idObraSocial],
            ['es_sin_cobertura' => true, 'habilitado_autorizaciones' => false],
        );
    }

    /**
     * @param  class-string<Model>  $modelo
     * @param  list<string>  $nombres
     */
    private function cargar(string $modelo, string $columna, array $nombres): void
    {
        foreach ($nombres as $nombre) {
            $modelo::firstOrCreate([$columna => $nombre]);
        }
    }
}
