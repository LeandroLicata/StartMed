<?php

namespace App\Http\Requests;

use App\Models\MaterialProveedor;
use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edicion de lo que un proveedor cobra por un material.
 */
class MaterialProveedorRequest extends FormRequest
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
            // El proveedor identifica el vinculo: al editar ya no se cambia,
            // se quita este y se agrega otro.
            ...$this->route('vinculo') ? [] : [
                'idProveedor' => [
                    'required',
                    Rule::exists((new Proveedor)->getTable(), 'idProveedor'),
                    Rule::unique((new MaterialProveedor)->getTable(), 'idProveedor')
                        ->where('idMaterial', $this->route('material')->idMaterial),
                ],
            ],
            'codExternoMaterialProveedor' => ['nullable', 'string', 'max:60'],
            // decimal(12,2): doce digitos en total, dos decimales.
            'precioExternoMaterialProveedor' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'idProveedor' => 'proveedor',
            'codExternoMaterialProveedor' => 'código del proveedor',
            'precioExternoMaterialProveedor' => 'precio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idProveedor.unique' => 'Ese proveedor ya está cargado para este material.',
        ];
    }
}
