<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamenCirugiaPreAnestesica extends Model
{
    protected $table = 'ExamenCirugiaPreAnestesica';

    protected $primaryKey = 'idExamenCirugiaPreAnestesica';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'observacionesExamenCirugiaPreAnestesica',
    ];

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function examenPreAnestesicoConfiges(): HasMany
    {
        return $this->hasMany(ExamenPreAnestesicoConfig::class, 'idExamenCirugiaPreAnestesica', 'idExamenCirugiaPreAnestesica');
    }
}
