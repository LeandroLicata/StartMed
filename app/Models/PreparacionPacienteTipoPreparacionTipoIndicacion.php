<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreparacionPacienteTipoPreparacionTipoIndicacion extends Model
{
    protected $table = 'PreparacionPacienteTipoPreparacionTipoIndicacion';

    protected $primaryKey = 'idPreparacionPacienteTipoPreparacionTipoIndicacion';

    public $timestamps = false;

    protected $fillable = [
        'idPreparacionPacienteTipoPreparacion',
        'idTipoIndicacion',
        'hsReglaCantidadIngestaAnteriorCirugia',
    ];

    public function preparacionPacienteTipoPreparacion(): BelongsTo
    {
        return $this->belongsTo(PreparacionPacienteTipoPreparacion::class, 'idPreparacionPacienteTipoPreparacion', 'idPreparacionPacienteTipoPreparacion');
    }

    public function tipoIndicacion(): BelongsTo
    {
        return $this->belongsTo(TipoIndicacion::class, 'idTipoIndicacion', 'idTipoIndicacion');
    }
}
