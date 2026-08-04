<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCirugia extends Model
{
    protected $table = 'TipoCirugia';

    protected $primaryKey = 'idTipoCirugia';

    public $timestamps = false;

    protected $fillable = [
        'nombreTipoCirugia',
        'descripcionTipoCirugia',
        'fechaBajaTipoCirugia',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaTipoCirugia' => 'datetime',
        ];
    }

    public function cirugias(): HasMany
    {
        return $this->hasMany(Cirugia::class, 'idTipoCirugia', 'idTipoCirugia');
    }

    public function configConsentimientos(): HasMany
    {
        return $this->hasMany(ConfigConsentimiento::class, 'idTipoCirugia', 'idTipoCirugia');
    }
}
