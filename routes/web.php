<?php

use App\Http\Controllers\AnestesistaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CirugiaController;
use App\Http\Controllers\CirujanoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DireccionController;
use App\Http\Controllers\PortalPacienteController;
use Illuminate\Support\Facades\Route;

/*
 * Punto de entrada: cada rol aterriza en su propio panel. Los usuarios sin
 * ningun rol asignado ven una pantalla que se los explica, en vez de un 403.
 */
Route::get('/', function () {
    $usuario = auth()->user();

    if (! $usuario) {
        return redirect()->route('login');
    }

    return ($ruta = $usuario->rutaInicial())
        ? redirect()->route($ruta)
        : response()->view('sin-acceso');
})->name('inicio');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrarFormulario'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('rol:Gestor de quirófano,Dirección médica')
        ->name('dashboard');

    Route::get('/cirujano', CirujanoController::class)
        ->middleware('rol:Cirujano')
        ->name('cirujano');

    Route::get('/anestesista', AnestesistaController::class)
        ->middleware('rol:Anestesista')
        ->name('anestesista');

    Route::get('/direccion', DireccionController::class)
        ->middleware('rol:Dirección médica')
        ->name('direccion');

    Route::get('/cirugias/{cirugia}', [CirugiaController::class, 'show'])
        ->name('cirugias.show');

    /*
     * Vista previa del portal del paciente. El paciente todavia no puede
     * entrar por su cuenta: `Usuario` cuelga de `Personal`, y un paciente es
     * una `Persona` sin legajo. Hasta que se resuelva el acceso, la pantalla
     * la abre el equipo desde el expediente.
     */
    Route::get('/cirugias/{cirugia}/portal-paciente', PortalPacienteController::class)
        ->name('cirugias.portal');
});
