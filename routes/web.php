<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\UsuarioController;
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

    /*
     * Administracion: la carga de los datos maestros que consumen el resto de
     * las secciones, y el alta de usuarios. Un solo controlador atiende las 26
     * tablas maestras; {catalogo} es el slug declarado en App\Support\Catalogos.
     */
    Route::middleware('rol:Administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminController::class)->name('inicio');
        Route::get('auditoria', AuditoriaController::class)->name('auditoria');

        Route::get('catalogos/{catalogo}', [CatalogoController::class, 'index'])->name('catalogos.index');
        Route::get('catalogos/{catalogo}/nuevo', [CatalogoController::class, 'create'])->name('catalogos.create');
        Route::post('catalogos/{catalogo}', [CatalogoController::class, 'store'])->name('catalogos.store');
        Route::get('catalogos/{catalogo}/{registro}/editar', [CatalogoController::class, 'edit'])->name('catalogos.edit');
        Route::put('catalogos/{catalogo}/{registro}', [CatalogoController::class, 'update'])->name('catalogos.update');
        Route::delete('catalogos/{catalogo}/{registro}', [CatalogoController::class, 'destroy'])->name('catalogos.destroy');
        Route::post('catalogos/{catalogo}/{registro}/reactivar', [CatalogoController::class, 'restore'])->name('catalogos.restore');

        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/nuevo', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::put('usuarios/{usuario}/clave', [UsuarioController::class, 'clave'])->name('usuarios.clave');
        Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
        Route::post('usuarios/{usuario}/reactivar', [UsuarioController::class, 'restore'])->name('usuarios.restore');
    });
});
