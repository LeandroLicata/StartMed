<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoCirugia extends Model
{
    protected $table = 'EstadoCirugia';

    protected $primaryKey = 'idEstadoCirugia';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoCirugia',
        'fechaBajaEstadoCirugia',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoCirugia' => 'datetime',
        ];
    }

    public function cirugiaEstados(): HasMany
    {
        return $this->hasMany(CirugiaEstado::class, 'idEstadoCirugia', 'idEstadoCirugia');
    }
}
