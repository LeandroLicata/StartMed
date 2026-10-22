<?php

namespace App\Services;

use App\Mail\AvisoCambioCobertura;
use App\Models\Cirugia;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\PlanObraSocial;
use App\Support\ResumenCirugia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa por mail a los gestores cuando un paciente con cirugias pendientes
 * cambia su obra social predeterminada. Solo avisa: AutCirugia no se toca.
 */
class AvisoCambioCoberturaService
{
    /** Estados en los que la cirugia ya no va a hacerse: no hace falta avisar. */
    private const ESTADOS_CERRADOS = ['Suspendida', 'Realizada'];

    /** La cobertura predeterminada vigente del paciente, o null si no tiene. */
    public static function predeterminadaDe(int $idPersona): ?int
    {
        return PlanObraSocial::query()
            ->where('idPersona', $idPersona)
            ->vigentes()
            ->where('esPredeterminadaPlanObraSocial', true)
            ->value('idPlanObraSocial');
    }

    /**
     * Compara la predeterminada de antes con la de ahora y, si cambio, manda
     * el aviso despues de la respuesta (defer, sin cola: en el deploy no hay
     * worker). Un fallo del mail se registra y nada mas.
     */
    public static function avisarSiCambio(int $idPersona, ?int $antes): void
    {
        $despues = static::predeterminadaDe($idPersona);

        if ($antes === $despues) {
            return;
        }

        defer(static function () use ($idPersona, $antes, $despues) {
            try {
                static::enviar($idPersona, $antes, $despues);
            } catch (Throwable $e) {
                Log::error('Portal: no se pudo enviar el aviso de cambio de obra social.', [
                    'idPersona' => $idPersona,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private static function enviar(int $idPersona, ?int $antes, ?int $despues): void
    {
        $pendientes = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonaPaciente', $idPersona)
            ->where('fechaHoraCirugia', '>=', today())
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $cirugia) => new ResumenCirugia($cirugia))
            ->reject(fn (ResumenCirugia $resumen) => $resumen->esAnterior()
                || in_array($resumen->estado(), self::ESTADOS_CERRADOS, true));

        if ($pendientes->isEmpty()) {
            return;
        }

        $destinatarios = Personal::query()
            ->whereHas('rolesVigentes', fn ($consulta) => $consulta->where('nombreRol', 'Gestor de quirófano'))
            ->pluck('mailInstitucional')
            ->filter(fn ($mail) => filter_var($mail, FILTER_VALIDATE_EMAIL))
            ->unique();

        if ($destinatarios->isEmpty()) {
            return;
        }

        Mail::to($destinatarios->all())->send(new AvisoCambioCobertura(
            Persona::find($idPersona)?->nombre_completo ?? 'Paciente',
            static::describir($antes),
            static::describir($despues),
            $pendientes
                ->map(fn (ResumenCirugia $resumen) => $resumen->cuando()->translatedFormat('D j/m H:i').' · '.$resumen->procedimiento())
                ->values()
                ->all(),
        ));
    }

    /** "OSDE · OSDE 410" a partir del id de la cobertura. */
    private static function describir(?int $idCobertura): ?string
    {
        if ($idCobertura === null) {
            return null;
        }

        $cobertura = PlanObraSocial::with('plan.obrasocial')->find($idCobertura);

        return $cobertura
            ? $cobertura->plan?->obrasocial?->nombreObraSocial.' · '.$cobertura->plan?->nombrePlan
            : null;
    }
}