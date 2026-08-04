<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoHemoderivado extends Model
{
    protected $table = 'PedidoHemoderivado';

    protected $primaryKey = 'idPedidoHemoderivado';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'observacionesPedidoHemoderivados',
        'fechaPedidoHemoderivado',
    ];

    protected function casts(): array
    {
        return [
            'fechaPedidoHemoderivado' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function pedidoHemoderivadoEstados(): HasMany
    {
        return $this->hasMany(PedidoHemoderivadoEstado::class, 'idPedidoHemoderivado', 'idPedidoHemoderivado');
    }

    public function pedidoTipoHemoderivados(): HasMany
    {
        return $this->hasMany(PedidoTipoHemoderivado::class, 'idPedidoHemoderivado', 'idPedidoHemoderivado');
    }
}
