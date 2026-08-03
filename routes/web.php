<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AnestesistaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CirugiaController;
use App\Http\Controllers\CirugiaCreacionController;
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

    // Alta de una cirugia nueva: buscar/dar de alta al paciente por DNI y
    // completar quirofano, equipo y cobertura. Exclusivo del gestor. Va antes
    // de '/cirugias/{cirugia}' para que 'nueva' no se interprete como un id.
    Route::middleware('rol:Gestor de quirófano')->group(function () {
        Route::get('/cirugias', [CirugiaController::class, 'index'])->name('cirugias.index');

        Route::get('/cirugias/nueva', [CirugiaCreacionController::class, 'buscar'])
            ->name('cirugias.crear');
        Route::post('/cirugias/nueva/paciente', [CirugiaCreacionController::class, 'crearPaciente'])
            ->name('cirugias.crear.paciente');
        Route::post('/cirugias/nueva/comprobar', [CirugiaCreacionController::class, 'comprobar'])
            ->name('cirugias.crear.comprobar');
        Route::post('/cirugias', [CirugiaCreacionController::class, 'store'])
            ->name('cirugias.store');

        Route::post('/cirugias/{cirugia}/reprogramar', [CirugiaController::class, 'reprogramar'])
            ->name('cirugias.reprogramar');

        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda');
        Route::get('/agenda/{fecha}', [AgendaController::class, 'dia'])
            ->where('fecha', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->name('agenda.dia');
    });

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
