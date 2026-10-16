<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarAccesoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El DNI se tipea con puntos o espacios ("28.456.789"). Se normaliza antes
     * de validar para que la clave del limite por DNI y la busqueda en la base
     * partan del mismo valor.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'documento' => preg_replace('/\D/', '', (string) $this->input('documento')),
        ]);
    }

    public function rules(): array
    {
        return [
            'documento' => ['required', 'digits_between:6,9'],
        ];
    }

    public function attributes(): array
    {
        return ['documento' => 'DNI'];
    }
}
