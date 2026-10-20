<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanObraSocial extends Model
{
    protected $table = 'PlanObraSocial';

    protected $primaryKey = 'idPlanObraSocial';

    public $timestamps = false;

    protected $fillable = [
        'idPersona',
        'idPlan',
        'nroBeneficiaroPlanObraSocial',
        'fechaInicioPlanObraSocial',
        'fechaFinPlanObraSocial',
        'esPredeterminadaPlanObraSocial',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioPlanObraSocial' => 'datetime',
            'fechaFinPlanObraSocial' => 'datetime',
            'esPredeterminadaPlanObraSocial' => 'boolean',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'idPlan', 'idPlan');
    }

    /**
     * La misma regla que vigentes(), sobre un modelo ya cargado. Si cambia
     * una, cambia la otra.
     */
    public function estaVigente(): bool
    {
        return $this->fechaFinPlanObraSocial === null
            || $this->fechaFinPlanObraSocial->isFuture();
    }

    /**
     * Coberturas vigentes: sin fecha de fin, o con una baja programada que
     * todavia no llego. El portal y las pantallas del gestor tienen que ver
     * la misma lista.
     */
    #[Scope]
    protected function vigentes(Builder $consulta): void
    {
        $consulta->where(fn (Builder $q) => $q
            ->whereNull('fechaFinPlanObraSocial')
            ->orWhere('fechaFinPlanObraSocial', '>', now()));
    }

    /**
     * Unica forma de sumarle una cobertura a un paciente, la cargue el
     * paciente desde el portal o el gestor al dar de alta o reprogramar.
     *
     * Si el paciente no tiene ninguna vigente marcada como predeterminada, la
     * nueva lo es: sin eso, el formulario del gestor no tiene nada que
     * preseleccionar (por ejemplo, despues de que el paciente dio de baja la
     * que era su predeterminada y cargo otra).
     */
    public static function agregar(int $idPersona, int $idPlan, ?string $nroBeneficiario): self
    {
        return static::create([
            'idPersona' => $idPersona,
            'idPlan' => $idPlan,
            'nroBeneficiaroPlanObraSocial' => $nroBeneficiario,
            'fechaInicioPlanObraSocial' => now(),
            'esPredeterminadaPlanObraSocial' => ! static::tienePredeterminadaVigente($idPersona),
        ]);
    }

    public static function tienePredeterminadaVigente(int $idPersona): bool
    {
        return static::query()
            ->where('idPersona', $idPersona)
            ->vigentes()
            ->where('esPredeterminadaPlanObraSocial', true)
            ->exists();
    }
}
