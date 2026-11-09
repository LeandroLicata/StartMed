<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarCambioEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_nuevo' => [
                'required',
                'email',
                'max:60',
                // No tiene sentido "cambiar" al mismo email que ya tiene.
                Rule::notIn([$this->user('paciente')->contacto_email_direccion]),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'email_nuevo' => 'email nuevo',
        ];
    }

    public function messages(): array
    {
        return [
            'email_nuevo.not_in' => 'Ese ya es tu email actual.',
        ];
    }
}
