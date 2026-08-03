<?php

namespace App\Http\Requests;

use App\Support\Consentimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Texto de una version de plantilla de consentimiento.
 */
class ConsentimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'textoConfigConsentimiento' => ['required', 'string', 'min:50'],
        ];
    }

    /**
     * Un marcador mal escrito no rompe nada: queda literal en el documento que
     * firma el paciente, y para cuando alguien lo ve ya esta firmado. Por eso
     * se rechaza al guardar y no se avisa despues.
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $desconocidos = Consentimiento::desconocidos(
                    $this->input('textoConfigConsentimiento') ?? ''
                );

                if ($desconocidos === []) {
                    return;
                }

                $validador->errors()->add('textoConfigConsentimiento', sprintf(
                    'Hay marcadores que el sistema no sabe completar: %s. Los válidos son %s.',
                    implode(', ', $desconocidos),
                    implode(', ', array_keys(Consentimiento::MARCADORES)),
                ));
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['textoConfigConsentimiento' => 'texto del consentimiento'];
    }
}
