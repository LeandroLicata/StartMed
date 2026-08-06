<?php

namespace App\Http\Requests;

use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edicion de un usuario. Un solo formulario cubre las tres tablas de la
 * cadena Persona -> Personal -> Usuario, mas los roles vigentes.
 *
 * La contrasena solo se pide en el alta: para cambiarla despues esta la ruta
 * admin.usuarios.clave, con su propio ClaveRequest.
 */
class UsuarioRequest extends FormRequest
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
        $usuario = $this->route('usuario');
        $persona = $usuario?->personal?->persona;

        return [
            // --- Persona ---
            'tipo_documento_id' => ['required', Rule::exists((new TipoDocumento)->getTable(), 'idTipoDocumento')],
            'documento' => [
                'required', 'string', 'max:60',
                // El documento se repite entre tipos distintos, no dentro del mismo.
                Rule::unique((new Persona)->getTable(), 'documento')
                    ->where('tipo_documento_id', $this->input('tipo_documento_id'))
                    ->ignore($persona?->idPersona, 'idPersona'),
            ],
            'apellidos' => ['required', 'string', 'max:120'],
            'nombres' => ['required', 'string', 'max:120'],

            // --- Personal ---
            'legajoPersonal' => ['nullable', 'string', 'max:60'],
            'matriculaProvincial' => ['nullable', 'string', 'max:60'],
            'matriculaNacional' => ['nullable', 'string', 'max:60'],
            'mailInstitucional' => ['nullable', 'email', 'max:120'],

            // --- Usuario ---
            'nombreUsuario' => [
                'required', 'string', 'max:120',
                Rule::unique((new Usuario)->getTable(), 'nombreUsuario')
                    ->ignore($usuario?->idUsuario, 'idUsuario'),
            ],
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],

            // --- Roles vigentes ---
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::exists((new Rol)->getTable(), 'idRol')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_documento_id' => 'tipo de documento',
            'documento' => 'documento',
            'apellidos' => 'apellido',
            'nombres' => 'nombre',
            'legajoPersonal' => 'legajo',
            'matriculaProvincial' => 'matrícula provincial',
            'matriculaNacional' => 'matrícula nacional',
            'mailInstitucional' => 'mail institucional',
            'nombreUsuario' => 'nombre de usuario',
            'password' => 'contraseña',
            'roles' => 'roles',
        ];
    }
}
