<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionAnestesicaEstado extends Model
{
    protected $table = 'EvaluacionAnestesicaEstado';

    protected $primaryKey = 'idEvaluacionAnestesicaEstado';

    public $timestamps = false;

    protected $fillable = [
        'idEvaluacionAnestesica',
        'idEstadoEvaluacionAnestesica',
        'fechaInicioEvaluacionAnestesicaEstado',
        'fechaFinEvaluacionAnestesicaEstado',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioEvaluacionAnestesicaEstado' => 'datetime',
            'fechaFinEvaluacionAnestesicaEstado' => 'datetime',
        ];
    }

    public function estadoEvaluacionAnestesica(): BelongsTo
    {
        return $this->belongsTo(EstadoEvaluacionAnestesica::class, 'idEstadoEvaluacionAnestesica', 'idEstadoEvaluacionAnestesica');
    }

    public function evaluacionAnestesica(): BelongsTo
    {
        return $this->belongsTo(EvaluacionAnestesica::class, 'idEvaluacionAnestesica', 'idEvaluacionAnestesica');
    }
}
