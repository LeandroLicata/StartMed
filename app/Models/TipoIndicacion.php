<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoIndicacion extends Model
{
    protected $table = 'TipoIndicacion';

    protected $primaryKey = 'idTipoIndicacion';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoIndicacion',
        'fechaBajaTipoIndicacion',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoIndicacion' => 'datetime',
        ];
    }

    public function preparacionPacienteTipoPreparacionTipoIndicaciones(): HasMany
    {
        return $this->hasMany(PreparacionPacienteTipoPreparacionTipoIndicacion::class, 'idTipoIndicacion', 'idTipoIndicacion');
    }
}
