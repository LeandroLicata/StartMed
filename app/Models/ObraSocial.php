<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObraSocial extends Model
{
    protected $table = 'ObraSocial';

    protected $primaryKey = 'idObraSocial';

    public $timestamps = false;

    protected $fillable = [
        'nombreObraSocial',
        'telefonoObraSocial',
        'emailObraSocial',
        'diasVigenciaOrden',
        'fechaBajaObraSocial',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaObraSocial' => 'datetime',
        ];
    }

    public function planes(): HasMany
    {
        return $this->hasMany(Plan::class, 'idobrasocial', 'idObraSocial');
    }
}
