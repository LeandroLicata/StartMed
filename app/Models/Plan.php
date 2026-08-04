<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'Plan';

    protected $primaryKey = 'idPlan';

    public $timestamps = false;

    protected $fillable = [
        'idobrasocial',
        'nombrePlan',
        'es_sin_cobertura',
        'habilitado_autorizaciones',
        'observacion_alerta',
        'fechaBajaPlan',
    ];

    protected function casts(): array
    {
        return [
            'es_sin_cobertura' => 'boolean',
            'habilitado_autorizaciones' => 'boolean',
            'fechaBajaPlan' => 'datetime',
        ];
    }

    public function obrasocial(): BelongsTo
    {
        return $this->belongsTo(ObraSocial::class, 'idobrasocial', 'idObraSocial');
    }

    public function autCirugias(): HasMany
    {
        return $this->hasMany(AutCirugia::class, 'idPlan', 'idPlan');
    }

    public function pedidoMateriales(): HasMany
    {
        return $this->hasMany(PedidoMaterial::class, 'idPlan', 'idPlan');
    }

    public function planObraSociales(): HasMany
    {
        return $this->hasMany(PlanObraSocial::class, 'idPlan', 'idPlan');
    }
}
