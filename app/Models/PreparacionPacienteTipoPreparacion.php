<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreparacionPacienteTipoPreparacion extends Model
{
    protected $table = 'PreparacionPacienteTipoPreparacion';

    protected $primaryKey = 'idPreparacionPacienteTipoPreparacion';

    public $timestamps = false;

    protected $fillable = [
        'idPreparacionPaciente',
        'idTipoPreparacion',
    ];

    public function preparacionPaciente(): BelongsTo
    {
        return $this->belongsTo(PreparacionPaciente::class, 'idPreparacionPaciente', 'idPreparacionPaciente');
    }

    public function tipoPreparacion(): BelongsTo
    {
        return $this->belongsTo(TipoPreparacion::class, 'idTipoPreparacion', 'idTipoPreparacion');
    }

    public function preparacionPacienteTipoPreparacionTipoIndicaciones(): HasMany
    {
        return $this->hasMany(PreparacionPacienteTipoPreparacionTipoIndicacion::class, 'idPreparacionPacienteTipoPreparacion', 'idPreparacionPacienteTipoPreparacion');
    }
}
