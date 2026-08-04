<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoHemoderivado extends Model
{
    protected $table = 'TipoHemoderivado';

    protected $primaryKey = 'idTipoHemoderivado';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoHemoderivado',
        'fechaBajaTipoHemoderivado',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoHemoderivado' => 'datetime',
        ];
    }

    public function pedidoTipoHemoderivados(): HasMany
    {
        return $this->hasMany(PedidoTipoHemoderivado::class, 'idTipoHemoderivado', 'idTipoHemoderivado');
    }
}
