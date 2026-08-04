<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoPedidoMaterial extends Model
{
    protected $table = 'EstadoPedidoMaterial';

    protected $primaryKey = 'idEstadoPedidoMaterial';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoPedidoMaterial',
        'fechaBajaEstadoPedidoMaterial',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoPedidoMaterial' => 'datetime',
        ];
    }

    public function pedidoMaterialEstados(): HasMany
    {
        return $this->hasMany(PedidoMaterialEstado::class, 'idEstadoPedidoMaterial', 'idEstadoPedidoMaterial');
    }
}
