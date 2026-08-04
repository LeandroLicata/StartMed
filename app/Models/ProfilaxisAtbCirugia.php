<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfilaxisAtbCirugia extends Model
{
    protected $table = 'ProfilaxisAtbCirugia';

    protected $primaryKey = 'idProfilaxisAtbCirugia';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'alertaProfilaxisAtbCirugia',
        'motivoProfilaxisAtbCirugia',
    ];

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function profilaxisAtbCirugiaProfilaxis(): HasMany
    {
        return $this->hasMany(ProfilaxisAtbCirugiaProfilaxis::class, 'idProfilaxisAtbCirugia', 'idProfilaxisAtbCirugia');
    }
}
