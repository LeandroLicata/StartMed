<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Support\ResumenCirugia;
use Illuminate\View\View;

class PortalPacienteController extends Controller
{
    /**
     * Lo que vería el paciente sobre su cirugía: turnos, estudios,
     * preparación y consentimiento, en lenguaje llano.
     *
     * Hoy la abre el equipo del hospital, no el paciente: `Usuario` cuelga de
     * `Personal` y un paciente es una `Persona` sin legajo, asi que el esquema
     * no tiene por donde autenticarlo. La pantalla queda lista para cuando se
     * defina ese acceso.
     */
    public function __invoke(Cirugia $cirugia): View
    {
        $cirugia->load([...ResumenCirugia::RELACIONES, ...ResumenCirugia::RELACIONES_EXPEDIENTE]);

        return view('cirugias.portal-paciente', [
            'caso' => new ResumenCirugia($cirugia),
        ]);
    }
}
