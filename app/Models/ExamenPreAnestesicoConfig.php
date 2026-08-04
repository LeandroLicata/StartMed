<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamenPreAnestesicoConfig extends Model
{
    protected $table = 'ExamenPreAnestesicoConfig';

    protected $primaryKey = 'idExamenPreAnestesicoConfig';

    public $timestamps = false;

    protected $fillable = [
        'idExamenCirugiaPreAnestesica',
        'idConfigTipoExamenPreAnestesico',
    ];

    public function configTipoExamenPreAnestesico(): BelongsTo
    {
        return $this->belongsTo(ConfigTipoExamenPreAnestesico::class, 'idConfigTipoExamenPreAnestesico', 'idConfigTipoExamenPreAnestesico');
    }

    public function examenCirugiaPreAnestesica(): BelongsTo
    {
        return $this->belongsTo(ExamenCirugiaPreAnestesica::class, 'idExamenCirugiaPreAnestesica', 'idExamenCirugiaPreAnestesica');
    }

    public function examenPreAnestesicoConfigPreguntas(): HasMany
    {
        return $this->hasMany(ExamenPreAnestesicoConfigPregunta::class, 'idExamenPreAnestesicoConfig', 'idExamenPreAnestesicoConfig');
    }
}
