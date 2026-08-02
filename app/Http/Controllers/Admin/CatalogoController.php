<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogoRequest;
use App\Support\Auditor;
use App\Support\Catalogos;
use App\Support\FiltroBaja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ABM de todas las tablas maestras. Un solo controlador para las 26 porque
 * comparten forma; lo que cambia entre una y otra esta declarado en
 * App\Support\Catalogos y llega por el parametro {catalogo} de la URL.
 *
 * No hay borrado fisico: dar de baja es escribir la fecha de baja, y por eso
 * nunca se rompe una referencia historica.
 */
class CatalogoController extends Controller
{
    public function index(Request $request, string $catalogo): View
    {
        $config = Catalogos::buscar($catalogo);
        $estado = $request->string('estado')->toString();

        $registros = FiltroBaja::aplicar($config['modelo']::query(), $estado, $config['baja'])
            ->orderBy(Catalogos::columnaTitulo($config))
            ->paginate(25)
            ->withQueryString();

        return view('admin.catalogos.index', [
            'config' => $config,
            'registros' => $registros,
            'estado' => $estado,
        ]);
    }

    public function create(string $catalogo): View
    {
        $config = Catalogos::buscar($catalogo);

        return view('admin.catalogos.formulario', [
            'config' => $config,
            'registro' => new $config['modelo'],
            'bloqueo' => null,
        ]);
    }

    public function store(CatalogoRequest $request, string $catalogo): RedirectResponse
    {
        $config = Catalogos::buscar($catalogo);

        $modelo = $config['modelo']::create($request->validated());

        Auditor::registrar(Auditor::ALTA, $modelo, $this->comoSeLlama($config, $modelo));

        return redirect()
            ->route('admin.catalogos.index', $catalogo)
            ->with('exito', $config['singular'].' creado correctamente.');
    }

    public function edit(string $catalogo, string $registro): View
    {
        $config = Catalogos::buscar($catalogo);
        $modelo = $this->registro($config, $registro);

        return view('admin.catalogos.formulario', [
            'config' => $config,
            'registro' => $modelo,
            // Se avisa en el formulario para no dejar que escriba y recién ahí rebotar.
            'bloqueo' => Catalogos::estaProtegido($config, $modelo)
                ? $config['motivoProteccion']
                : null,
        ]);
    }

    public function update(CatalogoRequest $request, string $catalogo, string $registro): RedirectResponse
    {
        $config = Catalogos::buscar($catalogo);
        $modelo = $this->registro($config, $registro);

        if ($rechazo = $this->protegerFilaDelSistema($config, $modelo, 'editar')) {
            return $rechazo;
        }

        // La foto va antes del save: al guardar, el modelo pisa sus originales.
        $antes = Auditor::foto($modelo);
        $modelo->update($request->validated());

        if ($cambios = Auditor::diferencia($modelo, $antes)) {
            Auditor::registrar(Auditor::EDICION, $modelo, $this->comoSeLlama($config, $modelo), $cambios);
        }

        return redirect()
            ->route('admin.catalogos.index', $catalogo)
            ->with('exito', $config['singular'].' actualizado correctamente.');
    }

    public function destroy(string $catalogo, string $registro): RedirectResponse
    {
        $config = Catalogos::buscar($catalogo);
        $modelo = $this->registro($config, $registro);

        if ($rechazo = $this->protegerFilaDelSistema($config, $modelo, 'dar de baja')) {
            return $rechazo;
        }

        $modelo->update([$config['baja'] => now()]);

        Auditor::registrar(Auditor::BAJA, $modelo, $this->comoSeLlama($config, $modelo));

        return redirect()
            ->route('admin.catalogos.index', $catalogo)
            ->with('exito', $config['singular'].' dado de baja. Se puede reactivar cuando haga falta.');
    }

    public function restore(string $catalogo, string $registro): RedirectResponse
    {
        $config = Catalogos::buscar($catalogo);
        $modelo = $this->registro($config, $registro);

        $modelo->update([$config['baja'] => null]);

        Auditor::registrar(Auditor::REACTIVACION, $modelo, $this->comoSeLlama($config, $modelo));

        return redirect()
            ->route('admin.catalogos.index', ['catalogo' => $catalogo, 'estado' => FiltroBaja::BAJAS])
            ->with('exito', $config['singular'].' reactivado.');
    }

    /**
     * El modelo varia segun el catalogo, asi que el route-model binding no
     * sirve y el registro se resuelve a mano.
     *
     * @param  array<string, mixed>  $config
     */
    private function registro(array $config, string $id): Model
    {
        return $config['modelo']::findOrFail($id);
    }

    /**
     * Como se nombra el registro en la auditoría: "Tipo de cirugía «Nefrectomía»".
     *
     * @param  array<string, mixed>  $config
     */
    private function comoSeLlama(array $config, Model $registro): string
    {
        return $config['singular'].' «'.$registro->{Catalogos::columnaTitulo($config)}.'»';
    }

    /**
     * Las filas que el codigo busca por su nombre no se tocan desde el ABM:
     * renombrarlas rompe la aplicacion sin lanzar ningun error. Cuales son y
     * por que lo declara el mapa de Catalogos.
     *
     * Devuelve null si la fila no esta protegida.
     *
     * @param  array<string, mixed>  $config
     */
    private function protegerFilaDelSistema(array $config, Model $registro, string $accion): ?RedirectResponse
    {
        if (! Catalogos::estaProtegido($config, $registro)) {
            return null;
        }

        $nombre = $registro->{Catalogos::columnaTitulo($config)};

        return redirect()
            ->route('admin.catalogos.index', $config['slug'])
            // Las llaves son necesarias: sin ellas PHP absorbe el » al nombre
            // de la variable, porque acepta bytes altos en los identificadores.
            ->with('error', "«{$nombre}» no se puede {$accion}. ".$config['motivoProteccion']);
    }
}
