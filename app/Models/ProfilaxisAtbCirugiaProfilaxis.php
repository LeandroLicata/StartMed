<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilaxisAtbCirugiaProfilaxis extends Model
{
    protected $table = 'ProfilaxisAtbCirugiaProfilaxis';

    protected $primaryKey = 'idProfilaxisAtbCirugiaProfilaxis';

    public $timestamps = false;

    protected $fillable = [
        'idProfilaxisAtbCirugia',
        'idProfilaxisRol',
        'idProfilaxis',
        'indicacionesProfilaxisAtbCirugiaProfilaxis',
    ];

    public function profilaxis(): BelongsTo
    {
        return $this->belongsTo(Profilaxis::class, 'idProfilaxis', 'idProfilaxis');
    }

    public function profilaxisAtbCirugia(): BelongsTo
    {
        return $this->belongsTo(ProfilaxisAtbCirugia::class, 'idProfilaxisAtbCirugia', 'idProfilaxisAtbCirugia');
    }

    public function profilaxisRol(): BelongsTo
    {
        return $this->belongsTo(ProfilaxisRol::class, 'idProfilaxisRol', 'idProfilaxisRol');
    }
}
