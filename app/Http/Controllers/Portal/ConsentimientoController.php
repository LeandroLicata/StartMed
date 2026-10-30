<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cirugia;
use App\Models\ConfigConsentimiento;
use App\Models\ConsentimientoPaciente;
use App\Support\ResumenCirugia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Firma del consentimiento informado desde el portal del paciente.
 *
 * El paciente lee el documento en una ventana, marca que lo leyó y confirma dos
 * veces. Se guarda el texto exacto que leyó (snapshot inmutable) con su SHA-256,
 * la fecha, la IP y el navegador.
 *
 * El formulario manda el hash del texto que se le mostró: si la plantilla
 * cambió entre que abrió la pantalla y que firmó, no se firma un texto que no
 * leyó, se le pide que lo vuelva a leer.
 *
 * La pertenencia de {cirugia} la verifica el middleware del grupo de rutas
 * (cirugia.del.paciente).
 */
class ConsentimientoController extends Controller
{
    public function firmar(Request $request, Cirugia $cirugia): RedirectResponse
    {
        $volver = redirect()->route('portal.cirugias.show', [$cirugia, 'consentimiento']);

        $request->validate([
            'leido' => ['accepted'],
            'hash' => ['required', 'string', 'size:64'],
        ], [
            'leido.accepted' => 'Marcá que leíste el documento antes de firmar.',
        ]);

        $cirugia->load(ResumenCirugia::RELACIONES);

        if ((new ResumenCirugia($cirugia))->esAnterior()) {
            return $volver->with('aviso', 'Es una cirugía anterior: ya no se puede firmar el consentimiento.');
        }

        $resultado = DB::transaction(function () use ($request, $cirugia) {
            Cirugia::whereKey($cirugia->idCirugia)->lockForUpdate()->first();
            $cirugia->load(ResumenCirugia::RELACIONES);
            $caso = new ResumenCirugia($cirugia);

            if ($caso->consentimientoFirmado()) {
                return 'firmado';
            }

            $existente = $caso->consentimiento();
            $vigente = $existente ? null : ConfigConsentimiento::vigentePara($cirugia->idTipoCirugia);
            $texto = $caso->documentoConsentimiento($vigente);

            if ($texto === null) {
                return 'sin-documento';
            }

            $hash = hash('sha256', $texto);

            if (! hash_equals($hash, (string) $request->input('hash'))) {
                return 'cambio';
            }

            $paciente = $cirugia->paciente;

            $firma = [
                'fechaFirmaConsentimiento' => now(),
                'firmaConsentimiento' => 'Aceptación electrónica en el portal: '
                    .$paciente->nombre_completo.' (DNI '.$paciente->documento.')',
                'ipFirmaConsentimiento' => $request->ip(),
                'userAgentFirmaConsentimiento' => mb_substr((string) $request->userAgent(), 0, 255),
            ];

            if ($existente) {
                $existente->update($firma + ['hashConsentimiento' => $hash]);
            } else {
                ConsentimientoPaciente::create($firma + [
                    'idCirugia' => $cirugia->idCirugia,
                    'idConfigConsentimiento' => $vigente->idConfigConsentimiento,
                    'textoRenderizadoConsentimiento' => $texto,
                    'hashConsentimiento' => $hash,
                ]);
            }

            return 'ok';
        });

        return match ($resultado) {
            'ok' => $volver->with('exito', 'Listo, firmaste el consentimiento informado.'),
            'firmado' => $volver->with('aviso', 'El consentimiento ya estaba firmado.'),
            'sin-documento' => $volver->with('error', 'Todavía no hay un consentimiento cargado para este procedimiento.'),
            'cambio' => $volver->with('aviso', 'El documento se actualizó mientras lo leías. Volvé a leerlo antes de firmar.'),
        };
    }
}
