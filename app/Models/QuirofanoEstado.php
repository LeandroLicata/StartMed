<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuirofanoEstado extends Model
{
    protected $table = 'QuirofanoEstado';

    protected $primaryKey = 'idQuirofanoEstado';

    public $timestamps = false;

    protected $fillable = [
        'idQuirofano',
        'idEstadoQuirofano',
        'fechaInicioQuirofanoEstado',
        'fechaFinQuirofanoEstado',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioQuirofanoEstado' => 'datetime',
            'fechaFinQuirofanoEstado' => 'datetime',
        ];
    }

    public function estadoQuirofano(): BelongsTo
    {
        return $this->belongsTo(EstadoQuirofano::class, 'idEstadoQuirofano', 'idEstadoQuirofano');
    }

    public function quirofano(): BelongsTo
    {
        return $this->belongsTo(Quirofano::class, 'idQuirofano', 'idQuirofano');
    }
}
