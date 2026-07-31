<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolPersonal extends Model
{
    protected $table = 'RolPersonal';

    protected $primaryKey = 'idRolPersonal';

    public $timestamps = false;

    protected $fillable = [
        'idPersonal',
        'idRol',
        'fechaHoraAsignacionRolPersonal',
        'fechaHoraBajaAsignacionRolPersonal',
    ];

    protected function casts(): array
    {
        return [
            'fechaHoraAsignacionRolPersonal' => 'datetime',
            'fechaHoraBajaAsignacionRolPersonal' => 'datetime',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'idPersonal', 'idPersonal');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'idRol', 'idRol');
    }
}
