<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Entrada y salida del portal del paciente.
 *
 * El acceso real (DNI -> link firmado de un solo uso al email de la Persona)
 * todavia no existe: `ingresar()` es la pantalla donde va a vivir. Mientras
 * tanto, `entrarLocal()` deja entrar como un paciente de la demo para poder
 * ver las pantallas del portal en local.
 */
class AccesoController extends Controller
{
    /** María García, la paciente de la demo con cirugía programada. */
    public const DOCUMENTO_DEMO = '28456789';

    public function ingresar(): View|RedirectResponse
    {
        if (Auth::guard('paciente')->check()) {
            return redirect()->route('portal.inicio');
        }

        return view('portal.ingresar');
    }

    /**
     * Solo cierra la sesion del guard `paciente`: si en el mismo navegador hay
     * una sesion de staff abierta, sigue abierta.
     */
    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('paciente')->logout();
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
}
