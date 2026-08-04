<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigTipoExamenPreAnestesicoPregunta extends Model
{
    protected $table = 'ConfigTipoExamenPreAnestesicoPregunta';

    protected $primaryKey = 'idConfigTipoExamenPreAnestesicoPregunta';

    public $timestamps = false;

    protected $fillable = [
        'idConfigTipoExamenPreAnestesico',
        'nombrePreguntaConfigTipoExamenPreAnestesicoPregunta',
        'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta',
    ];

    protected function casts(): array
    {
        return [
            'requiereOpcionRespuestaConfigTipoExamenPreAnestesicoPregunta' => 'boolean',
        ];
    }

    public function configTipoExamenPreAnestesico(): BelongsTo
    {
        return $this->belongsTo(ConfigTipoExamenPreAnestesico::class, 'idConfigTipoExamenPreAnestesico', 'idConfigTipoExamenPreAnestesico');
    }

    public function configTipoExamenPreAnestesicoPreguntaRespuestas(): HasMany
    {
        return $this->hasMany(ConfigTipoExamenPreAnestesicoPreguntaRespuesta::class, 'idConfigTipoExamenPreAnestesicoPregunta', 'idConfigTipoExamenPreAnestesicoPregunta');
    }

    public function examenPreAnestesicoConfigPreguntas(): HasMany
    {
        return $this->hasMany(ExamenPreAnestesicoConfigPregunta::class, 'idConfigTipoExamenPreAnestesicoPregunta', 'idConfigTipoExamenPreAnestesicoPregunta');
    }
}
