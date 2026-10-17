<?php

namespace App\Http\Requests\Portal;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgregarObraSocialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idPlan' => ['required', Rule::in(Plan::disponiblesParaPaciente()->keys())],
            'nroBeneficiario' => ['required', 'string', 'max:60'],
        ];
    }

    public function attributes(): array
    {
        return [
            'idPlan' => 'obra social y plan',
            'nroBeneficiario' => 'número de afiliado',
        ];
    }
}
