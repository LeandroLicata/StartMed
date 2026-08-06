<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Una pregunta del cuestionario preanestesico.
 */
class PreguntaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => ['required', 'string', 'max:255'],
            'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta' => 'pregunta',
            'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => 'tipo de respuesta',
        ];
    }
}
