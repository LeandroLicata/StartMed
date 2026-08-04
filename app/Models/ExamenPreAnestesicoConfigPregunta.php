<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamenPreAnestesicoConfigPregunta extends Model
{
    protected $table = 'ExamenPreAnestesicoConfigPregunta';

    protected $primaryKey = 'idExamenPreAnestesicoConfigPregunta';

    public $timestamps = false;

    protected $fillable = [
        'idExamenPreAnestesicoConfig',
        'idConfigTipoExamenPreAnestesicoPregunta',
        'respuestaExamenPreAnestesicoConfigPregunta',
    ];

    public function examenPreAnestesicoConfig(): BelongsTo
    {
        return $this->belongsTo(ExamenPreAnestesicoConfig::class, 'idExamenPreAnestesicoConfig', 'idExamenPreAnestesicoConfig');
    }

    public function configTipoExamenPreAnestesicoPregunta(): BelongsTo
    {
        return $this->belongsTo(ConfigTipoExamenPreAnestesicoPregunta::class, 'idConfigTipoExamenPreAnestesicoPregunta', 'idConfigTipoExamenPreAnestesicoPregunta');
    }

    public function examenPreAnestesicoConfigPreguntaRespuestas(): HasMany
    {
        return $this->hasMany(ExamenPreAnestesicoConfigPreguntaRespuesta::class, 'idExamenPreAnestesicoConfigPregunta', 'idExamenPreAnestesicoConfigPregunta');
    }
}
