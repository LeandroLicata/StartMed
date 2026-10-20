<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
            'cambiosAuditoria' => 'array',
        ];
    }

    /**
     * La hora de la auditoria se guarda en UTC, a diferencia del resto del
     * esquema (hora argentina sin zona, ver config/app.php): un registro de
     * auditoria tiene que ser inequivoco aunque se lea fuera de la app, y no
     * depender de que el pais no vuelva a tener horario de verano.
     *
     * Hacia afuera se usa en la zona de la app como cualquier otra fecha: se
     * le asigna now() y se lee un Carbon en hora argentina. La conversion
     * pasa solo aca. Las filas escritas antes del cambio de zona ya estaban
     * en UTC (la app corria en UTC), asi que no hubo que migrarlas.
     */
    protected function fechaHoraAuditoria(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor) => $valor === null
                ? null
                : Carbon::parse($valor, 'UTC')->setTimezone(config('app.timezone')),
            set: fn (mixed $valor) => $valor === null
                ? null
                : ($valor instanceof DateTimeInterface ? Carbon::instance($valor) : Carbon::parse($valor))
                    ->utc()
                    ->format('Y-m-d H:i:s'),
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idUsuario', 'idUsuario');
    }
}
