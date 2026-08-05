<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialProveedorRequest;
use App\Http\Requests\MedidaProveedorRequest;
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
 * Que proveedor vende cada material, en que unidades y a que precio.
 *
 * No es un catalogo: MaterialProveedor es una relacion con atributos, y las
 * unidades cuelgan de ella con su propio rango de vigencia. Por eso queda
 * fuera del ABM generico y tiene pantalla propia.
 *
 * El precio no cuelga del proveedor sino de la unidad: un implante de 0,5 m y
 * uno de 1 m son dos articulos facturables distintos del mismo proveedor. El
 * codigo externo acompana al precio por la misma razon.
 *
 * Esto no toca el historial de pedidos: PedidoMaterial guarda su propio
 * idMaterial, idProveedor, idTipoMedida, unitario y subtotal, sin referenciar a
 * MaterialProveedor. Un precio que cambia hoy no reescribe lo que se pidio
 * ayer.
 */
class PrecioController extends Controller
{
    public function index(Request $request): View
    {
        // El precio vive dos niveles abajo, asi que el minimo y el maximo
        // salen de la relacion through, contando solo asignaciones vigentes:
        // una unidad cerrada es historial y no deberia fijar el minimo.
        $vigentes = fn ($q) => $q->whereNull('fechaFinAsignacionMaterialTipoMedida');

        $busqueda = trim($request->string('q')->toString());

        return view('admin.precios.index', [
            'materiales' => Material::query()
                ->whereNull('fechaBajaMaterial')
                // Con el listado paginado, sin buscador encontrar un material
                // seria pasar paginas hasta dar con la letra.
                ->when($busqueda !== '', fn ($q) => $q->where(
                    fn ($q) => $q
                        ->where('nombreMaterial', 'like', "%{$busqueda}%")
                        ->orWhere('codMaterial', 'like', "%{$busqueda}%")
                ))
                ->withCount('materialProveedores')
                ->withMin(
                    ['materialProveedorTipoMedidas as precioMinimo' => $vigentes],
                    'precioExternoMaterialProveedorTipoMedida'
                )
                ->withMax(
                    ['materialProveedorTipoMedidas as precioMaximo' => $vigentes],
                    'precioExternoMaterialProveedorTipoMedida'
                )
                ->orderBy('nombreMaterial')
                ->paginate(25)
                ->withQueryString(),
            'filtros' => ['q' => $busqueda],
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
        $vinculo = Vinculo::create([
            'idMaterial' => $material->idMaterial,
            ...$request->validated(),
        ]);

        Auditor::registrar(Auditor::ALTA, $vinculo, $this->comoSeLlama($vinculo->load('proveedor')));

        return back()->with('exito', 'Proveedor agregado. Cargale en qué unidades lo vende.');
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

    // --- Unidades en que lo vende, con su precio ---

    public function agregarMedida(MedidaProveedorRequest $request, Material $material, Vinculo $vinculo): RedirectResponse
    {
        $datos = $request->validated();

        $medida = Medida::create([
            'idMaterialProveedor' => $vinculo->idMaterialProveedor,
            ...$datos,
            'fechaAsignacionMaterialTipoMedida' => now(),
            'disponibleMaterialTipoMedida' => true,
            'fechaActualizacionPrecioMaterialProveedorTipoMedida' => ($datos['precioExternoMaterialProveedorTipoMedida'] ?? null) !== null ? now() : null,
        ]);

        Auditor::registrar(Auditor::ALTA, $medida, $this->comoSeLlamaLaMedida($medida->load('tipoMedida'), $vinculo));

        return back()->with('exito', 'Unidad agregada.');
    }

    public function actualizarMedida(MedidaProveedorRequest $request, Material $material, Vinculo $vinculo, Medida $medida): RedirectResponse
    {
        $antes = Auditor::foto($medida);

        $medida->fill($request->validated());

        // La fecha se mueve sola: es lo que dice el nombre de la columna, y a
        // mano nadie la va a mantener al dia.
        $cambioElPrecio = $medida->isDirty('precioExternoMaterialProveedorTipoMedida');

        if ($cambioElPrecio) {
            $medida->fechaActualizacionPrecioMaterialProveedorTipoMedida = now();
        }

        $medida->save();

        if ($cambios = Auditor::diferencia($medida, $antes)) {
            Auditor::registrar(Auditor::EDICION, $medida, $this->comoSeLlamaLaMedida($medida, $vinculo), $cambios);
        }

        // El aviso dice lo que de verdad paso: la fila tiene dos campos y
        // "Precio actualizado" despues de corregir un codigo es mentira.
        return match (true) {
            $cambios === [] => back()->with('info', 'No había nada que cambiar.'),
            $cambioElPrecio => back()->with('exito', 'Precio actualizado.'),
            default => back()->with('exito', 'Código actualizado.'),
        };
    }

    /**
     * Marcar una unidad como disponible o no. Es distinto de quitarla: sigue
     * siendo una unidad en la que ese proveedor vende, hoy sin stock.
     */
    public function alternarMedida(Material $material, Vinculo $vinculo, Medida $medida): RedirectResponse
    {
        $antes = Auditor::foto($medida);

        $medida->update([
            'disponibleMaterialTipoMedida' => ! $medida->disponibleMaterialTipoMedida,
        ]);

        Auditor::registrar(
            Auditor::EDICION,
            $medida,
            $this->comoSeLlamaLaMedida($medida, $vinculo),
            Auditor::diferencia($medida, $antes),
        );

        return back()->with('exito', $medida->disponibleMaterialTipoMedida
            ? 'Unidad marcada como disponible.'
            : 'Unidad marcada como no disponible.');
    }

    /**
     * Cerrar la asignacion en vez de borrarla: la tabla tiene rango de
     * vigencia, asi que queda registrado hasta cuando se vendio asi, y a que
     * precio.
     */
    public function quitarMedida(Material $material, Vinculo $vinculo, Medida $medida): RedirectResponse
    {
        $descripcion = $this->comoSeLlamaLaMedida($medida->load('tipoMedida'), $vinculo);

        $medida->update(['fechaFinAsignacionMaterialTipoMedida' => now()]);

        Auditor::registrar(Auditor::BAJA, $medida, $descripcion);

        return back()->with('exito', 'Unidad dada de baja. Queda en el historial.');
    }

    private function comoSeLlama(Vinculo $vinculo): string
    {
        return '«'.$vinculo->material->nombreMaterial.'» en '
            .($vinculo->proveedor?->nombreProveedor ?? 'proveedor desconocido');
    }

    /**
     * Nombra la unidad, no solo el material: si no, tres precios del mismo
     * material aparecen como tres renglones identicos en la auditoria.
     */
    private function comoSeLlamaLaMedida(Medida $medida, Vinculo $vinculo): string
    {
        return 'Precio de «'.$vinculo->material->nombreMaterial.'» por '
            .($medida->tipoMedida?->nombreTipoMedida ?? 'unidad desconocida').' en '
            .($vinculo->proveedor?->nombreProveedor ?? 'proveedor desconocido');
    }
}
