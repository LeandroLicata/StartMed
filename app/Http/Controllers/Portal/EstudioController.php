<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cirugia;
use App\Models\CirugiaTipoEstudio;
use App\Services\ResultadoEstudioService;
use App\Support\DocumentoNoDisponible;
use App\Support\GestorDocumental;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Estudios prequirurgicos desde el portal: el paciente sube el resultado de
 * cada estudio que le pidieron y ve lo que ya esta subido.
 *
 * La subida y la entrega son las mismas que las del staff
 * (ResultadoEstudioService). Lo propio del portal son dos limites:
 *
 * - Solo se sube a un estudio pendiente. La base no registra quien subio un
 *   archivo, asi que dejar reemplazar permitiria pisar un resultado que cargo
 *   el laboratorio o el staff; si el paciente se equivoco, lo corrige el
 *   equipo desde el expediente.
 * - Nada se sube a una cirugia anterior (ResumenCirugia::esAnterior).
 *
 * La pertenencia de `{cirugia}` la verifica el middleware del grupo de rutas,
 * y la del estudio a esa cirugia, el scopeBindings() de las rutas.
 */
class EstudioController extends Controller
{
    public function subir(Request $request, Cirugia $cirugia, CirugiaTipoEstudio $cirugiaTipoEstudio, ResultadoEstudioService $resultados): RedirectResponse
    {
        $volver = redirect()->route('portal.cirugias.show', [$cirugia, 'estudios']);

        if ((new ResumenCirugia($cirugia))->esAnterior()) {
            return $volver->with('aviso', 'Es una cirugía anterior: ya no se pueden subir estudios.');
        }

        if (filled($cirugiaTipoEstudio->urlArchivoCirugiaTipoEstudio)) {
            return $volver->with('aviso', 'Ese estudio ya tiene un resultado. Si subiste un archivo equivocado, avisale al equipo del hospital.');
        }

        // Una bolsa de errores por estudio: el error se muestra en el estudio
        // que se intento subir y no repetido en todos.
        $request->validateWithBag(self::bolsaDeErrores($cirugiaTipoEstudio), [
            'archivoResultadoEstudio' => ['required', ...GestorDocumental::REGLAS_ARCHIVO],
        ]);

        try {
            $resultados->subir($cirugiaTipoEstudio, $request->file('archivoResultadoEstudio'));
        } catch (DocumentoNoDisponible $e) {
            report($e);

            return $volver->with('error', 'No pudimos guardar el archivo. Probá de nuevo en unos minutos.');
        }

        return $volver->with('exito', 'Listo, recibimos tu estudio.');
    }

    /**
     * Redirige al resultado con una URL firmada que vence: el puntero nunca
     * sale al HTML.
     */
    public function ver(Cirugia $cirugia, CirugiaTipoEstudio $cirugiaTipoEstudio, ResultadoEstudioService $resultados): RedirectResponse
    {
        abort_if(blank($cirugiaTipoEstudio->urlArchivoCirugiaTipoEstudio), 404);

        try {
            return redirect()->away($resultados->urlTemporal($cirugiaTipoEstudio));
        } catch (DocumentoNoDisponible $e) {
            report($e);

            return redirect()
                ->route('portal.cirugias.show', [$cirugia, 'estudios'])
                ->with('error', 'El archivo no está disponible en este momento.');
        }
    }

    public static function bolsaDeErrores(CirugiaTipoEstudio $estudio): string
    {
        return 'estudio'.$estudio->idCirugiaTipoEstudio;
    }
}
