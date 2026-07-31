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
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioPlanObraSocial' => 'datetime',
            'fechaFinPlanObraSocial' => 'datetime',
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
}
