<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentimientoPaciente extends Model
{
    protected $table = 'ConsentimientoPaciente';

    protected $primaryKey = 'idConsentimiento';

    public $timestamps = false;

    protected $fillable = [
        'idCirugia',
        'idConfigConsentimiento',
        'textoRenderizadoConsentimiento',
        'hashConsentimiento',
        'fechaFirmaConsentimiento',
        'firmaConsentimiento',
        'ipFirmaConsentimiento',
        'userAgentFirmaConsentimiento',
        'urlPdfConsentimiento',
    ];

    protected function casts(): array
    {
        return [
            'fechaFirmaConsentimiento' => 'datetime',
        ];
    }

    public function cirugia(): BelongsTo
    {
        return $this->belongsTo(Cirugia::class, 'idCirugia', 'idCirugia');
    }

    public function configConsentimiento(): BelongsTo
    {
        return $this->belongsTo(ConfigConsentimiento::class, 'idConfigConsentimiento', 'idConfigConsentimiento');
    }
}
