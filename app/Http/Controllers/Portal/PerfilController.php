<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ActualizarDatosRequest;
use App\Http\Requests\Portal\AgregarObraSocialRequest;
use App\Http\Requests\Portal\SolicitarCambioEmailRequest;
use App\Mail\ConfirmarCambioEmail;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use App\Services\AvisoCambioCoberturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Perfil del paciente: datos personales y obras sociales.
 */
class PerfilController extends Controller
{
    public function show(): View
    {
        $persona = auth('paciente')->user();

        $coberturas = $persona->planObraSociales()
            ->with('plan.obrasocial')
            ->get();

        $vigentes = $coberturas->filter(fn ($cobertura) => $cobertura->estaVigente());
        $historicas = $coberturas->reject(fn ($cobertura) => $cobertura->estaVigente());

        $planes = Plan::disponiblesParaPaciente();

        return view('portal.perfil', [
            'persona' => $persona->load('grupoSanguineo'),
            'vigentes' => $vigentes,
            'historicas' => $historicas,
            'planes' => $planes,
        ]);
    }

    /**
     * Guarda solo los datos que el paciente puede editar: teléfonos y donación.
     * Lo de solo lectura (DNI, nacimiento, grupo) queda afuera porque no está
     * en las reglas del request, y validated() devuelve solo lo validado.
     */
    public function actualizarDatos(ActualizarDatosRequest $request): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $persona->update($request->validated());

        return redirect()->route('portal.perfil')
            ->with('exito', 'Tus datos quedaron guardados.');
    }

    /**
     * No cambia el email: manda un link firmado al correo NUEVO. Recién cuando
     * lo abre (confirmarCambioEmail) se guarda. Así un email mal escrito no deja
     * al paciente sin poder entrar al portal.
     */
    public function solicitarCambioEmail(SolicitarCambioEmailRequest $request): RedirectResponse
    {
        $persona = auth('paciente')->user();
        $emailNuevo = $request->validated('email_nuevo');
        $nombre = $persona->nombres;

        // El email viaja dentro del link; la firma impide que alguien lo cambie.
        $enlace = URL::temporarySignedRoute(
            'portal.perfil.email.confirmar',
            now()->addMinutes(ConfirmarCambioEmail::VIGENCIA_MINUTOS),
            ['persona' => $persona->idPersona, 'email' => $emailNuevo],
        );

        // Se manda después de responder, como el enlace de acceso: no hay cola en el deploy.
        defer(static function () use ($emailNuevo, $enlace, $nombre) {
            try {
                Mail::to($emailNuevo)->send(new ConfirmarCambioEmail($enlace, $nombre));
            } catch (Throwable $e) {
                Log::error('Portal: no se pudo enviar la confirmación de cambio de email.', [
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return redirect()->route('portal.perfil')
            ->with('info', "Te mandamos un link a {$emailNuevo}. Abrilo para confirmar el cambio.");
    }

    public function marcarPredeterminada(int $cobertura): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $elegida = $persona->planObraSociales()->findOrFail($cobertura);

        if (! $elegida->estaVigente()) {
            return back()->with('error', 'Solo podés elegir una obra social vigente.');
        }

        $antes = AvisoCambioCoberturaService::predeterminadaDe($persona->idPersona);

        DB::transaction(function () use ($persona, $elegida) {
            // Se desmarcan solo las demas: si la elegida ya tenia el flag en
            // memoria, update() no ve cambios y no la volveria a marcar.
            $persona->planObraSociales()
                ->whereKeyNot($elegida->getKey())
                ->update(['esPredeterminadaPlanObraSocial' => false]);
            $elegida->update(['esPredeterminadaPlanObraSocial' => true]);
        });

        AvisoCambioCoberturaService::avisarSiCambio($persona->idPersona, $antes);

        return redirect()->route('portal.perfil')
            ->with('exito', 'Listo, quedó como tu obra social predeterminada.');
    }

    public function darDeBaja(int $cobertura): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $baja = $persona->planObraSociales()->findOrFail($cobertura);

        if (! $baja->estaVigente()) {
            return back()->with('error', 'Esa obra social ya estaba dada de baja.');
        }

        $antes = AvisoCambioCoberturaService::predeterminadaDe($persona->idPersona);

        $eraPredeterminada = $baja->esPredeterminadaPlanObraSocial;

        $baja->update([
            'fechaFinPlanObraSocial' => now(),
            'esPredeterminadaPlanObraSocial' => false,
        ]);

        AvisoCambioCoberturaService::avisarSiCambio($persona->idPersona, $antes);

        if ($eraPredeterminada) {
            return redirect()->route('portal.perfil')
                ->with('aviso', 'Diste de baja tu obra social predeterminada. Elegí otra o agregá una nueva.');
        }

        return redirect()->route('portal.perfil')
            ->with('exito', 'La obra social quedó dada de baja.');
    }

    public function agregar(AgregarObraSocialRequest $request): RedirectResponse
    {
        $persona = auth('paciente')->user();

        $yaLaTiene = $persona->planObraSociales()
            ->vigentes()
            ->where('idPlan', $request->integer('idPlan'))
            ->exists();

        if ($yaLaTiene) {
            return back()->withInput()->with('error', 'Ya tenés ese plan entre tus obras sociales vigentes.');
        }

        $antes = AvisoCambioCoberturaService::predeterminadaDe($persona->idPersona);

        $nueva = PlanObraSocial::agregar(
            $persona->idPersona,
            $request->integer('idPlan'),
            $request->input('nroBeneficiario'),
        );

        AvisoCambioCoberturaService::avisarSiCambio($persona->idPersona, $antes);

        return redirect()->route('portal.perfil')
            ->with('exito', $nueva->esPredeterminadaPlanObraSocial
                ? 'Agregaste la obra social. Quedó como tu predeterminada.'
                : 'Agregaste la obra social.');
    }
}
