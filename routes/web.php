<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\ConsentimientoController;
use App\Http\Controllers\Admin\CuestionarioController;
use App\Http\Controllers\Admin\PrecioController;
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

        /*
         * Las plantillas de consentimiento no entran en el ABM generico: son
         * texto con vigencia, asi que se tratan por versiones.
         */
        Route::get('consentimientos', [ConsentimientoController::class, 'index'])->name('consentimientos.index');
        Route::get('consentimientos/{tipoCirugia}', [ConsentimientoController::class, 'show'])->name('consentimientos.show');
        Route::get('consentimientos/{tipoCirugia}/nueva', [ConsentimientoController::class, 'create'])->name('consentimientos.create');
        Route::post('consentimientos/{tipoCirugia}', [ConsentimientoController::class, 'store'])->name('consentimientos.store');
        Route::get('consentimientos/{tipoCirugia}/{version}/corregir', [ConsentimientoController::class, 'edit'])->name('consentimientos.edit');
        Route::put('consentimientos/{tipoCirugia}/{version}', [ConsentimientoController::class, 'update'])->name('consentimientos.update');
        Route::delete('consentimientos/{tipoCirugia}', [ConsentimientoController::class, 'destroy'])->name('consentimientos.destroy');

        /*
         * Cuestionario preanestesico: arbol de version -> preguntas -> opciones,
         * todo sobre una sola pantalla por version para no pedir JavaScript.
         */
        Route::get('cuestionario', [CuestionarioController::class, 'index'])->name('cuestionario.index');
        Route::post('cuestionario', [CuestionarioController::class, 'store'])->name('cuestionario.store');
        Route::get('cuestionario/{version}', [CuestionarioController::class, 'show'])->name('cuestionario.show');
        Route::delete('cuestionario/{version}', [CuestionarioController::class, 'destroy'])->name('cuestionario.destroy');
        Route::post('cuestionario/{version}/preguntas', [CuestionarioController::class, 'agregarPregunta'])->name('cuestionario.preguntas.store');
        Route::put('cuestionario/{version}/preguntas/{pregunta}', [CuestionarioController::class, 'actualizarPregunta'])->name('cuestionario.preguntas.update');
        Route::delete('cuestionario/{version}/preguntas/{pregunta}', [CuestionarioController::class, 'eliminarPregunta'])->name('cuestionario.preguntas.destroy');
        Route::post('cuestionario/{version}/preguntas/{pregunta}/respuestas', [CuestionarioController::class, 'agregarRespuesta'])->name('cuestionario.respuestas.store');
        Route::delete('cuestionario/{version}/preguntas/{pregunta}/respuestas/{respuesta}', [CuestionarioController::class, 'eliminarRespuesta'])->name('cuestionario.respuestas.destroy');

        /*
         * Precios por proveedor: MaterialProveedor es una relacion con
         * atributos, no un catalogo, y las unidades cuelgan de ella.
         */
        Route::get('precios', [PrecioController::class, 'index'])->name('precios.index');
        Route::get('precios/{material}', [PrecioController::class, 'show'])->name('precios.show');
        Route::post('precios/{material}/proveedores', [PrecioController::class, 'agregarProveedor'])->name('precios.proveedores.store');
        Route::put('precios/{material}/proveedores/{vinculo}', [PrecioController::class, 'actualizarProveedor'])->name('precios.proveedores.update');
        Route::delete('precios/{material}/proveedores/{vinculo}', [PrecioController::class, 'quitarProveedor'])->name('precios.proveedores.destroy');
        Route::post('precios/{material}/proveedores/{vinculo}/medidas', [PrecioController::class, 'agregarMedida'])->name('precios.medidas.store');
        Route::put('precios/{material}/proveedores/{vinculo}/medidas/{medida}', [PrecioController::class, 'alternarMedida'])->name('precios.medidas.update');
        Route::delete('precios/{material}/proveedores/{vinculo}/medidas/{medida}', [PrecioController::class, 'quitarMedida'])->name('precios.medidas.destroy');

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
