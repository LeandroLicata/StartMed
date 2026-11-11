<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarDatosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Una casilla destildada no se manda en el formulario.
     * Acá la convertimos en true/false para que siempre llegue.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'no_acepta_donacion_sanguinea' => $this->boolean('no_acepta_donacion_sanguinea'),
        ]);
    }

    public function rules(): array
    {
        return [
            'telefono_codigo' => ['nullable', 'regex:/^\d+$/', 'max:10'],
            'telefono_numero' => ['nullable', 'regex:/^\d+$/', 'max:20'],
            'contacto_celular_codigo' => ['nullable', 'regex:/^\d+$/', 'max:10'],
            'contacto_celular_numero' => ['nullable', 'regex:/^\d+$/', 'max:20'],
            'no_acepta_donacion_sanguinea' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'telefono_codigo' => 'código de área del teléfono',
            'telefono_numero' => 'número de teléfono',
            'contacto_celular_codigo' => 'código de área del celular',
            'contacto_celular_numero' => 'número de celular',
        ];
    }

    public function messages(): array
    {
        return [
            'regex' => 'El campo :attribute solo puede tener números, sin espacios ni guiones.',
        ];
    }
}
