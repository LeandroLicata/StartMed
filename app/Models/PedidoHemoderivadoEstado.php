<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoHemoderivadoEstado extends Model
{
    protected $table = 'PedidoHemoderivadoEstado';

    protected $primaryKey = 'idPedidoHemoderivadoEstado';

    public $timestamps = false;

    protected $fillable = [
        'idPedidoHemoderivado',
        'idEstadoPedidoHemoderivado',
        'fechaAsignacionPedidoHemoderivadoEstado',
        'fechaFinAsignacionPedidoHemoderivadoEstado',
    ];

    protected function casts(): array
    {
        return [
            'fechaAsignacionPedidoHemoderivadoEstado' => 'datetime',
            'fechaFinAsignacionPedidoHemoderivadoEstado' => 'datetime',
        ];
    }

    public function estadoPedidoHemoderivado(): BelongsTo
    {
        return $this->belongsTo(EstadoPedidoHemoderivado::class, 'idEstadoPedidoHemoderivado', 'idEstadoPedidoHemoderivado');
    }

    public function pedidoHemoderivado(): BelongsTo
    {
        return $this->belongsTo(PedidoHemoderivado::class, 'idPedidoHemoderivado', 'idPedidoHemoderivado');
    }
}
