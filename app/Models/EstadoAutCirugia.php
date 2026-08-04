<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoAutCirugia extends Model
{
    protected $table = 'EstadoAutCirugia';

    protected $primaryKey = 'idEstadoAutCirugia';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoAutCirugia',
        'fechaBajaEstadoAutCirugia',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoAutCirugia' => 'datetime',
        ];
    }

    public function autoCirugiaEstados(): HasMany
    {
        return $this->hasMany(AutoCirugiaEstado::class, 'idEstadoAutCirugia', 'idEstadoAutCirugia');
    }
}
