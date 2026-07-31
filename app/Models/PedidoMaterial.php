<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoMaterial extends Model
{
    protected $table = 'PedidoMaterial';

    protected $primaryKey = 'idPedidoMaterial';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'idMaterial',
        'idPlan',
        'idProveedor',
        'idTipoMedida',
        'cantidadPedidoMaterial',
        'observacionesPedidoMaterial',
        'subtotalPedidoMaterial',
        'fechaPedidoMaterial',
    ];

    protected function casts(): array
    {
        return [
            'subtotalPedidoMaterial' => 'decimal:2',
            'fechaPedidoMaterial' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'idMaterial', 'idMaterial');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'idPlan', 'idPlan');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'idProveedor', 'idProveedor');
    }

    public function tipoMedida(): BelongsTo
    {
        return $this->belongsTo(TipoMedida::class, 'idTipoMedida', 'idTipoMedida');
    }

    public function pedidoMaterialEstados(): HasMany
    {
        return $this->hasMany(PedidoMaterialEstado::class, 'idPedidoMaterial', 'idPedidoMaterial');
    }
}
