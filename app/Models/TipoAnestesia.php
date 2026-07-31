<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoAnestesia extends Model
{
    protected $table = 'TipoAnestesia';

    protected $primaryKey = 'idTipoAnestesia';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoAnestesia',
        'fechaBajaTipoAnestesia',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoAnestesia' => 'datetime',
        ];
    }

    public function evaluacionTipoAnestesias(): HasMany
    {
        return $this->hasMany(EvaluacionTipoAnestesia::class, 'idTipoAnestesia', 'idTipoAnestesia');
    }
}
