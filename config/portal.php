<?php

/*
 * Portal del paciente: datos del hospital que el paciente ve y que no viven en
 * ninguna tabla. Cada uno es opcional: si no esta cargado, la pantalla no
 * muestra la fila en vez de inventar un valor.
 */

return [

    /*
     * Direccion del hospital, tal como se le muestra al paciente en el detalle
     * de su cirugia. No hay tabla de sedes y Persona no tiene domicilio, asi
     * que sale de aca.
     */
    'direccion_hospital' => env('PORTAL_DIRECCION_HOSPITAL'),

    /*
     * Numero de WhatsApp del hospital (solo digitos con codigo de pais,
     * por ejemplo 5492994000000). Opcional.
     */
    'whatsapp_hospital' => env('PORTAL_WHATSAPP_HOSPITAL'),

    /*
     * Telefono del hospital, tal como se muestra al paciente. Opcional.
     */
    'telefono_hospital' => env('PORTAL_TELEFONO_HOSPITAL'),

    /*
     * Email de contacto del hospital para consultas del paciente. Opcional.
     */
    'email_hospital' => env('PORTAL_EMAIL_HOSPITAL'),

];
