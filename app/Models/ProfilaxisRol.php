<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfilaxisRol extends Model
{
    protected $table = 'ProfilaxisRol';

    protected $primaryKey = 'idProfilaxisRol';

    public $timestamps = false;

    protected $fillable = [
        'nombreProfilaxisRol',
        'fechaBajaProfilaxisRol',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaProfilaxisRol' => 'datetime',
        ];
    }

    public function profilaxisAtbHisopadoSarmProfilaxis(): HasMany
    {
        return $this->hasMany(ProfilaxisAtbHisopadoSarmProfilaxis::class, 'idProfilaxisRol', 'idProfilaxisRol');
    }
}
