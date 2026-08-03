<?php

namespace App\Support;

use App\Models\Cirugia;

/**
 * Los marcadores de las plantillas de consentimiento y como se resuelven.
 *
 * Vivia solo en ExpedienteSeeder. Al aparecer la pantalla de administracion,
 * que tiene que previsualizar lo mismo, se trae aca para que no haya dos
 * implementaciones que se bifurquen.
 *
 * Solo trabaja sobre texto: no consulta la base ni decide que version esta
 * vigente, igual que ResumenCirugia no consulta nada.
 */
final class Consentimiento
{
    /**
     * Marcador -> de donde sale el valor. La pantalla los lista para que nadie
     * tenga que adivinarlos.
     */
    public const MARCADORES = [
        '{{paciente}}' => 'Apellido y nombre del paciente',
        '{{dni}}' => 'Documento del paciente',
        '{{procedimiento}}' => 'Nombre del tipo de cirugía',
        '{{cirujano}}' => 'Apellido y nombre del cirujano',
    ];

    /**
     * Valores de muestra para la vista previa. Sirven para comprobar que los
     * marcadores estan bien escritos, que es lo unico que no se ve leyendo la
     * plantilla.
     */
    private const EJEMPLO = [
        '{{paciente}}' => 'Pérez, Juan Carlos',
        '{{dni}}' => '28456789',
        '{{procedimiento}}' => 'Colecistectomía laparoscópica',
        '{{cirujano}}' => 'López, Silvia',
    ];

    /**
     * El texto que firma un paciente. De aca sale el snapshot inmutable que
     * guarda ConsentimientoPaciente, junto con su hash.
     */
    public static function paraCirugia(string $plantilla, Cirugia $cirugia): string
    {
        $paciente = $cirugia->paciente;

        return strtr($plantilla, [
            '{{paciente}}' => $paciente?->nombre_completo ?? '',
            '{{dni}}' => $paciente?->documento ?? '',
            '{{procedimiento}}' => $cirugia->tipoCirugia->nombreTipoCirugia,
            '{{cirujano}}' => $cirugia->cirujano?->persona?->nombre_completo ?? '',
        ]);
    }

    public static function ejemplo(string $plantilla): string
    {
        return strtr($plantilla, self::EJEMPLO);
    }

    /**
     * Marcadores escritos en la plantilla que no existen. Un {{pacientes}} o un
     * {{ paciente }} con espacios no falla: queda tal cual en el documento que
     * firma el paciente, y nadie se entera hasta que ya esta firmado.
     *
     * @return list<string>
     */
    public static function desconocidos(string $texto): array
    {
        preg_match_all('/\{\{.*?\}\}/s', $texto, $encontrados);

        return array_values(array_unique(
            array_diff($encontrados[0], array_keys(self::MARCADORES))
        ));
    }
}
