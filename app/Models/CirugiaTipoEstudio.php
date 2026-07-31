<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirugiaTipoEstudio extends Model
{
    protected $table = 'CirugiaTipoEstudio';

    protected $primaryKey = 'idCirugiaTipoEstudio';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'idTipoEstudio',
        'urlArchivoCirugiaTipoEstudio',
        'fechaSubidaCirugiaTipoEstudio',
        'fechaAsignacionCirugiaTipoEstudio',
        'fechaFinAsignacionCirugiaTipoEstudio',
        'fechaEsperadaResultadoCirugiaTipoEstudio',
        'resultadoCirugiaTipoEstudio',
    ];

    protected function casts(): array
    {
        return [
            'fechaSubidaCirugiaTipoEstudio' => 'datetime',
            'fechaAsignacionCirugiaTipoEstudio' => 'datetime',
            'fechaFinAsignacionCirugiaTipoEstudio' => 'datetime',
            'fechaEsperadaResultadoCirugiaTipoEstudio' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function tipoEstudio(): BelongsTo
    {
        return $this->belongsTo(TipoEstudio::class, 'idTipoEstudio', 'idTipoEstudio');
    }
}
