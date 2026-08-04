<?php

namespace App\Support;

use Illuminate\Session\Store;

final class PortalPacienteMock
{
    /** @return array<string, mixed> */
    public static function datos(Store $session): array
    {
        $estado = $session->get('portal_paciente', []);

        return [
            'paciente' => ['nombre' => 'García, María', 'iniciales' => 'MG', 'documento' => '28.456.789'],
            'cirugia' => [
                'procedimiento' => 'Colecistectomía laparoscópica',
                'fecha' => 'Lunes 16 de junio',
                'hora' => '07:30',
                'llegada' => '07:00',
                'cirujano' => 'Dr. Pérez',
                'anestesista' => 'Dr. Ramos',
                'direccion' => 'Av. Rivadavia 2340, Santa Fe',
                'cobertura' => 'OSDE · Autorizada',
            ],
            'estado' => $estado,
            'pasos' => [
                ['resumen', 'Resumen', 'home', true],
                ['turnos', 'Turnos', 'event', true],
                ['estudios', 'Estudios', 'description', true],
                ['preanestesica', 'Preanestésica', 'assignment', (bool) ($estado['cuestionario'] ?? false)],
                ['preparacion', 'Preparación', 'no_food', true],
                ['consentimiento', 'Consentimiento', 'draw', (bool) ($estado['firmar'] ?? false)],
            ],
        ];
    }
}
