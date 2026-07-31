<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function pedidoMateriales(): HasMany
    {
        return $this->hasMany(PedidoMaterial::class, 'idMaterial', 'idMaterial');
    }
}
