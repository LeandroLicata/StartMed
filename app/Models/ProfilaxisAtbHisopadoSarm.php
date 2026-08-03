<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfilaxisAtbHisopadoSarm extends Model
{
    protected $table = 'ProfilaxisAtbHisopadoSarm';

    protected $primaryKey = 'idProfilaxisAtbHisopadoSarm';

    public $timestamps = false;

    protected $fillable = [
        'idHisopadoSarm',
        'alertaProfilaxisAtbHisopadoSarm',
        'motivoProfilaxisAtbHisopadoSarm',
    ];

    public function hisopadoSarm(): BelongsTo
    {
        return $this->belongsTo(HisopadoSarm::class, 'idHisopadoSarm', 'idHisopadoSarm');
    }

    public function profilaxisAtbHisopadoSarmProfilaxis(): HasMany
    {
        return $this->hasMany(ProfilaxisAtbHisopadoSarmProfilaxis::class, 'idProfilaxisAtbHisopadoSarm', 'idProfilaxisAtbHisopadoSarm');
    }
}
