<?php

namespace App\Http\Requests;

use App\Models\MaterialProveedor;
use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta del vinculo entre un material y un proveedor.
 *
 * El vinculo no tiene mas datos que eso: el codigo y el precio dependen de la
 * unidad en que se venda, asi que viven en MedidaProveedorRequest.
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
            'idProveedor' => [
                'required',
                Rule::exists((new Proveedor)->getTable(), 'idProveedor'),
                Rule::unique((new MaterialProveedor)->getTable(), 'idProveedor')
                    ->where('idMaterial', $this->route('material')->idMaterial),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'idProveedor' => 'proveedor',
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
