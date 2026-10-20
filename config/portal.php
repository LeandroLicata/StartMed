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

];
