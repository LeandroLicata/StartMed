<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirugiaEstado extends Model
{
    protected $table = 'CirugiaEstado';

    protected $primaryKey = 'idCirugiaEstado';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'idEstadoCirugia',
        'fechaAsignacionCirugiaEstado',
        'fechaDesasignacionCirugiaEstado',
    ];

    protected function casts(): array
    {
        return [
            'fechaAsignacionCirugiaEstado' => 'datetime',
            'fechaDesasignacionCirugiaEstado' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function estadoCirugia(): BelongsTo
    {
        return $this->belongsTo(EstadoCirugia::class, 'idEstadoCirugia', 'idEstadoCirugia');
    }
}
