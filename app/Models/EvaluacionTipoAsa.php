<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionTipoAsa extends Model
{
    protected $table = 'EvaluacionTipoAsa';

    protected $primaryKey = 'idEvaluacionTipoAsa';

    public $timestamps = false;

    protected $fillable = [
        'idEvaluacionAnestesica',
        'idTipoAsa',
        'fechaInicioTipoAsa',
        'fechaFinTipoAsa',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioTipoAsa' => 'datetime',
            'fechaFinTipoAsa' => 'datetime',
        ];
    }

    public function evaluacionAnestesica(): BelongsTo
    {
        return $this->belongsTo(EvaluacionAnestesica::class, 'idEvaluacionAnestesica', 'idEvaluacionAnestesica');
    }

    public function tipoAsa(): BelongsTo
    {
        return $this->belongsTo(TipoASA::class, 'idTipoAsa', 'idTipoAsa');
    }
}
