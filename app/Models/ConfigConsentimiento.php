<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigConsentimiento extends Model
{
    protected $table = 'ConfigConsentimiento';

    protected $primaryKey = 'idConfigConsentimiento';

    public $timestamps = false;

    protected $fillable = [
        'idTipoCirugia',
        'textoConfigConsentimiento',
        'fechaInicioConfigConsentimiento',
        'fechaFinConfigConsentimiento',
    ];

    protected function casts(): array
    {
        return [
            'fechaInicioConfigConsentimiento' => 'datetime',
            'fechaFinConfigConsentimiento' => 'datetime',
        ];
    }

    public function tipoCirugia(): BelongsTo
    {
        return $this->belongsTo(TipoCirugia::class, 'idTipoCirugia', 'idTipoCirugia');
    }

    public function consentimientoPacientes(): HasMany
    {
        return $this->hasMany(ConsentimientoPaciente::class, 'idConfigConsentimiento', 'idConfigConsentimiento');
    }
}
