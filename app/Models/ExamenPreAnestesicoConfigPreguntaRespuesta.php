<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamenPreAnestesicoConfigPreguntaRespuesta extends Model
{
    protected $table = 'ExamenPreAnestesicoConfigPreguntaRespuesta';

    protected $primaryKey = 'idExamenPreAnestesicoConfigPreguntaRespuesta';

    public $timestamps = false;

    protected $fillable = [
        'idExamenPreAnestesicoConfigPregunta',
        'idConfigTipoExamenPreAnestesicoPreguntaRespuesta',
    ];

    public function examenPreAnestesicoConfigPregunta(): BelongsTo
    {
        return $this->belongsTo(ExamenPreAnestesicoConfigPregunta::class, 'idExamenPreAnestesicoConfigPregunta', 'idExamenPreAnestesicoConfigPregunta');
    }

    public function configTipoExamenPreAnestesicoPreguntaRespuesta(): BelongsTo
    {
        return $this->belongsTo(ConfigTipoExamenPreAnestesicoPreguntaRespuesta::class, 'idConfigTipoExamenPreAnestesicoPreguntaRespuesta', 'idConfigTipoExamenPreAnestesicoPreguntaRespuesta');
    }
}
