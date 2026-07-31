<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoMaterialEstado extends Model
{
    protected $table = 'PedidoMaterialEstado';

    protected $primaryKey = 'idPedidoMaterialEstado';

    public $timestamps = false;

    protected $fillable = [
        'idPedidoMaterial',
        'idEstadoPedidoMaterial',
        'observacionesPedidoMaterialEstado',
    ];

    public function estadoPedidoMaterial(): BelongsTo
    {
        return $this->belongsTo(EstadoPedidoMaterial::class, 'idEstadoPedidoMaterial', 'idEstadoPedidoMaterial');
    }

    public function pedidoMaterial(): BelongsTo
    {
        return $this->belongsTo(PedidoMaterial::class, 'idPedidoMaterial', 'idPedidoMaterial');
    }
}
