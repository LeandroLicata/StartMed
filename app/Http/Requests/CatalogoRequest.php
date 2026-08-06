<?php

namespace App\Http\Requests;

use App\Support\Catalogos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Reglas de un catalogo, derivadas del mapa de App\Support\Catalogos. Sirve
 * para el alta y para la edicion: la diferencia es a que registro ignora la
 * regla de unicidad.
 */
class CatalogoRequest extends FormRequest
{
    /**
     * El acceso ya lo resuelve el middleware `rol:Administrador`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $config = Catalogos::buscar($this->route('catalogo'));
        $modelo = new $config['modelo'];

        $reglas = [];

        foreach ($config['campos'] as $columna => $campo) {
            $tipo = $campo['tipo'] ?? 'texto';

            $regla = [($campo['requerido'] ?? false) ? 'required' : 'nullable'];

            $regla[] = match ($tipo) {
                'numero' => 'integer',
                'booleano' => 'boolean',
                'email' => 'email',
                'select' => $this->existeEnOpciones($campo),
                default => 'string',
            };

            // El largo maximo sale de la migracion y solo aplica a texto.
            if (isset($campo['max']) && in_array($tipo, ['texto', 'texto-largo', 'email'], true)) {
                $regla[] = 'max:'.$campo['max'];
            }

            if ($campo['unico'] ?? false) {
                $regla[] = Rule::unique($modelo->getTable(), $columna)
                    ->ignore($this->route('registro'), $modelo->getKeyName());
            }

            $reglas[$columna] = $regla;
        }

        return $reglas;
    }

    /**
     * Que los mensajes hablen de "Nombre" y no de "nombreTipoEstudio".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $campos = Catalogos::buscar($this->route('catalogo'))['campos'];

        return array_map(fn (array $campo) => mb_strtolower($campo['etiqueta']), $campos);
    }

    /**
     * @param  array<string, mixed>  $campo
     */
    private function existeEnOpciones(array $campo): Exists
    {
        [$modelo] = $campo['opciones'];
        $relacionado = new $modelo;

        return Rule::exists($relacionado->getTable(), $relacionado->getKeyName());
    }
}
