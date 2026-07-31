<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigTipoExamenPreAnestesicoPreguntaRespuesta extends Model
{
    protected $table = 'ConfigTipoExamenPreAnestesicoPreguntaRespuesta';

    protected $primaryKey = 'idConfigTipoExamenPreAnestesicoPreguntaRespuesta';

    public $timestamps = false;

    protected $fillable = [
        'idConfigTipoExamenPreAnestesicoPregunta',
        'nombreRespuestaConfigTipoExamenPreAnestesicoPreguntaRespuesta',
    ];

    public function configTipoExamenPreAnestesicoPregunta(): BelongsTo
    {
        return $this->belongsTo(ConfigTipoExamenPreAnestesicoPregunta::class, 'idConfigTipoExamenPreAnestesicoPregunta', 'idConfigTipoExamenPreAnestesicoPregunta');
    }

    public function examenPreAnestesicoConfigPreguntaRespuestas(): HasMany
    {
        return $this->hasMany(ExamenPreAnestesicoConfigPreguntaRespuesta::class, 'idConfigTipoExamenPreAnestesicoPreguntaRespuesta', 'idConfigTipoExamenPreAnestesicoPreguntaRespuesta');
    }
}
