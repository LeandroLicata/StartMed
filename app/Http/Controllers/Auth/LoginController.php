<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function mostrarFormulario(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'nombreUsuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // El EloquentUserProvider ignora la clave `password` al buscar el
        // registro y la compara contra Usuario::getAuthPassword().
        if (! Auth::attempt($credenciales)) {
            throw ValidationException::withMessages([
                'nombreUsuario' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        if (Auth::user()->estaDadoDeBaja()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'nombreUsuario' => 'Este usuario esta dado de baja.',
            ]);
        }

        $request->session()->regenerate();

        // La ruta inicial depende del rol, la resuelve el punto de entrada.
        return redirect()->intended(route('inicio'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
