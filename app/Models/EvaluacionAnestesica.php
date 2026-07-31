<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluacionAnestesica extends Model
{
    protected $table = 'EvaluacionAnestesica';

    protected $primaryKey = 'idEvaluacionAnestesica';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'observacionesEquipoEvaluacion',
        'observacionesPacienteEvaluacion',
    ];

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function evaluacionAnestesicaEstados(): HasMany
    {
        return $this->hasMany(EvaluacionAnestesicaEstado::class, 'idEvaluacionAnestesica', 'idEvaluacionAnestesica');
    }

    public function evaluacionTipoAnestesias(): HasMany
    {
        return $this->hasMany(EvaluacionTipoAnestesia::class, 'idEvaluacionAnestesica', 'idEvaluacionAnestesica');
    }

    public function evaluacionTipoAsas(): HasMany
    {
        return $this->hasMany(EvaluacionTipoAsa::class, 'idEvaluacionAnestesica', 'idEvaluacionAnestesica');
    }
}
