<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SolicitarCambioEmailRequest;
use App\Mail\AvisoEmailCambiado;
use App\Mail\ConfirmarCambioEmail;
use App\Models\Persona;
use App\Support\EnlaceCambioEmail;
use App\Support\MailerSeguro;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Cambio de email del paciente, en tres pasos:
 *
 * 1. `solicitar()` (con sesion): manda un link de un solo uso al email NUEVO.
 *    El email guardado no cambia todavia, asi un email mal escrito no deja al
 *    paciente sin poder entrar.
 * 2. `mostrar()` (sin sesion): el GET del link solo valida y muestra el boton.
 *    Un cliente de correo que abre los links para analizarlos no lo gasta.
 * 3. `confirmar()` (sin sesion): el POST del boton consume el link, guarda el
 *    email y avisa al anterior.
 *
 * Los pasos 2 y 3 no piden sesion porque el paciente puede abrir el mail en
 * otro dispositivo; ver App\Support\EnlaceCambioEmail.
 */
class CambioEmailController extends Controller
{
    public function solicitar(SolicitarCambioEmailRequest $request): RedirectResponse
    {
        /** @var Persona $persona */
        $persona = auth('paciente')->user();
        $emailNuevo = $request->validated('email_nuevo');

        // Con un mailer que escribe el mail en el servidor, quien lea el log
        // podria confirmar el cambio: ni se emite ni se manda.
        if (MailerSeguro::exponeLosMails()) {
            Log::error('Portal: el mailer por defecto («'.config('mail.default').'») deja los mails legibles en el servidor; '
                .'no se emitió el enlace de cambio de email. Configurá un mailer real (MAIL_MAILER).');

            return $this->noSePudoMandar();
        }

        try {
            $enlace = EnlaceCambioEmail::emitir($persona, $emailNuevo);
        } catch (LockTimeoutException) {
            return $this->noSePudoMandar();
        }

        $nombre = (string) $persona->nombres;

        // Despues de responder, como el enlace de acceso: el deploy no tiene cola.
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
            ->with('info', "Te mandamos un enlace a {$emailNuevo}. Abrilo para confirmar el cambio: "
                .'hasta entonces seguimos usando tu email actual.');
    }

    public function mostrar(Request $request, int $persona, string $nonce): Response|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->enlaceInvalido($persona, 'El enlace venció o no es válido. Pedí el cambio de nuevo desde tu perfil.');
        }

        $email = EnlaceCambioEmail::pendiente($persona, $nonce);

        if ($email === null || ! $this->personaActiva($persona)) {
            return $this->enlaceInvalido($persona);
        }

        // La URL lleva la firma y el nonce: que no viaje en el Referer ni quede en cache.
        return response()
            ->view('portal.cambio-email-confirmar', ['accion' => $request->fullUrl(), 'email' => $email])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }

    public function confirmar(Request $request, int $persona, string $nonce): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->enlaceInvalido($persona, 'El enlace venció o no es válido. Pedí el cambio de nuevo desde tu perfil.');
        }

        $paciente = $this->personaActiva($persona);
        $email = $paciente ? EnlaceCambioEmail::consumir($persona, $nonce) : null;

        if ($email === null) {
            return $this->enlaceInvalido($persona);
        }

        $anterior = $paciente->contacto_email_direccion;
        $paciente->update(['contacto_email_direccion' => $email]);

        if ($anterior !== null && mb_strtolower($anterior) !== mb_strtolower($email)) {
            $this->avisarAlEmailAnterior($anterior, (string) $paciente->nombres);
        }

        $mensaje = 'Listo, tu email ahora es '.$email.'.';

        return $this->esElPacienteLogueado($persona)
            ? redirect()->route('portal.perfil')->with('exito', $mensaje)
            : redirect()->route('portal.ingresar')->with('exito', $mensaje.' Ya podés ingresar con tu DNI.');
    }

    /**
     * Si alguien con la sesion del paciente abierta cambio el email, el dueño
     * se entera en el buzon que usaba hasta ahora. No lleva ningun link: no
     * hace falta que el mailer sea seguro para mandarlo.
     */
    private function avisarAlEmailAnterior(string $anterior, string $nombre): void
    {
        defer(static function () use ($anterior, $nombre) {
            try {
                Mail::to($anterior)->send(new AvisoEmailCambiado($nombre));
            } catch (Throwable $e) {
                Log::error('Portal: no se pudo avisar al email anterior del cambio.', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function personaActiva(int $idPersona): ?Persona
    {
        return Persona::whereKey($idPersona)->whereNull('fechaHoraBajaPersona')->first();
    }

    private function esElPacienteLogueado(int $idPersona): bool
    {
        return (int) auth('paciente')->id() === $idPersona;
    }

    /**
     * Con la sesion del mismo paciente vuelve al perfil; si no, al ingreso.
     * Mandar a alguien sin sesion al perfil perderia el mensaje en el rebote
     * del middleware de autenticacion.
     */
    private function enlaceInvalido(int $idPersona, string $mensaje = 'Este enlace ya se usó o fue reemplazado por uno más nuevo. Pedí el cambio de nuevo desde tu perfil.'): RedirectResponse
    {
        return $this->esElPacienteLogueado($idPersona)
            ? redirect()->route('portal.perfil')->with('error', $mensaje)
            : redirect()->route('portal.ingresar')->with('error', $mensaje);
    }

    private function noSePudoMandar(): RedirectResponse
    {
        return redirect()->route('portal.perfil')
            ->with('error', 'No pudimos mandarte el enlace en este momento. Probá de nuevo en unos minutos.');
    }
}
