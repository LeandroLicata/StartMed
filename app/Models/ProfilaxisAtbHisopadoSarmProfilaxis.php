<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilaxisAtbHisopadoSarmProfilaxis extends Model
{
    protected $table = 'ProfilaxisAtbHisopadoSarmProfilaxis';

    protected $primaryKey = 'idProfilaxisAtbHisopadoSarmProfilaxis';

    public $timestamps = false;

    protected $fillable = [
        'idProfilaxisAtbHisopadoSarm',
        'idProfilaxisRol',
        'idProfilaxis',
        'indicacionesProfilaxisAtbHisopadoSarmProfilaxis',
    ];

    public function profilaxis(): BelongsTo
    {
        return $this->belongsTo(Profilaxis::class, 'idProfilaxis', 'idProfilaxis');
    }

    public function profilaxisAtbHisopadoSarm(): BelongsTo
    {
        return $this->belongsTo(ProfilaxisAtbHisopadoSarm::class, 'idProfilaxisAtbHisopadoSarm', 'idProfilaxisAtbHisopadoSarm');
    }

    public function profilaxisRol(): BelongsTo
    {
        return $this->belongsTo(ProfilaxisRol::class, 'idProfilaxisRol', 'idProfilaxisRol');
    }
}
