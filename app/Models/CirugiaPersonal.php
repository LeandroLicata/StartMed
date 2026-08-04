<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirugiaPersonal extends Model
{
    protected $table = 'CirugiaPersonal';

    protected $primaryKey = 'idCirugiaPersonal';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'idPersonal',
        'idRol',
        'fechaInicioAsignacionCirugiaPersonal',
        'fechaFinAsignacionCirugiaPersonal',
        'observacionesCirugiaPersonal',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioAsignacionCirugiaPersonal' => 'datetime',
            'fechaFinAsignacionCirugiaPersonal' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'idPersonal', 'idPersonal');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'idRol', 'idRol');
    }
}
