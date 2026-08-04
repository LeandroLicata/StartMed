<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialProveedorRequest;
use App\Models\Material;
use App\Models\MaterialProveedor as Vinculo;
use App\Models\MaterialProveedorTipoMedida as Medida;
use App\Models\Proveedor;
use App\Models\TipoMedida;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Que proveedor vende cada material, a que precio y en que unidades.
 *
 * No es un catalogo: MaterialProveedor es una relacion con atributos, y las
 * unidades cuelgan de ella con su propio rango de vigencia. Por eso queda
 * fuera del ABM generico y tiene pantalla propia.
 *
 * Esto no toca el historial de pedidos: PedidoMaterial guarda su propio
 * idMaterial, idProveedor, idTipoMedida y subtotal, sin referenciar a
 * MaterialProveedor. Un precio que cambia hoy no reescribe lo que se pidio
 * ayer.
 */
class PrecioController extends Controller
{
    public function index(): View
    {
        return view('admin.precios.index', [
            'materiales' => Material::query()
                ->whereNull('fechaBajaMaterial')
                ->withCount('materialProveedores')
                ->withMin('materialProveedores', 'precioExternoMaterialProveedor')
                ->withMax('materialProveedores', 'precioExternoMaterialProveedor')
                ->orderBy('nombreMaterial')
                ->get(),
        ]);
    }

    public function show(Material $material): View
    {
        return view('admin.precios.material', [
            'material' => $material,
            'vinculos' => $material->materialProveedores()
                ->with([
                    'proveedor',
                    // Solo las asignaciones vigentes: las cerradas son historial.
                    'materialProveedorTipoMedidas' => fn ($q) => $q
                        ->whereNull('fechaFinAsignacionMaterialTipoMedida')
                        ->with('tipoMedida'),
                ])
                ->get()
                // Ordenado en PHP: un join contra Proveedor solo para ordenar
                // ataria la consulta al casing del nombre de tabla.
                ->sortBy(fn (Vinculo $v) => $v->proveedor?->nombreProveedor)
                ->values(),
            'proveedores' => Proveedor::query()
                ->whereNull('fechaBajaProveedor')
                ->whereNotIn('idProveedor', $material->materialProveedores()->pluck('idProveedor'))
                ->orderBy('nombreProveedor')
                ->pluck('nombreProveedor', 'idProveedor')
                ->all(),
            'medidas' => TipoMedida::query()
                ->whereNull('fechaBajaTipoMedida')
                ->orderBy('nombreTipoMedida')
                ->pluck('nombreTipoMedida', 'idTipoMedida')
                ->all(),
        ]);
    }

    // --- Proveedores de un material ---

    public function agregarProveedor(MaterialProveedorRequest $request, Material $material): RedirectResponse
    {
        $datos = $request->validated();

        $vinculo = Vinculo::create([
            'idMaterial' => $material->idMaterial,
            ...$datos,
            'fechaActualizacionPrecio' => ($datos['precioExternoMaterialProveedor'] ?? null) !== null ? now() : null,
        ]);

        Auditor::registrar(Auditor::ALTA, $vinculo, $this->comoSeLlama($vinculo->load('proveedor')));

        return back()->with('exito', 'Proveedor agregado.');
    }

    public function actualizarProveedor(MaterialProveedorRequest $request, Material $material, Vinculo $vinculo): RedirectResponse
    {
        $datos = $request->validated();
        $antes = Auditor::foto($vinculo);

        $vinculo->fill($datos);

        // La fecha se mueve sola: es lo que dice el nombre de la columna, y a
        // mano nadie la va a mantener al dia.
        if ($vinculo->isDirty('precioExternoMaterialProveedor')) {
            $vinculo->fechaActualizacionPrecio = now();
        }

        $vinculo->save();

        if ($cambios = Auditor::diferencia($vinculo, $antes)) {
            Auditor::registrar(Auditor::EDICION, $vinculo, $this->comoSeLlama($vinculo), $cambios);
        }

        return back()->with('exito', 'Precio actualizado.');
    }

    /**
     * Se borra de verdad: la tabla no tiene columna de baja, y los pedidos ya
     * hechos no la referencian, asi que no se pierde historial de nada.
     */
    public function quitarProveedor(Material $material, Vinculo $vinculo): RedirectResponse
    {
        $descripcion = $this->comoSeLlama($vinculo);

        DB::transaction(function () use ($vinculo) {
            $vinculo->materialProveedorTipoMedidas()->delete();
            $vinculo->delete();
        });

        Auditor::registrar(Auditor::BAJA, $vinculo, $descripcion);

        return back()->with('exito', 'Proveedor quitado de este material.');
    }

    // --- Unidades en que lo vende ---

    public function agregarMedida(Request $request, Material $material, Vinculo $vinculo): RedirectResponse
    {
        $datos = $request->validate(
            [
                'idTipoMedida' => [
                    'required',
                    'exists:TipoMedida,idTipoMedida',
                    // Una unidad no puede estar asignada dos veces a la vez.
                    function (string $atributo, mixed $valor, callable $falla) use ($vinculo) {
                        $yaEsta = $vinculo->materialProveedorTipoMedidas()
                            ->where('idTipoMedida', $valor)
                            ->whereNull('fechaFinAsignacionMaterialTipoMedida')
                            ->exists();

                        if ($yaEsta) {
                            $falla('Ese proveedor ya vende este material en esa unidad.');
                        }
                    },
                ],
                'disponibleMaterialTipoMedida' => ['boolean'],
            ],
            attributes: ['idTipoMedida' => 'unidad'],
        );

        Medida::create([
            'idMaterialProveedor' => $vinculo->idMaterialProveedor,
            'idTipoMedida' => $datos['idTipoMedida'],
            'fechaAsignacionMaterialTipoMedida' => now(),
            'disponibleMaterialTipoMedida' => $datos['disponibleMaterialTipoMedida'] ?? true,
        ]);

        return back()->with('exito', 'Unidad agregada.');
    }

    /**
     * Marcar una unidad como disponible o no. Es distinto de quitarla: sigue
     * siendo una unidad en la que ese proveedor vende, hoy sin stock.
     */
    public function alternarMedida(Material $material, Vinculo $vinculo, Medida $medida): RedirectResponse
    {
        $medida->update([
            'disponibleMaterialTipoMedida' => ! $medida->disponibleMaterialTipoMedida,
        ]);

        return back()->with('exito', $medida->disponibleMaterialTipoMedida
            ? 'Unidad marcada como disponible.'
            : 'Unidad marcada como no disponible.');
    }

    /**
     * Cerrar la asignacion en vez de borrarla: la tabla tiene rango de
     * vigencia, asi que queda registrado hasta cuando se vendio asi.
     */
    public function quitarMedida(Material $material, Vinculo $vinculo, Medida $medida): RedirectResponse
    {
        $medida->update(['fechaFinAsignacionMaterialTipoMedida' => now()]);

        return back()->with('exito', 'Unidad dada de baja. Queda en el historial.');
    }

    private function comoSeLlama(Vinculo $vinculo): string
    {
        return 'Precio de «'.$vinculo->material->nombreMaterial.'» en '
            .($vinculo->proveedor?->nombreProveedor ?? 'proveedor desconocido');
    }
}
