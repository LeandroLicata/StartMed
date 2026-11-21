<?php

namespace App\Http\Requests;

use App\Models\GrupoSanguineo;
use App\Models\Persona;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edicion de una Persona. Compartido entre el padron de pacientes
 * (Gestor de quirofano) y la seccion Personas (Administrador).
 */
class PersonaRequest extends FormRequest
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
            'es_cronico' => $this->boolean('es_cronico'),
            'tiene_incapacidad' => $this->boolean('tiene_incapacidad'),
            'no_acepta_donacion_sanguinea' => $this->boolean('no_acepta_donacion_sanguinea'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $persona = $this->route('paciente') ?? $this->route('persona');

        return [
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

            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', Rule::in(['M', 'F', 'X'])],
            'cuil' => ['nullable', 'string', 'max:60'],
            'grupo_sanguineo_id' => ['nullable', Rule::exists((new GrupoSanguineo)->getTable(), 'idGrupoSanguineo')],
            'contacto_celular_numero' => ['nullable', 'string', 'max:20'],
            'es_cronico' => ['boolean'],
            'tiene_incapacidad' => ['boolean'],
            'no_acepta_donacion_sanguinea' => ['boolean'],
            'observaciones' => ['nullable', 'string'],
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
            'apellidos' => 'apellidos',
            'nombres' => 'nombres',
            'apellido_materno' => 'apellido materno',
            'contacto_email_direccion' => 'mail personal',
            'telefono_numero' => 'teléfono',
            'contacto_celular_numero' => 'celular',
            'direccionPersona' => 'dirección',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'genero' => 'género',
            'cuil' => 'CUIL',
            'grupo_sanguineo_id' => 'grupo sanguíneo',
            'es_cronico' => 'paciente crónico',
            'tiene_incapacidad' => 'tiene incapacidad',
            'no_acepta_donacion_sanguinea' => 'no acepta donación sanguínea',
            'observaciones' => 'observaciones',
        ];
    }
}
