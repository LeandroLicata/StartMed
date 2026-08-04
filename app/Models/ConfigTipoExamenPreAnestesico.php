<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigTipoExamenPreAnestesico extends Model
{
    protected $table = 'ConfigTipoExamenPreAnestesico';

    protected $primaryKey = 'idConfigTipoExamenPreAnestesico';

    public $timestamps = false;

    protected $fillable = [
        'fechaInicioVigeConfigTipoExamenPreAnestesico',
        'fechaFinVigeConfigTipoExamenPreAnestesico',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioVigeConfigTipoExamenPreAnestesico' => 'datetime',
            'fechaFinVigeConfigTipoExamenPreAnestesico' => 'datetime',
        ];
    }

    public function configTipoExamenPreAnestesicoPreguntas(): HasMany
    {
        return $this->hasMany(ConfigTipoExamenPreAnestesicoPregunta::class, 'idConfigTipoExamenPreAnestesico', 'idConfigTipoExamenPreAnestesico');
    }

    public function examenPreAnestesicoConfiges(): HasMany
    {
        return $this->hasMany(ExamenPreAnestesicoConfig::class, 'idConfigTipoExamenPreAnestesico', 'idConfigTipoExamenPreAnestesico');
    }
}
