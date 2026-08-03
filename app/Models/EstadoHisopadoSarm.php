<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoHisopadoSarm extends Model
{
    protected $table = 'EstadoHisopadoSarm';

    protected $primaryKey = 'idEstadoHisopadoSarm';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoHisopadoSarm',
        'fechaBajaEstadoHisopadoSarm',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoHisopadoSarm' => 'datetime',
        ];
    }

    public function hisopadoSarmEstados(): HasMany
    {
        return $this->hasMany(HisopadoSarmEstado::class, 'idEstadoHisopadoSarm', 'idEstadoHisopadoSarm');
    }
}
