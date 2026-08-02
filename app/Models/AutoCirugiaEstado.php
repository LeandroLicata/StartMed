<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoCirugiaEstado extends Model
{
    protected $table = 'AutoCirugiaEstado';

    protected $primaryKey = 'idAutCirugiaEstado';

    public $timestamps = false;

    protected $fillable = [
        'idAutCirugia',
        'idEstadoAutCirugia',
        'fechaInicioAutoCirugiaEstado',
        'fechaFinAutoCirugiaEstado',
        'observacionesAutoCirugiaEstado',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioAutoCirugiaEstado' => 'datetime',
            'fechaFinAutoCirugiaEstado' => 'datetime',
        ];
    }

    public function autCirugia(): BelongsTo
    {
        return $this->belongsTo(AutCirugia::class, 'idAutCirugia', 'idAutCirugia');
    }

    public function estadoAutCirugia(): BelongsTo
    {
        return $this->belongsTo(EstadoAutCirugia::class, 'idEstadoAutCirugia', 'idEstadoAutCirugia');
    }
}
