<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SolicitarAccesoRequest;
use App\Mail\EnlaceAccesoPortal;
use App\Models\Persona;
use App\Support\EnlaceAccesoPaciente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Entrada y salida del portal del paciente.
 *
 * El acceso es por DNI: `solicitar()` manda un link firmado de un solo uso al
 * email de la Persona, `entrar()` lo muestra y `confirmar()` autentica (ver
 * App\Support\EnlaceAccesoPaciente). Ademas, `entrarLocal()` deja entrar como
 * un paciente de la demo para ver las pantallas del portal sin email.
 */
class AccesoController extends Controller
{
    /** María García, la paciente de la demo con cirugía programada. */
    public const DOCUMENTO_DEMO = '28456789';

    /** Nombre del tipo de documento por el que se busca en el portal (protegido en Catalogos). */
    public const TIPO_DOCUMENTO = 'DNI';

    /** Pedidos de link por DNI dentro de la ventana: el limite no depende de que el DNI exista. */
    private const PEDIDOS_POR_DNI = 3;

    private const VENTANA_SEGUNDOS = EnlaceAccesoPaciente::VIGENCIA_MINUTOS * 60;

    public function ingresar(): View|RedirectResponse
    {
        if (Auth::guard('paciente')->check()) {
            return redirect()->route('portal.inicio');
        }

        return view('portal.ingresar');
    }

    /**
     * Pide el link. La respuesta es la MISMA exista o no el DNI, tenga o no
     * email, y haya o no superado el limite: cualquier diferencia (mensaje,
     * codigo o demora) le dice a quien prueba DNIs cuales son de pacientes.
     * Por eso el mail se manda despues de la respuesta, sin cola, y por eso
     * superar el limite solo omite el mail en vez de mostrar otro mensaje.
     */
    public function solicitar(SolicitarAccesoRequest $request): RedirectResponse
    {
        $documento = $request->validated('documento');
        $clave = "portal-acceso:dni:{$documento}";

        if (! RateLimiter::tooManyAttempts($clave, self::PEDIDOS_POR_DNI)) {
            RateLimiter::hit($clave, self::VENTANA_SEGUNDOS);

            if ($persona = $this->personaParaEnlace($documento)) {
                $this->enviarEnlaceDespuesDeResponder($persona->idPersona);
            }
        }

        return redirect()->route('portal.ingresar')->with(
            'info',
            'Si el DNI corresponde a un paciente con un correo registrado, te enviamos un enlace para ingresar. '
            .'Vale '.EnlaceAccesoPaciente::VIGENCIA_MINUTOS.' minutos y se usa una sola vez. '
            .'Si no te llegó el mail, probá de nuevo en unos minutos o consultá en recepción.',
        );
    }

    /**
     * Lo que abre el paciente desde el mail. Solo valida y muestra el boton:
     * no autentica ni quema el nonce, porque un cliente de correo que analiza
     * los links tambien hace este GET.
     */
    public function entrar(Request $request, int $persona, string $nonce): Response|RedirectResponse
    {
        if ($rechazo = $this->rechazarEnlace($request, $persona, $nonce, quemar: false)) {
            return $rechazo;
        }

        // El link lleva la firma y el nonce en la URL: que no viaje en el
        // Referer ni quede en cache.
        return response()
            ->view('portal.acceso-confirmar', ['accion' => $request->fullUrl()])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }

    /** El boton «Entrar»: consume el nonce y abre la sesion del guard `paciente`. */
    public function confirmar(Request $request, int $persona, string $nonce): RedirectResponse
    {
        if ($rechazo = $this->rechazarEnlace($request, $persona, $nonce, quemar: true)) {
            return $rechazo;
        }

        $paciente = $this->personaActiva($persona);

        if (! $paciente) {
            return $this->enlaceInvalido();
        }

        Auth::guard('paciente')->login($paciente);
        $request->session()->regenerate();

        return redirect()->route('portal.inicio');
    }

    /**
     * Cierra la sesion del portal entera. La del staff, si hay una abierta en
     * el mismo navegador, es otra cookie (ver SesionPorArea) y sigue abierta.
     */
    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('paciente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.ingresar');
    }

    /**
     * Atajo de desarrollo: entra como el paciente con ese DNI sin pasar por el
     * email. La ruta solo se registra en `local` (ver routes/web.php), asi que
     * en produccion no existe.
     */
    public function entrarLocal(Request $request, string $documento = self::DOCUMENTO_DEMO): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $persona = Persona::where('documento', $documento)
            ->whereNull('fechaHoraBajaPersona')
            ->firstOrFail();

        Auth::guard('paciente')->login($persona);
        $request->session()->regenerate();

        return redirect()->route('portal.inicio');
    }

    /**
     * La unica Persona activa con ese DNI y un email usable. Cero, mas de una
     * o sin email dan null y el pedido se responde igual que si no existiera.
     */
    private function personaParaEnlace(string $documento): ?Persona
    {
        $personas = Persona::where('documento', $documento)
            ->whereNull('fechaHoraBajaPersona')
            ->whereHas('tipoDocumento', fn ($q) => $q->where('nombreTipoDocumento', self::TIPO_DOCUMENTO))
            ->get();

        if ($personas->count() > 1) {
            // Nunca se manda un link a "alguna" de varias personas: se corrige a mano.
            Log::warning('Portal: más de una Persona activa con el mismo DNI; no se envió el enlace de acceso.', [
                'ids' => $personas->pluck('idPersona')->all(),
            ]);

            return null;
        }

        $persona = $personas->first();

        return $persona && filter_var($persona->contacto_email_direccion, FILTER_VALIDATE_EMAIL) ? $persona : null;
    }

    /**
     * Armar el link y mandar el mail es lo caro, y es lo unico que distingue un
     * DNI existente de uno que no: va despues de la respuesta. Sin cola, porque
     * en el deploy no hay worker. Un fallo de SMTP se registra y nada mas: el
     * paciente ya recibio la respuesta y no hay a quien mostrarsela.
     */
    private function enviarEnlaceDespuesDeResponder(int $idPersona): void
    {
        // `defer()` corre despues de enviar la respuesta, en el mismo proceso y
        // sin cola. Si falla, no hay respuesta que cambiar: solo se registra.
        defer(static function () use ($idPersona) {
            // Con un mailer que escribe el mail en el servidor (`log`) el link
            // quedaria legible y serviria para entrar: ni se emite ni se manda.
            // Nada del link, del nonce ni del DNI va al log.
            if (EnlaceAccesoPaciente::mailerExponeElEnlace()) {
                Log::error('Portal: el mailer por defecto («'.config('mail.default').'») deja los mails legibles en el servidor; '
                    .'no se emitió ni se envió el enlace de acceso. Configurá un mailer real (MAIL_MAILER).');

                return;
            }

            try {
                $persona = Persona::whereKey($idPersona)->whereNull('fechaHoraBajaPersona')->first();

                if ($persona) {
                    Mail::to($persona->contacto_email_direccion)->send(
                        new EnlaceAccesoPortal(EnlaceAccesoPaciente::emitir($persona), $persona->nombres),
                    );
                }
            } catch (Throwable $e) {
                Log::error('Portal: no se pudo enviar el enlace de acceso.', [
                    'idPersona' => $idPersona,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function personaActiva(int $idPersona): ?Persona
    {
        return Persona::whereKey($idPersona)->whereNull('fechaHoraBajaPersona')->first();
    }

    /**
     * Firma, vencimiento y nonce. Con `$quemar` el nonce se consume (el POST);
     * sin el solo se mira (el GET). Devuelve la redireccion con el motivo, o
     * null si el link sirve.
     */
    private function rechazarEnlace(Request $request, int $persona, string $nonce, bool $quemar): ?RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->enlaceInvalido('El enlace venció o no es válido. Pedí uno nuevo con tu DNI.');
        }

        $sirve = $quemar
            ? EnlaceAccesoPaciente::consumir($persona, $nonce)
            : EnlaceAccesoPaciente::vigente($persona, $nonce);

        return $sirve
            ? null
            : $this->enlaceInvalido('Este enlace ya se usó o fue reemplazado por uno más nuevo. Pedí uno nuevo con tu DNI.');
    }

    private function enlaceInvalido(string $mensaje = 'El enlace no es válido. Pedí uno nuevo con tu DNI.'): RedirectResponse
    {
        return redirect()->route('portal.ingresar')->with('error', $mensaje);
    }
}
