<?php

namespace App\Http\Requests;

use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edicion de un usuario. Un solo formulario cubre las tres tablas de la
 * cadena Persona -> Personal -> Usuario, mas los roles vigentes.
 *
 * En el alta puede recibir `persona_id` para promover una Persona existente
 * (por ejemplo, un paciente ya registrado) a usuario de staff sin duplicarla.
 * La contrasena solo se pide en el alta: para cambiarla despues esta la ruta
 * admin.usuarios.clave, con su propio ClaveRequest.
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $limpiar = static function (mixed $valor): ?string {
            $texto = trim((string) $valor);

            return $texto === '' ? null : $texto;
        };

        $this->merge([
            'documento' => $limpiar($this->input('documento')),
            'apellidos' => $limpiar($this->input('apellidos')),
            'nombres' => $limpiar($this->input('nombres')),
            'contacto_email_direccion' => $limpiar($this->input('contacto_email_direccion')),
            'telefono_numero' => $limpiar($this->input('telefono_numero')),
            'direccionPersona' => $limpiar($this->input('direccionPersona')),
            'nombreUsuario' => $limpiar($this->input('nombreUsuario')),
            'mailInstitucional' => $limpiar($this->input('mailInstitucional')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $persona = $usuario?->personal?->persona;

        if ($persona === null && $this->filled('persona_id')) {
            $persona = Persona::find($this->integer('persona_id'));
        }

        return [
            'persona_id' => ['nullable', 'integer', Rule::exists((new Persona)->getTable(), 'idPersona')],

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
            'contacto_email_direccion' => ['required', 'email', 'max:60'],
            'telefono_numero' => ['required', 'string', 'max:20'],
            'direccionPersona' => ['required', 'string', 'max:255'],

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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('usuario') !== null || ! $this->filled('persona_id')) {
                    return;
                }

                $persona = Persona::with('personal.usuario')->find($this->integer('persona_id'));

                if (! $persona) {
                    return;
                }

                if ($persona->fechaHoraBajaPersona !== null) {
                    $validator->errors()->add('persona_id', 'No podés crear un usuario para una persona dada de baja.');
                }

                if ($persona->personal?->usuario !== null) {
                    $validator->errors()->add('persona_id', 'Esta persona ya tiene un usuario asignado.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'persona_id' => 'persona',
            'tipo_documento_id' => 'tipo de documento',
            'documento' => 'documento',
            'apellidos' => 'apellido',
            'nombres' => 'nombre',
            'contacto_email_direccion' => 'mail personal',
            'telefono_numero' => 'teléfono',
            'direccionPersona' => 'dirección',
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
