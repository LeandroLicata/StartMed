<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HisopadoSarmEstado extends Model
{
    protected $table = 'HisopadoSarmEstado';

    protected $primaryKey = 'idHisopadoSarmEstado';

    public $timestamps = false;

    protected $fillable = [
        'idHisopadoSarm',
        'idEstadoHisopadoSarm',
        'fechaInicioAsignacionHisopadoSarmEstado',
        'fechaFinAsignacionHisopadoSarmEstado',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioAsignacionHisopadoSarmEstado' => 'datetime',
            'fechaFinAsignacionHisopadoSarmEstado' => 'datetime',
        ];
    }

    public function estadoHisopadoSarm(): BelongsTo
    {
        return $this->belongsTo(EstadoHisopadoSarm::class, 'idEstadoHisopadoSarm', 'idEstadoHisopadoSarm');
    }

    public function hisopadoSarm(): BelongsTo
    {
        return $this->belongsTo(HisopadoSarm::class, 'idHisopadoSarm', 'idHisopadoSarm');
    }
}
