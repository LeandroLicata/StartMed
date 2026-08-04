<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\ConsentimientoController;
use App\Http\Controllers\Admin\CuestionarioController;
use App\Http\Controllers\Admin\PrecioController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AnestesistaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CirugiaController;
use App\Http\Controllers\CirugiaCreacionController;
use App\Http\Controllers\CirujanoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DireccionController;
use App\Http\Controllers\PacienteController;
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

    Route::resource('pacientes', PacienteController::class)
        ->only(['index', 'show'])
        ->middleware('rol:Gestor de quirófano,Cirujano,Anestesista');

    Route::get('/cirujano', CirujanoController::class)
        ->middleware('rol:Cirujano')
        ->name('cirujano');

    Route::get('/cirujano/agenda', [CirujanoController::class, 'agenda'])
        ->middleware('rol:Cirujano')
        ->name('cirujano.agenda');

    Route::get('/anestesista', [AnestesistaController::class, 'index'])
        ->middleware('rol:Anestesista')
        ->name('anestesista');

    /*
     * CRUD de la evaluación pre-anestésica. La cirugía se resuelve por ruta y
     * el controlador valida que sea del anestesista que entra.
     */
    Route::middleware('rol:Anestesista')->prefix('anestesista')->name('anestesista.')->group(function () {
        Route::get('/{cirugia}/evaluar', [AnestesistaController::class, 'evaluar'])->name('evaluar');
        Route::post('/{cirugia}/evaluacion', [AnestesistaController::class, 'store'])->name('store');
        Route::get('/{cirugia}/evaluacion/editar', [AnestesistaController::class, 'editar'])->name('editar');
        Route::put('/{cirugia}/evaluacion', [AnestesistaController::class, 'update'])->name('update');
        Route::delete('/{cirugia}/evaluacion', [AnestesistaController::class, 'destroy'])->name('destroy');
    });

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

        Route::patch('/cirugias/{cirugia}/requerimientos', [CirugiaController::class, 'agregarRequerimiento'])
            ->name('cirugias.requerimientos.agregar');

        Route::patch('/cirugias/{cirugia}/cancelar', [CirugiaController::class, 'cancelar'])
            ->name('cirugias.cancelar');

        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda');
        Route::get('/agenda/{fecha}', [AgendaController::class, 'dia'])
            ->where('fecha', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->name('agenda.dia');
    });

    Route::middleware('rol:Gestor de quirófano,Cirujano')->group(function () {
        Route::patch('/cirugias/{cirugia}/hisopado', [CirugiaController::class, 'actualizarHisopado'])
            ->name('cirugias.hisopado.actualizar');

        Route::patch('/cirugias/{cirugia}/hisopado/estado', [CirugiaController::class, 'actualizarEstadoHisopado'])
            ->name('cirugias.hisopado.estado');

        Route::post('/cirugias/{cirugia}/profilaxis', [CirugiaController::class, 'agregarProfilaxis'])
            ->name('cirugias.profilaxis.store');

        Route::patch('/cirugias/{cirugia}/autorizacion/estado', [CirugiaController::class, 'actualizarEstadoAutorizacion'])
            ->name('cirugias.autorizacion.estado');

        Route::post('/cirugias/{cirugia}/preparacion', [CirugiaController::class, 'guardarPreparacion'])
            ->name('cirugias.preparacion.guardar');

        Route::get('/cirugias/{cirugia}/api/personal-disponible', [CirugiaController::class, 'personalDisponible'])
            ->name('cirugias.personal.disponible');

        Route::patch('/cirugias/{cirugia}/personal', [CirugiaController::class, 'reasignarPersonal'])
            ->name('cirugias.personal.reasignar');
    });

    Route::middleware('rol:Gestor de quirófano,Cirujano,Anestesista')->group(function () {
        Route::post('/cirugias/{cirugia}/estudios', [CirugiaController::class, 'agregarEstudio'])
            ->name('cirugias.estudios.store');

        Route::patch('/cirugias/{cirugia}/estudios/{estudio}', [CirugiaController::class, 'actualizarEstudio'])
            ->name('cirugias.estudios.update');

        Route::post('/cirugias/{cirugia}/hemoderivados', [CirugiaController::class, 'storePedidoHemoderivado'])
            ->name('cirugias.hemoderivados.store');

        Route::patch('/cirugias/{cirugia}/hemoderivados/{componente}/estado', [CirugiaController::class, 'actualizarEstadoComponenteHemoderivado'])
            ->name('cirugias.hemoderivados.componente.estado');

        Route::get('/cirugias/api/materiales/buscar', [CirugiaController::class, 'buscarMateriales'])
            ->name('cirugias.materiales.buscar');

        Route::get('/cirugias/api/materiales/{material}/proveedores', [CirugiaController::class, 'proveedoresMaterial'])
            ->name('cirugias.materiales.proveedores');

        Route::post('/cirugias/{cirugia}/materiales', [CirugiaController::class, 'storePedidoMaterial'])
            ->name('cirugias.materiales.store');

        Route::put('/cirugias/{cirugia}/materiales/{pedido}', [CirugiaController::class, 'updatePedidoMaterial'])
            ->name('cirugias.materiales.update');

        Route::delete('/cirugias/{cirugia}/materiales/{pedido}', [CirugiaController::class, 'destroyPedidoMaterial'])
            ->name('cirugias.materiales.destroy');
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
