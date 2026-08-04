<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreguntaRequest;
use App\Http\Requests\RespuestaRequest;
use App\Models\ConfigTipoExamenPreAnestesico as Version;
use App\Models\ConfigTipoExamenPreAnestesicoPregunta as Pregunta;
use App\Models\ConfigTipoExamenPreAnestesicoPreguntaRespuesta as Respuesta;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cuestionario preanestesico: el formulario que el paciente completa antes de
 * la evaluacion con el anestesista.
 *
 * Es un arbol de tres niveles —version, preguntas, opciones de respuesta— con
 * vigencia por fechas, asi que no entra en el ABM generico de catalogos.
 *
 * Una version usada queda congelada, y esto es mas fuerte que en los
 * consentimientos. ResumenCirugia::cuestionario() lee el texto de la pregunta y
 * de la opcion en vivo desde estas tablas: no hay snapshot como el que guarda
 * ConsentimientoPaciente. Editar una pregunta despues de que alguien la
 * respondio cambiaria retroactivamente que se le pregunto a ese paciente.
 *
 * Todo cuelga de una sola pantalla por version, con formularios chicos, para no
 * necesitar JavaScript ni obligar a navegar tres niveles de profundidad.
 */
class CuestionarioController extends Controller
{
    public function index(): View
    {
        return view('admin.cuestionario.index', [
            'versiones' => Version::query()
                ->withCount(['configTipoExamenPreAnestesicoPreguntas', 'examenPreAnestesicoConfiges'])
                ->orderByRaw('fechaFinVigeConfigTipoExamenPreAnestesico IS NULL DESC')
                ->orderByDesc('fechaInicioVigeConfigTipoExamenPreAnestesico')
                ->orderByDesc('idConfigTipoExamenPreAnestesico')
                ->get(),
        ]);
    }

    /**
     * Publica una version nueva clonando el arbol de la vigente, que es lo que
     * se necesita casi siempre: se retoca un cuestionario, no se escribe uno
     * desde cero.
     */
    public function store(): RedirectResponse
    {
        $version = DB::transaction(function () {
            $anterior = $this->vigente();
            $anterior?->update(['fechaFinVigeConfigTipoExamenPreAnestesico' => now()]);

            $nueva = Version::create(['fechaInicioVigeConfigTipoExamenPreAnestesico' => now()]);

            foreach ($anterior?->configTipoExamenPreAnestesicoPreguntas ?? [] as $pregunta) {
                $copia = Pregunta::create([
                    'idConfigTipoExamenPreAnestesico' => $nueva->idConfigTipoExamenPreAnestesico,
                    'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => $pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta,
                    'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => $pregunta->requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta,
                ]);

                foreach ($pregunta->configTipoExamenPreAnestesicoPreguntaRespuestas as $respuesta) {
                    Respuesta::create([
                        'idConfigTipoExamenPreAnestesicoPregunta' => $copia->idConfigTipoExamenPreAnestesicoPregunta,
                        'nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => $respuesta->nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta,
                    ]);
                }
            }

            return $nueva;
        });

        Auditor::registrar(Auditor::ALTA, $version, 'Cuestionario preanestésico');

        return redirect()
            ->route('admin.cuestionario.show', $version)
            ->with('exito', 'Versión nueva publicada, con una copia de las preguntas anteriores para editar.');
    }

    public function show(Version $version): View
    {
        return view('admin.cuestionario.version', [
            'version' => $version->loadCount('examenPreAnestesicoConfiges'),
            'preguntas' => $version->configTipoExamenPreAnestesicoPreguntas()
                ->with('configTipoExamenPreAnestesicoPreguntaRespuestas')
                ->orderBy('idConfigTipoExamenPreAnestesicoPregunta')
                ->get(),
            'editable' => $this->esEditable($version),
        ]);
    }

    public function destroy(Version $version): RedirectResponse
    {
        $version->update(['fechaFinVigeConfigTipoExamenPreAnestesico' => now()]);

        Auditor::registrar(Auditor::BAJA, $version, 'Cuestionario preanestésico');

        return redirect()
            ->route('admin.cuestionario.index')
            ->with('exito', 'Cuestionario retirado. Queda en el historial y se puede publicar uno nuevo.');
    }

    // --- Preguntas ---

    public function agregarPregunta(PreguntaRequest $request, Version $version): RedirectResponse
    {
        $this->exigirEditable($version);

        $pregunta = Pregunta::create([
            'idConfigTipoExamenPreAnestesico' => $version->idConfigTipoExamenPreAnestesico,
            ...$request->validated(),
        ]);

        Auditor::registrar(Auditor::ALTA, $pregunta, $this->comoSeLlama($pregunta));

        return back()->with('exito', 'Pregunta agregada.');
    }

    public function actualizarPregunta(PreguntaRequest $request, Version $version, Pregunta $pregunta): RedirectResponse
    {
        $this->exigirEditable($version);

        $antes = Auditor::foto($pregunta);
        $pregunta->update($request->validated());

        if ($cambios = Auditor::diferencia($pregunta, $antes)) {
            Auditor::registrar(Auditor::EDICION, $pregunta, $this->comoSeLlama($pregunta), $cambios);
        }

        return back()->with('exito', 'Pregunta actualizada.');
    }

    public function eliminarPregunta(Version $version, Pregunta $pregunta): RedirectResponse
    {
        $this->exigirEditable($version);

        $descripcion = $this->comoSeLlama($pregunta);

        DB::transaction(function () use ($pregunta) {
            // Sin borrado logico: estas tablas no tienen columna de baja, y una
            // version sin usar no la referencia nadie.
            $pregunta->configTipoExamenPreAnestesicoPreguntaRespuestas()->delete();
            $pregunta->delete();
        });

        Auditor::registrar(Auditor::BAJA, $pregunta, $descripcion);

        return back()->with('exito', 'Pregunta eliminada.');
    }

    // --- Opciones de respuesta ---

    public function agregarRespuesta(RespuestaRequest $request, Version $version, Pregunta $pregunta): RedirectResponse
    {
        $this->exigirEditable($version);

        Respuesta::create([
            'idConfigTipoExamenPreAnestesicoPregunta' => $pregunta->idConfigTipoExamenPreAnestesicoPregunta,
            ...$request->validated(),
        ]);

        return back()->with('exito', 'Opción agregada.');
    }

    public function eliminarRespuesta(Version $version, Pregunta $pregunta, Respuesta $respuesta): RedirectResponse
    {
        $this->exigirEditable($version);

        $respuesta->delete();

        return back()->with('exito', 'Opción eliminada.');
    }

    // --- Apoyo ---

    private function vigente(): ?Version
    {
        return Version::query()
            ->whereNull('fechaFinVigeConfigTipoExamenPreAnestesico')
            ->with('configTipoExamenPreAnestesicoPreguntas.configTipoExamenPreAnestesicoPreguntaRespuestas')
            ->first();
    }

    /**
     * Se edita mientras siga vigente y no la haya respondido nadie.
     */
    private function esEditable(Version $version): bool
    {
        return $version->fechaFinVigeConfigTipoExamenPreAnestesico === null
            && $version->examenPreAnestesicoConfiges()->doesntExist();
    }

    private function exigirEditable(Version $version): void
    {
        abort_unless(
            $this->esEditable($version),
            403,
            'Este cuestionario ya se respondió o está cerrado: publicá una versión nueva.',
        );
    }

    private function comoSeLlama(Pregunta $pregunta): string
    {
        return 'Pregunta «'.$pregunta->nombrePreguntaConfigTipoExamenPreAnestesicoPregunta.'»';
    }
}
