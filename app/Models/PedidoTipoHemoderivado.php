<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoTipoHemoderivado extends Model
{
    protected $table = 'PedidoTipoHemoderivado';

    protected $primaryKey = 'idPedidoTipoHemoderivado';

    public $timestamps = false;

    protected $fillable = [
        'idPedidoHemoderivado',
        'idTipoHemoderivado',
        'idEstablecimiento',
        'cantidadPedidoTipoHemoderivado',
        'descripcionPedidoTipoHemoderivado',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'idEstablecimiento', 'idEstablecimiento');
    }

    public function pedidoHemoderivado(): BelongsTo
    {
        return $this->belongsTo(PedidoHemoderivado::class, 'idPedidoHemoderivado', 'idPedidoHemoderivado');
    }

    public function tipoHemoderivado(): BelongsTo
    {
        return $this->belongsTo(TipoHemoderivado::class, 'idTipoHemoderivado', 'idTipoHemoderivado');
    }

    public function pedidoTipoHemoderivadoEstados(): HasMany
    {
        return $this->hasMany(PedidoTipoHemoderivadoEstado::class, 'idPedidoTipoHemoderivado', 'idPedidoTipoHemoderivado');
    }
}
