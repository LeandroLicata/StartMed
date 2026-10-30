<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cirugia;
use App\Models\ConfigTipoExamenPreAnestesico;
use App\Models\Persona;
use App\Support\ResumenCirugia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Cirugias del paciente: proximas, historial y el detalle de cada una.
 *
 * La pertenencia de `{cirugia}` ya la verifica el middleware
 * `cirugia.del.paciente` del grupo de rutas: aca no se repite.
 */
class CirugiaController extends Controller
{
    /**
     * Pestañas del detalle, en el orden en que se muestran: clave => [etiqueta, icono].
     *
     * Cada una es un parcial en portal/cirugias/secciones/{clave}.blade.php, y
     * cada parcial es de quien lleva ese modulo: agregar o cambiar una
     * pestaña no toca las demas.
     */
    public const SECCIONES = [
        'resumen' => ['Resumen', 'home'],
        'estudios' => ['Estudios', 'upload_file'],
        'preanestesica' => ['Preanestésica', 'stethoscope'],
        'preparacion' => ['Preparación', 'no_food'],
        'consentimiento' => ['Consentimiento', 'draw'],
        'turnos' => ['Turnos', 'event'],
        'contacto' => ['Contacto', 'call'],
    ];

    public function proximas(): View
    {
        return view('portal.cirugias.lista', [
            'titulo' => 'Próximas cirugías',
            'casos' => $this->delPaciente(fn (Builder $q) => $q
                ->where('fechaHoraCirugia', '>=', today())
                ->orderBy('fechaHoraCirugia')),
            'vacio' => 'No tenés cirugías programadas.',
        ]);
    }

    /**
     * Todas las cirugias pasadas, en cualquier estado: una suspendida o
     * reprogramada tambien es parte de la historia del paciente.
     */
    public function historial(): View
    {
        return view('portal.cirugias.lista', [
            'titulo' => 'Historial de cirugías',
            'casos' => $this->delPaciente(fn (Builder $q) => $q
                ->where('fechaHoraCirugia', '<', today())
                ->orderByDesc('fechaHoraCirugia')),
            'vacio' => 'Todavía no tenés cirugías anteriores.',
        ]);
    }

    public function show(Cirugia $cirugia, string $seccion = 'resumen'): View
    {
        $cirugia->load([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE]);
        $caso = new ResumenCirugia($cirugia);

        return view('portal.cirugias.show', [
            'caso' => $caso,
            'seccion' => $seccion,
            'secciones' => self::SECCIONES,
            // Una cirugia pasada se mira, no se completa: sin firmar, sin
            // subir, sin responder. Cada pestaña con formularios lo respeta.
            'soloLectura' => $caso->esAnterior(),
            'cuestionarioVigente' => $seccion === 'preanestesica'
                ? ConfigTipoExamenPreAnestesico::vigente()
                : null,
        ]);
    }

    /**
     * @param  callable(Builder): Builder  $filtro
     * @return Collection<int, ResumenCirugia>
     */
    private function delPaciente(callable $filtro): Collection
    {
        /** @var Persona $paciente */
        $paciente = auth('paciente')->user();

        return $filtro(Cirugia::with(ResumenCirugia::RELACIONES)
            ->where('idPersonaPaciente', $paciente->idPersona))
            ->get()
            ->map(fn (Cirugia $cirugia) => new ResumenCirugia($cirugia));
    }
}
