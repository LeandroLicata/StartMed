<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Perfil del paciente: datos personales y obras sociales.
 *
 * Esqueleto del contrato del portal; el contenido real se completa despues.
 */
class PerfilController extends Controller
{
    public function show(): View
    {
        return view('portal.pendiente', ['titulo' => 'Mi perfil']);
    }
}
