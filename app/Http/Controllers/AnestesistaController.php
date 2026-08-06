<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\EstadoEvaluacionAnestesica;
use App\Models\EvaluacionAnestesica;
use App\Models\EvaluacionAnestesicaEstado;
use App\Models\EvaluacionTipoAnestesia;
use App\Models\EvaluacionTipoAsa;
use App\Models\Personal;
use App\Models\TipoAnestesia;
use App\Models\TipoASA;
use App\Support\Paginador;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnestesistaController extends Controller
{
    /** Evaluaciones por pagina: la bandeja crece con la agenda del anestesista. */
    private const POR_PAGINA = 10;

    /**
     * Bandeja del anestesista: las evaluaciones que tiene asignadas, separadas
     * por lo que le falta hacer a cada una.
     */
    public function index(Request $request): View
    {
        $personal = $this->personalLogueado($request);

        $evaluaciones = Cirugia::query()
            ->with([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE])
            ->where('idPersonalAnestesista', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        $completas = $evaluaciones->filter(fn (ResumenCirugia $r) => $r->evaluacionCompleta());

        return view('paneles.anestesista', [
            'personal' => $personal,
            // Solo el listado se recorta; los indicadores y el cuestionario del
            // proximo paciente siguen mirando la bandeja entera.
            'evaluaciones' => Paginador::deColeccion($evaluaciones, $request, self::POR_PAGINA),
            'pendientes' => $evaluaciones->reject(fn (ResumenCirugia $r) => $r->evaluacionCompleta())->values(),
            'indicadores' => [
                'total' => $evaluaciones->count(),
                'completas' => $completas->count(),
                'pendientes' => $evaluaciones->count() - $completas->count(),
                // ASA III o superior necesita monitoreo adicional.
                'riesgoAlto' => $evaluaciones->filter(
                    fn (ResumenCirugia $r) => in_array($r->asa(), ['ASA III', 'ASA IV', 'ASA V'], true)
                )->count(),
                'cuestionarios' => $evaluaciones->filter(
                    fn (ResumenCirugia $r) => $r->cuestionario()->isNotEmpty()
                )->count(),
            ],
        ]);
    }

    /**
     * Formulario para cargar la evaluación pre-anestésica de una cirugía que
     * todavía no tiene una.
     */
    public function evaluar(Request $request, Cirugia $cirugia): View|RedirectResponse
    {
        $this->autorizarCirugia($request, $cirugia);

        if ($cirugia->evaluacionAnestesicas()->exists()) {
            return redirect()->route('anestesista.editar', $cirugia);
        }

        return $this->formulario($request, $cirugia);
    }

    /**
     * Guarda la evaluación nueva: queda Completada con su ASA y su tipo de
     * anestesia vigentes.
     */
    public function store(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $this->autorizarCirugia($request, $cirugia);

        if ($cirugia->evaluacionAnestesicas()->exists()) {
            return redirect()->route('anestesista.editar', $cirugia);
        }

        $datos = $this->validar($request);

        // La decisión la trae el botón del formulario ('apto' / 'no_apto').
        $decision = $request->input('decision', 'apto');

        DB::transaction(function () use ($cirugia, $datos, $decision): void {
            $evaluacion = EvaluacionAnestesica::create([
                'idCirugia' => $cirugia->idCirugia,
                'observacionesEquipoEvaluacion' => $datos['observacionesEquipoEvaluacion'] ?? null,
                'observacionesPacienteEvaluacion' => $datos['observacionesPacienteEvaluacion'] ?? null,
            ]);

            $this->completar($evaluacion, $datos, $decision);
        });

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'evaluacion'])
            ->with('exito', 'Evaluación registrada correctamente.');
    }

    /**
     * Formulario para modificar una evaluación existente.
     */
    public function editar(Request $request, Cirugia $cirugia): View|RedirectResponse
    {
        $this->autorizarCirugia($request, $cirugia);

        if (! $cirugia->evaluacionAnestesicas()->exists()) {
            return redirect()->route('anestesista.evaluar', $cirugia);
        }

        return $this->formulario($request, $cirugia);
    }

    /**
     * Actualiza la evaluación: cierra el ASA / tipo de anestesia que estaban
     * vigentes y abre los nuevos, porque el esquema guarda el historial en
     * rangos de fecha.
     */
    public function update(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $this->autorizarCirugia($request, $cirugia);

        $evaluacion = $cirugia->evaluacionAnestesicas()->first();

        if ($evaluacion === null) {
            return redirect()->route('anestesista.evaluar', $cirugia);
        }

        $datos = $this->validar($request);

        // La decisión la trae el botón del formulario ('apto' / 'no_apto').
        $decision = $request->input('decision', 'apto');

        DB::transaction(function () use ($evaluacion, $datos, $decision): void {
            $evaluacion->update([
                'observacionesEquipoEvaluacion' => $datos['observacionesEquipoEvaluacion'] ?? null,
                'observacionesPacienteEvaluacion' => $datos['observacionesPacienteEvaluacion'] ?? null,
            ]);

            $this->completar($evaluacion, $datos, $decision);
        });

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'evaluacion'])
            ->with('exito', 'Evaluación actualizada correctamente.');
    }

    /**
     * Elimina la evaluación de la cirugía. Se borran antes las filas hijas,
     * porque las FK no llevan cascade y este registro no tiene fecha de baja.
     */
    public function destroy(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $this->autorizarCirugia($request, $cirugia);

        $evaluacion = $cirugia->evaluacionAnestesicas()->first();

        if ($evaluacion === null) {
            return redirect()->route('cirugias.show', [$cirugia, 'tab' => 'evaluacion']);
        }

        DB::transaction(function () use ($evaluacion): void {
            $evaluacion->evaluacionAnestesicaEstados()->delete();
            $evaluacion->evaluacionTipoAsas()->delete();
            $evaluacion->evaluacionTipoAnestesias()->delete();
            $evaluacion->delete();
        });

        return redirect()
            ->route('cirugias.show', [$cirugia, 'tab' => 'evaluacion'])
            ->with('exito', 'La evaluación fue eliminada. La cirugía vuelve a quedar sin evaluar.');
    }

    // --- Soporte -----------------------------------------------------------

    /**
     * @return array{
     *     idTipoAsa: int,
     *     idTipoAnestesia: int,
     *     observacionesEquipoEvaluacion: ?string,
     *     observacionesPacienteEvaluacion: ?string,
     * }
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'idTipoAsa' => ['required', 'exists:TipoASA,idTipoAsa'],
            'idTipoAnestesia' => ['required', 'exists:TipoAnestesia,idTipoAnestesia'],
            'observacionesEquipoEvaluacion' => ['nullable', 'string', 'max:255'],
            'observacionesPacienteEvaluacion' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * Deja vigente el estado según la decisión del anestesista y el ASA y el
     * tipo de anestesia recibidos, cerrando los registros que estaban abiertos.
     *
     * 'apto' → Completada; 'no_apto' → Diferida.
     *
     * @param  array{
     *     idTipoAsa: int,
     *     idTipoAnestesia: int,
     *     observacionesEquipoEvaluacion: ?string,
     *     observacionesPacienteEvaluacion: ?string,
     * }  $datos
     */
    private function completar(EvaluacionAnestesica $evaluacion, array $datos, string $decision = 'apto'): void
    {
        $nombreEstado = $decision === 'no_apto' ? 'Diferida' : 'Completada';

        $estado = EstadoEvaluacionAnestesica::where('nombreEstadoEvaluacionAnestesica', $nombreEstado)
            ->value('idEstadoEvaluacionAnestesica');

        $estadoVigente = $evaluacion->evaluacionAnestesicaEstados()
            ->whereNull('fechaFinEvaluacionAnestesicaEstado')
            ->first();

        if ($estadoVigente?->idEstadoEvaluacionAnestesica !== $estado) {
            $estadoVigente?->update(['fechaFinEvaluacionAnestesicaEstado' => now()]);

            EvaluacionAnestesicaEstado::create([
                'idEvaluacionAnestesica' => $evaluacion->idEvaluacionAnestesica,
                'idEstadoEvaluacionAnestesica' => $estado,
                'fechaInicioEvaluacionAnestesicaEstado' => now(),
            ]);
        }

        $this->asignar(
            $evaluacion,
            'evaluacionTipoAsas', 'fechaFinTipoAsa', 'fechaInicioTipoAsa', 'idTipoAsa',
            EvaluacionTipoAsa::class, $datos['idTipoAsa'],
        );

        $this->asignar(
            $evaluacion,
            'evaluacionTipoAnestesias', 'fechaFinTipoAnestesia', 'fechaInicioTipoAnestesia', 'idTipoAnestesia',
            EvaluacionTipoAnestesia::class, $datos['idTipoAnestesia'],
        );
    }

    /**
     * Deja vigente el valor recibido: si había otro registro abierto lo cierra
     * y abre uno nuevo con el valor del formulario.
     */
    private function asignar(
        EvaluacionAnestesica $evaluacion,
        string $relacion,
        string $columnaFin,
        string $columnaInicio,
        string $columnaValor,
        string $modelo,
        mixed $valor
    ): void {
        $vigente = $evaluacion->{$relacion}()
            ->whereNull($columnaFin)
            ->first();

        if ($vigente !== null && $vigente->{$columnaValor} === $valor) {
            return;
        }

        $vigente?->update([$columnaFin => now()]);

        $modelo::create([
            'idEvaluacionAnestesica' => $evaluacion->idEvaluacionAnestesica,
            $columnaValor => $valor,
            $columnaInicio => now(),
        ]);
    }

    private function formulario(Request $request, Cirugia $cirugia): View
    {
        $this->autorizarCirugia($request, $cirugia);

        $cirugia->load(ResumenCirugia::RELACIONES);

        return view('paneles.anestesista.form', [
            'cirugia' => $cirugia,
            'resumen' => new ResumenCirugia($cirugia),
            'evaluacion' => $cirugia->evaluacionAnestesicas->first(),
            'tiposAsa' => TipoASA::query()->whereNull('fechaBajaTipoAsa')->orderBy('aliasTipoAsa')->get(),
            'tiposAnestesia' => TipoAnestesia::query()->whereNull('fechaBajaTipoAnestesia')->orderBy('nombreTipoAnestesia')->get(),
        ]);
    }

    private function personalLogueado(Request $request): Personal
    {
        $personal = $request->user()->personal;

        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        return $personal;
    }

    /**
     * Solo el anestesista titular de la cirugía (o el administrador) puede
     * cargar o modificar su evaluación.
     */
    private function autorizarCirugia(Request $request, Cirugia $cirugia): Personal
    {
        $personal = $this->personalLogueado($request);

        $esSuCirugia = $cirugia->idPersonalAnestesista === $personal->idPersonal;

        abort_if(! $esSuCirugia && ! $request->user()->tieneRol('Administrador'), 403,
            'Esta cirugía no te está asignada.');

        return $personal;
    }
}
