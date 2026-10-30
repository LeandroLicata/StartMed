<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\MensajePacienteAlEquipo;
use App\Models\Cirugia;
use App\Models\Persona;
use App\Services\AvisoCambioCoberturaService;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Envío de mensajes del paciente al equipo de quirófano.
 * No guarda nada en la base de datos: se manda por correo a los gestores vigentes.
 */
class ContactoController extends Controller
{
    public function enviar(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $caso = new ResumenCirugia($cirugia);

        // Antes de validar: en una cirugía anterior no hay formulario, así que
        // no tiene sentido devolver errores de un campo que no se ve.
        if ($caso->esAnterior()) {
            return redirect()->route('portal.cirugias.show', [$cirugia, 'contacto'])
                ->with('aviso', 'Esta cirugía ya pasó: no podés enviar mensajes para el equipo.');
        }

        $datos = $request->validate([
            'mensaje' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $destinatarios = AvisoCambioCoberturaService::emailsGestores();

        if ($destinatarios->isEmpty()) {
            Log::warning('Portal: no hay gestores de quirófano con email válido para recibir el mensaje del paciente.', [
                'idCirugia' => $cirugia->idCirugia,
            ]);

            return redirect()->route('portal.cirugias.show', [$cirugia, 'contacto'])
                ->with('exito', 'Listo, le mandamos tu mensaje al equipo. Te van a responder por teléfono o email.');
        }

        /** @var Persona|null $paciente */
        $paciente = auth('paciente')->user();

        $nombrePaciente = $paciente?->nombre_completo ?? 'Paciente';
        $dniPaciente = $paciente?->documento ?? 'Sin DNI';
        $tipoCirugia = $caso->procedimiento();
        $fechaCirugia = $caso->cuando()?->translatedFormat('d/m/Y H:i');
        $idCirugia = $cirugia->idCirugia;
        $mensaje = $datos['mensaje'];
        $replyToEmail = filter_var($paciente?->contacto_email_direccion, FILTER_VALIDATE_EMAIL)
            ? $paciente->contacto_email_direccion
            : null;

        defer(static function () use ($destinatarios, $nombrePaciente, $dniPaciente, $tipoCirugia, $fechaCirugia, $idCirugia, $mensaje, $replyToEmail) {
            try {
                Mail::to($destinatarios->all())->send(new MensajePacienteAlEquipo(
                    paciente: $nombrePaciente,
                    dni: $dniPaciente,
                    tipoCirugia: $tipoCirugia,
                    fechaCirugia: $fechaCirugia,
                    idCirugia: $idCirugia,
                    mensaje: $mensaje,
                    replyToEmail: $replyToEmail,
                ));
            } catch (Throwable $e) {
                Log::error('Portal: no se pudo enviar el mensaje del paciente al equipo.', [
                    'idCirugia' => $idCirugia,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return redirect()->route('portal.cirugias.show', [$cirugia, 'contacto'])
            ->with('exito', 'Listo, le mandamos tu mensaje al equipo. Te van a responder por teléfono o email.');
    }
}
