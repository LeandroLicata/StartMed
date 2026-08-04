<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreparacionPaciente extends Model
{
    protected $table = 'PreparacionPaciente';

    protected $primaryKey = 'idPreparacionPaciente';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'observacionesPreparacionPaciente',
    ];

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function preparacionPacienteTipoPreparaciones(): HasMany
    {
        return $this->hasMany(PreparacionPacienteTipoPreparacion::class, 'idPreparacionPaciente', 'idPreparacionPaciente');
    }
}
