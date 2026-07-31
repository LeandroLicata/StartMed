<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establecimiento extends Model
{
    protected $table = 'Establecimiento';

    protected $primaryKey = 'idEstablecimiento';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstablecimiento',
        'descripcionEstablecimiento',
        'codTelefonoEstablecimiento',
        'numeroTelefonoEstablecimiento',
        'emailEstablecimiento',
        'fechaEstablecimiento',
    ];

    protected function casts(): array
    {
        return [
            'fechaEstablecimiento' => 'datetime',
        ];
    }

    public function pedidoTipoHemoderivados(): HasMany
    {
        return $this->hasMany(PedidoTipoHemoderivado::class, 'idEstablecimiento', 'idEstablecimiento');
    }
}
