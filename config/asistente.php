<?php

/*
 * Asistente de uso: el chat que le explica a cada rol como se usa StartMed.
 * Ver App\Support\Asistente\InstruccionesAsistente.
 */

return [

    /*
     * Clave de la API de Gemini. Vacia a proposito en .env.example: sin clave
     * el widget no se dibuja y el endpoint responde 404, asi que un clon nuevo
     * funciona entero sin abrir una cuenta. Se saca en
     * https://aistudio.google.com/apikey.
     *
     * Ojo con el free tier: Google puede usar lo que se le manda para mejorar
     * sus productos. Por eso el asistente no recibe ningun dato del sistema,
     * solo la guia de uso y lo que el usuario escribe, y el widget le pide no
     * escribir datos de pacientes.
     */
    'clave' => env('GEMINI_API_KEY'),

    /*
     * Flash-Lite alcanza: la guia entra completa en el prompt y la tarea es
     * responder sobre ella. Al cambiar de modelo, revisar el nivel de
     * razonamiento: gemini-3.5-flash-lite acepta MINIMAL, pero
     * gemini-3.7-flash solo LOW/MEDIUM/HIGH y rechaza MINIMAL con un 400.
     */
    'modelo' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    'nivel_razonamiento' => env('GEMINI_THINKING_LEVEL', 'MINIMAL'),

    // Baja para que se pegue a la guia en vez de improvisar.
    'temperatura' => 0.3,
    'max_tokens' => 700,
    'timeout' => 30,

    /*
     * Tope de la conversacion que manda el navegador. El historial vive en el
     * cliente: el servidor no guarda nada de lo que se escribe.
     */
    'max_mensajes' => 20,
    'max_caracteres' => 500,

    /*
     * Por usuario (o por paciente), no por IP: todos los pedidos vienen con
     * sesion. El techo real de gasto lo pone el free tier, que devuelve 429
     * en vez de facturar.
     */
    'por_minuto' => 10,
    'por_dia' => 150,

];
