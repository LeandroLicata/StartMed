<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutCirugia extends Model
{
    protected $table = 'AutCirugia';

    protected $primaryKey = 'idAutCirugia';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'idPlan',
        'fechaLimiteEnvioAutorizacion',
        'fechaHoraEnvioAutorizacionAutCirugia',
        'fechaHoraVerificacionAutCirugia',
        'nroAprobacionAutCirugia',
    ];

    protected function casts(): array
    {
        return [
            'fechaLimiteEnvioAutorizacion' => 'date',
            'fechaHoraEnvioAutorizacionAutCirugia' => 'datetime',
            'fechaHoraVerificacionAutCirugia' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'idPlan', 'idPlan');
    }

    public function autoCirugiaEstados(): HasMany
    {
        return $this->hasMany(AutoCirugiaEstado::class, 'idAutCirugia', 'idAutCirugia');
    }
}
