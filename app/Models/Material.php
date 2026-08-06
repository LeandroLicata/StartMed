<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Material extends Model
{
    protected $table = 'Material';

    protected $primaryKey = 'idMaterial';

    public $timestamps = false;

    protected $fillable = [
        'nombreMaterial',
        'codMaterial',
        'fechaBajaMaterial',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaMaterial' => 'datetime',
        ];
    }

    public function materialProveedores(): HasMany
    {
        return $this->hasMany(MaterialProveedor::class, 'idMaterial', 'idMaterial');
    }

    /**
     * Todos los precios de este material, sin importar el proveedor.
     *
     * El precio vive dos niveles abajo (proveedor -> unidad de venta), asi que
     * sin esta relacion el listado de /admin/precios no puede pedir el minimo
     * y el maximo en la misma consulta.
     */
    public function materialProveedorTipoMedidas(): HasManyThrough
    {
        return $this->hasManyThrough(
            MaterialProveedorTipoMedida::class,
            MaterialProveedor::class,
            'idMaterial',
            'idMaterialProveedor',
            'idMaterial',
            'idMaterialProveedor',
        );
    }

    public function pedidoMateriales(): HasMany
    {
        return $this->hasMany(PedidoMaterial::class, 'idMaterial', 'idMaterial');
    }
}
