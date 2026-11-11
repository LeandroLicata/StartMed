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
     * Un campo que no viene queda como estaba. La casilla destildada igual
     * llega, como 0: `<x-checkbox>` manda un hidden antes del checkbox. Por eso
     * no se rellena la ausencia con false, que borraria el rechazo de
     * transfusiones ante cualquier pedido que no lo incluya.
     */
    public function rules(): array
    {
        return [
            'telefono_codigo' => ['nullable', 'regex:/^\d+$/', 'max:10'],
            'telefono_numero' => ['nullable', 'regex:/^\d+$/', 'max:20'],
            'contacto_celular_codigo' => ['nullable', 'regex:/^\d+$/', 'max:10'],
            'contacto_celular_numero' => ['nullable', 'regex:/^\d+$/', 'max:20'],
            'no_acepta_donacion_sanguinea' => ['sometimes', 'boolean'],
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
