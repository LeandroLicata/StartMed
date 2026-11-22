<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La conversacion que manda el widget. El historial vive en el navegador y
 * viaja entero en cada pedido; aca se le ponen los topes (cantidad de turnos
 * y largo de lo que escribe el usuario) para que no se pueda inflar el
 * prompt.
 */
class AsistenteRequest extends FormRequest
{
    /**
     * Las respuestas del asistente vuelven en el historial y pueden ser mas
     * largas que lo que se le deja escribir al usuario.
     */
    private const MAX_CARACTERES_RESPUESTA = 6000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mensajes' => ['required', 'array', 'min:1', 'max:'.config('asistente.max_mensajes')],
            'mensajes.*' => ['required', 'array:rol,texto'],
            'mensajes.*.rol' => ['required', Rule::in(['usuario', 'asistente'])],
            'mensajes.*.texto' => ['required', 'string', 'max:'.self::MAX_CARACTERES_RESPUESTA],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $mensajes = $this->input('mensajes');

            // La conversacion arranca y termina en el usuario: lo ultimo es la
            // pregunta que hay que responder.
            if (head($mensajes)['rol'] !== 'usuario' || last($mensajes)['rol'] !== 'usuario') {
                $validator->errors()->add('mensajes', 'La conversación tiene que empezar y terminar con un mensaje tuyo.');
            }

            $maximo = (int) config('asistente.max_caracteres');

            foreach ($mensajes as $i => $mensaje) {
                if ($mensaje['rol'] === 'usuario' && mb_strlen($mensaje['texto']) > $maximo) {
                    $validator->errors()->add("mensajes.$i.texto", "Cada mensaje puede tener hasta $maximo caracteres.");
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mensajes' => 'conversación',
            'mensajes.*.rol' => 'rol',
            'mensajes.*.texto' => 'mensaje',
        ];
    }

    /**
     * @return list<array{rol: 'usuario'|'asistente', texto: string}>
     */
    public function mensajes(): array
    {
        return array_map(
            fn (array $mensaje) => ['rol' => $mensaje['rol'], 'texto' => $mensaje['texto']],
            array_values($this->validated('mensajes')),
        );
    }
}
