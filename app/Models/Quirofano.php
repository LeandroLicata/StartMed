<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quirofano extends Model
{
    protected $table = 'Quirofano';

    protected $primaryKey = 'idQuirofano';

    public $timestamps = false;

    protected $fillable = [
        'nroQuirofano',
        'nombreQuirofano',
        'fechaBajaQuirofano',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaQuirofano' => 'datetime',
        ];
    }

    public function cirugiaQuirofanos(): HasMany
    {
        return $this->hasMany(CirugiaQuirofano::class, 'idQuirofano', 'idQuirofano');
    }

    public function quirofanoEstados(): HasMany
    {
        return $this->hasMany(QuirofanoEstado::class, 'idQuirofano', 'idQuirofano');
    }
}
