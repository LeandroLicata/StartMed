<?php

/*
 * APP_LOCALE=es y no hay traducciones publicadas, asi que sin este archivo los
 * errores de validacion salen como la clave cruda ("validation.required").
 * Solo estan las reglas que usa la aplicacion; el resto cae en el ingles del
 * framework, que es preferible a la clave.
 *
 * `:attribute` lo resuelve cada FormRequest con su metodo attributes().
 */

return [
    'accepted' => 'Tenés que aceptar :attribute.',
    'array' => 'El campo :attribute tiene que ser una lista.',
    'boolean' => 'El campo :attribute tiene que ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'different' => 'Los campos :attribute y :other tienen que ser distintos.',
    'email' => 'El campo :attribute tiene que ser una dirección de correo válida.',
    'exists' => 'El valor elegido en :attribute no es válido.',
    'in' => 'El valor elegido en :attribute no es válido.',
    'integer' => 'El campo :attribute tiene que ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El campo :attribute no puede pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute tiene que tener al menos :min elementos.',
        'file' => 'El campo :attribute tiene que pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute tiene que ser al menos :min.',
        'string' => 'El campo :attribute tiene que tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute tiene que ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'string' => 'El campo :attribute tiene que ser texto.',
    'unique' => 'Ese :attribute ya está en uso.',

    'attributes' => [],
];
