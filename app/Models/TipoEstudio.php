<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEstudio extends Model
{
    protected $table = 'TipoEstudio';

    protected $primaryKey = 'idTipoEstudio';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoEstudio',
        'fechaBajaTipoEstudio',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoEstudio' => 'datetime',
        ];
    }

    public function cirugiaTipoEstudios(): HasMany
    {
        return $this->hasMany(CirugiaTipoEstudio::class, 'idTipoEstudio', 'idTipoEstudio');
    }
}
