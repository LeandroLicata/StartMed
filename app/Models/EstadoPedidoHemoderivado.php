<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoPedidoHemoderivado extends Model
{
    protected $table = 'EstadoPedidoHemoderivado';

    protected $primaryKey = 'idEstadoPedidoHemoderivado';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoPedidoHemoderivado',
        'fechaBajaEstadoPedidoHemoderivado',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoPedidoHemoderivado' => 'datetime',
        ];
    }

    public function pedidoHemoderivadoEstados(): HasMany
    {
        return $this->hasMany(PedidoHemoderivadoEstado::class, 'idEstadoPedidoHemoderivado', 'idEstadoPedidoHemoderivado');
    }
}
