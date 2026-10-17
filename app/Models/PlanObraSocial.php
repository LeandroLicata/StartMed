<?php

namespace App\Models;

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

    public function estaVigente(): bool
    { 
        return $this->fechaFinPlanObraSocial === null
            || $this->fechaFinPlanObraSocial->isFuture();
    }
}
