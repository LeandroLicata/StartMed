<?php

namespace App\Http\Controllers;

use App\Support\PortalPacienteMock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PacientePortalController extends Controller
{
    public function __invoke(Request $request, string $seccion = 'resumen'): View
    {
        return view('paciente.portal', [
            'seccion' => $seccion,
            'portal' => PortalPacienteMock::datos($request->session()),
        ]);
    }

    public function accion(Request $request, string $accion): RedirectResponse
    {
        $reglas = match ($accion) {
            'estudio' => ['archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480']],
            'cuestionario' => [
                'alergia' => ['required', 'in:si,no'],
                'medicacion' => ['nullable', 'string', 'max:255'],
                'cirugias_previas' => ['required', 'in:si,no'],
                'fuma' => ['required', 'in:si,no'],
                'enfermedad_cronica' => ['nullable', 'string', 'max:255'],
                'complicaciones_anestesia' => ['required', 'in:si,no'],
            ],
            'contacto' => ['mensaje' => ['required', 'string', 'max:1000']],
            default => [],
        };

        $request->validate($reglas);

        $estado = $request->session()->get('portal_paciente', []);
        $estado[$accion] = true;
        $request->session()->put('portal_paciente', $estado);

        $destino = match ($accion) {
            'estudio' => 'estudios',
            'cuestionario' => 'preanestesica',
            'firmar' => 'consentimiento',
            'contacto' => 'contacto',
            default => 'resumen',
        };

        $mensaje = match ($accion) {
            'confirmar' => 'Asistencia confirmada. Gracias por avisarnos.',
            'reprogramar' => 'Recibimos tu solicitud. El equipo se va a comunicar con vos.',
            'estudio' => 'Estudio recibido correctamente (demostración).',
            'cuestionario' => 'Cuestionario enviado al Dr. Ramos.',
            'firmar' => 'Consentimiento firmado correctamente (demostración).',
            'contacto' => 'Mensaje enviado al equipo de cirugía.',
        };

        return redirect()->route('paciente.portal', ['seccion' => $destino])->with('exito', $mensaje);
    }
}
