<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de contrasena hecho por un administrador.
 *
 * No hay recuperacion por mail (config/auth.php deja 'passwords' vacio a
 * proposito: Usuario no tiene columna de email), asi que este es el unico
 * camino para reponer una clave.
 */
class ClaveRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'contraseña'];
    }
}
