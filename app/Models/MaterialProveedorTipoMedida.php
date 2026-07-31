<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialProveedorTipoMedida extends Model
{
    protected $table = 'MaterialProveedorTipoMedida';

    protected $primaryKey = 'idMaterialProveedorTipoMedida';

    public $timestamps = false;

    protected $fillable = [
        'idMaterialProveedor',
        'idTipoMedida',
        'fechaAsignacionMaterialTipoMedida',
        'fechaFinAsignacionMaterialTipoMedida',
        'disponibleMaterialTipoMedida',
    ];

    protected function casts(): array
    {
        return [
            'fechaAsignacionMaterialTipoMedida' => 'datetime',
            'fechaFinAsignacionMaterialTipoMedida' => 'datetime',
            'disponibleMaterialTipoMedida' => 'boolean',
        ];
    }

    public function materialProveedor(): BelongsTo
    {
        return $this->belongsTo(MaterialProveedor::class, 'idMaterialProveedor', 'idMaterialProveedor');
    }

    public function tipoMedida(): BelongsTo
    {
        return $this->belongsTo(TipoMedida::class, 'idTipoMedida', 'idTipoMedida');
    }
}
