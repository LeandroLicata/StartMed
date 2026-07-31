<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CirugiaQuirofano extends Model
{
    protected $table = 'CirugiaQuirofano';

    protected $primaryKey = 'idCirugiaQuirofano';

    public $timestamps = false;

    protected $fillable = [
        'idQuirofano',
        'idCirugia',
        'fechaHoraAsignacion',
        'fechaHoraDesasignacion',
        'observacionCirugiaQuirofano',
    ];

    protected function casts(): array
    {
        return [
            'fechaHoraAsignacion' => 'datetime',
            'fechaHoraDesasignacion' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function quirofano(): BelongsTo
    {
        return $this->belongsTo(Quirofano::class, 'idQuirofano', 'idQuirofano');
    }
}
