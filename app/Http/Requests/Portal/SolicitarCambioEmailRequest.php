<?php

namespace App\Http\Requests\Portal;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SolicitarCambioEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actual = (string) $this->user('paciente')->contacto_email_direccion;

        return [
            'email_nuevo' => [
                'required',
                'email',
                'max:60',
                // No tiene sentido "cambiar" al mismo email que ya tiene. Sin
                // distinguir mayusculas: Maria@x.com y maria@x.com son el mismo
                // buzon en la practica, y un notIn los dejaba pasar.
                function (string $atributo, mixed $valor, Closure $fallar) use ($actual) {
                    if ($actual !== '' && mb_strtolower((string) $valor) === mb_strtolower($actual)) {
                        $fallar('Ese ya es tu email actual.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'email_nuevo' => 'email nuevo',
        ];
    }
}
