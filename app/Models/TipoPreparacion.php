<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoPreparacion extends Model
{
    protected $table = 'TipoPreparacion';

    protected $primaryKey = 'idTipoPreparacion';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoPreparacion',
        'fechaBajaTipoPreparacion',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoPreparacion' => 'datetime',
        ];
    }

    public function preparacionPacienteTipoPreparaciones(): HasMany
    {
        return $this->hasMany(PreparacionPacienteTipoPreparacion::class, 'idTipoPreparacion', 'idTipoPreparacion');
    }
}
