<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoASA extends Model
{
    protected $table = 'TipoASA';

    protected $primaryKey = 'idTipoAsa';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoAsa',
        'aliasTipoAsa',
        'descripcionTipoAsa',
        'fechaBajaTipoAsa',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoAsa' => 'datetime',
        ];
    }

    public function evaluacionTipoAsas(): HasMany
    {
        return $this->hasMany(EvaluacionTipoAsa::class, 'idTipoAsa', 'idTipoAsa');
    }
}
