<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialProveedor extends Model
{
    protected $table = 'MaterialProveedor';

    protected $primaryKey = 'idMaterialProveedor';

    public $timestamps = false;

    protected $fillable = [
        'idMaterial',
        'idProveedor',
        'codExternoMaterialProveedor',
        'precioExternoMaterialProveedor',
        'fechaActualizacionPrecio',
    ];

    protected function casts(): array
    {
        return [
            'precioExternoMaterialProveedor' => 'decimal:2',
            'fechaActualizacionPrecio' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'idMaterial', 'idMaterial');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'idProveedor', 'idProveedor');
    }

    public function materialProveedorTipoMedidas(): HasMany
    {
        return $this->hasMany(MaterialProveedorTipoMedida::class, 'idMaterialProveedor', 'idMaterialProveedor');
    }
}
