<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoMedida extends Model
{
    protected $table = 'TipoMedida';

    protected $primaryKey = 'idTipoMedida';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoMedida',
        'fechaBajaTipoMedida',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoMedida' => 'datetime',
        ];
    }

    public function materialProveedorTipoMedidas(): HasMany
    {
        return $this->hasMany(MaterialProveedorTipoMedida::class, 'idTipoMedida', 'idTipoMedida');
    }

    public function pedidoMateriales(): HasMany
    {
        return $this->hasMany(PedidoMaterial::class, 'idTipoMedida', 'idTipoMedida');
    }
}
