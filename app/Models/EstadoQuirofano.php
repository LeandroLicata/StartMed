<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoQuirofano extends Model
{
    protected $table = 'EstadoQuirofano';

    protected $primaryKey = 'idEstadoQuirofano';

    public $timestamps = false;

    protected $fillable = [
        'nombreEstadoQuirofano',
        'fechaBajaEstadoQuirofano',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaEstadoQuirofano' => 'datetime',
        ];
    }

    public function quirofanoEstados(): HasMany
    {
        return $this->hasMany(QuirofanoEstado::class, 'idEstadoQuirofano', 'idEstadoQuirofano');
    }
}
