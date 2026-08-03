<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HisopadoSarm extends Model
{
    protected $table = 'HisopadoSarm';

    protected $primaryKey = 'idHisopadoSarm';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'idEstablecimiento',
        'fechaSolicitacionHisopadoSarm',
        'fechaEstimadaResultadosHisopadoSarm',
        'observacionesHisopadoSarm',
        'urlHisopadoSarm',
    ];

    protected function casts(): array
    {
        return [
            'fechaSolicitacionHisopadoSarm' => 'datetime',
            'fechaEstimadaResultadosHisopadoSarm' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'idEstablecimiento', 'idEstablecimiento');
    }

    public function hisopadoSarmEstados(): HasMany
    {
        return $this->hasMany(HisopadoSarmEstado::class, 'idHisopadoSarm', 'idHisopadoSarm');
    }

    public function profilaxisAtbHisopadoSarms(): HasMany
    {
        return $this->hasMany(ProfilaxisAtbHisopadoSarm::class, 'idHisopadoSarm', 'idHisopadoSarm');
    }
}
