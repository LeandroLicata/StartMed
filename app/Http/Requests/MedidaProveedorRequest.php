<?php

namespace App\Http\Requests;

use App\Models\TipoMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edicion de una unidad de venta: en que presentacion vende un
 * proveedor un material, con que codigo la factura y a que precio.
 */
class MedidaProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // La unidad identifica la fila: al editar ya no se cambia, se
            // cierra esta asignacion y se agrega otra.
            ...$this->route('medida') ? [] : [
                'idTipoMedida' => [
                    'required',
                    Rule::exists((new TipoMedida)->getTable(), 'idTipoMedida'),
                    function (string $atributo, mixed $valor, callable $falla) {
                        $yaEsta = $this->route('vinculo')
                            ->materialProveedorTipoMedidas()
                            ->where('idTipoMedida', $valor)
                            ->whereNull('fechaFinAsignacionMaterialTipoMedida')
                            ->exists();

                        if ($yaEsta) {
                            $falla('Ese proveedor ya vende este material en esa unidad.');
                        }
                    },
                ],
            ],
            'codExternoMaterialProveedorTipoMedida' => ['nullable', 'string', 'max:60'],
            // decimal(12,2): doce digitos en total, dos decimales.
            'precioExternoMaterialProveedorTipoMedida' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'idTipoMedida' => 'unidad',
            'codExternoMaterialProveedorTipoMedida' => 'código del proveedor',
            'precioExternoMaterialProveedorTipoMedida' => 'precio',
        ];
    }
}
