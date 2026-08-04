<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoTipoHemoderivadoEstado extends Model
{
    protected $table = 'PedidoTipoHemoderivadoEstado';

    protected $primaryKey = 'idPedidoTipoHemoderivadoEstado';

    public $timestamps = false;

    protected $fillable = [
        'idPedidoTipoHemoderivado',
        'idEstadoPedidoTipoHemoderivado',
        'fechaAsignacionPedidoTipoHemoderivadoEstado',
        'fechaFinAsignacionPedidoTipoHemoderivadoEstado',
    ];

    protected function casts(): array
    {
        return [
            'fechaAsignacionPedidoTipoHemoderivadoEstado' => 'datetime',
            'fechaFinAsignacionPedidoTipoHemoderivadoEstado' => 'datetime',
        ];
    }

    public function estadoPedidoTipoHemoderivado(): BelongsTo
    {
        return $this->belongsTo(EstadoPedidoTipoHemoderivado::class, 'idEstadoPedidoTipoHemoderivado', 'idEstadoPedidoTipoHemoderivado');
    }

    public function pedidoTipoHemoderivado(): BelongsTo
    {
        return $this->belongsTo(PedidoTipoHemoderivado::class, 'idPedidoTipoHemoderivado', 'idPedidoTipoHemoderivado');
    }
}
