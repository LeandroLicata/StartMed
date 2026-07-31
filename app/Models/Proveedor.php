<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    protected $table = 'Proveedor';

    protected $primaryKey = 'idProveedor';

    public $timestamps = false;

    protected $fillable = [
        'nombreProveedor',
        'cuitProveedor',
        'telefonoProveedor',
        'fechaBajaProveedor',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaProveedor' => 'datetime',
        ];
    }

    public function materialProveedores(): HasMany
    {
        return $this->hasMany(MaterialProveedor::class, 'idProveedor', 'idProveedor');
    }

    public function pedidoMateriales(): HasMany
    {
        return $this->hasMany(PedidoMaterial::class, 'idProveedor', 'idProveedor');
    }
}
