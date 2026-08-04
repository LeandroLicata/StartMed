<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Una opcion de respuesta de una pregunta cerrada.
 */
class RespuestaRequest extends FormRequest
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
            'nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta' => 'opción de respuesta'];
    }
}
