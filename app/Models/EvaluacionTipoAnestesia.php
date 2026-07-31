<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionTipoAnestesia extends Model
{
    protected $table = 'EvaluacionTipoAnestesia';

    protected $primaryKey = 'idEvaluacionTipoAnestesia';

    public $timestamps = false;

    protected $fillable = [
        'idEvaluacionAnestesica',
        'idTipoAnestesia',
        'fechaInicioTipoAnestesia',
        'fechaFinTipoAnestesia',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioTipoAnestesia' => 'datetime',
            'fechaFinTipoAnestesia' => 'datetime',
        ];
    }

    public function evaluacionAnestesica(): BelongsTo
    {
        return $this->belongsTo(EvaluacionAnestesica::class, 'idEvaluacionAnestesica', 'idEvaluacionAnestesica');
    }

    public function tipoAnestesia(): BelongsTo
    {
        return $this->belongsTo(TipoAnestesia::class, 'idTipoAnestesia', 'idTipoAnestesia');
    }
}
