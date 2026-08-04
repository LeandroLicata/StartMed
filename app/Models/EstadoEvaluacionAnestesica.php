<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoEvaluacionAnestesica extends Model
{
    protected $table = 'EstadoEvaluacionAnestesica';

    protected $primaryKey = 'idEstadoEvaluacionAnestesica';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoEvaluacionAnestesica',
        'fechaBajaEstadoEvaluacionAnestesica',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoEvaluacionAnestesica' => 'datetime',
        ];
    }

    public function evaluacionAnestesicaEstados(): HasMany
    {
        return $this->hasMany(EvaluacionAnestesicaEstado::class, 'idEstadoEvaluacionAnestesica', 'idEstadoEvaluacionAnestesica');
    }
}
