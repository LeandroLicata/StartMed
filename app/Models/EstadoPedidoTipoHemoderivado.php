<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoPedidoTipoHemoderivado extends Model
{
    protected $table = 'EstadoPedidoTipoHemoderivado';

    protected $primaryKey = 'idEstadoPedidoTipoHemoderivado';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoPedidoTipoHemoderivado',
        'fechaBajaEstadoPedidoTipoHemoderivado',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoPedidoTipoHemoderivado' => 'datetime',
        ];
    }

    public function pedidoTipoHemoderivadoEstados(): HasMany
    {
        return $this->hasMany(PedidoTipoHemoderivadoEstado::class, 'idEstadoPedidoTipoHemoderivado', 'idEstadoPedidoTipoHemoderivado');
    }
}
