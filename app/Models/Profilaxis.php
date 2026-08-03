<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profilaxis extends Model
{
    protected $table = 'Profilaxis';

    protected $primaryKey = 'idProfilaxis';

    public $timestamps = false;

    protected $fillable = [
        'nombreProfilaxis',
        'fechaBajaProfilaxis',
    ];

    protected function casts(): array
    {
        return [
            'fechaBajaProfilaxis' => 'datetime',
        ];
    }

    public function profilaxisAtbHisopadoSarmProfilaxis(): HasMany
    {
        return $this->hasMany(ProfilaxisAtbHisopadoSarmProfilaxis::class, 'idProfilaxis', 'idProfilaxis');
    }
}
