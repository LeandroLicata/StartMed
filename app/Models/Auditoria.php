<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una escritura hecha desde la seccion de administracion. Las graba
 * App\Support\Auditor; aca solo viven la forma de la fila y su lectura.
 */
class Auditoria extends Model
{
    protected $table = 'Auditoria';

    protected $primaryKey = 'idAuditoria';

    public $timestamps = false;

    protected $fillable = [
        'idUsuario',
        'fechaHoraAuditoria',
        'accionAuditoria',
        'tablaAuditoria',
        'idRegistroAuditoria',
        'descripcionAuditoria',
        'cambiosAuditoria',
    ];

    protected function casts(): array
    {
        return [
            'fechaHoraAuditoria' => 'datetime',
            'cambiosAuditoria' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idUsuario', 'idUsuario');
    }
}
