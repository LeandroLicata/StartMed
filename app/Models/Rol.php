<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'Rol';

    protected $primaryKey = 'idRol';

    public $timestamps = false;

    protected $fillable = ['nombreRol', 'fechaHoraBajaRol'];

    protected function casts(): array
    {
        return ['fechaHoraBajaRol' => 'datetime'];
    }

    public function rolPersonales(): HasMany
    {
        return $this->hasMany(RolPersonal::class, 'idRol', 'idRol');
    }

    public function cirugiaPersonales(): HasMany
    {
        return $this->hasMany(CirugiaPersonal::class, 'idRol', 'idRol');
    }

    public function personales(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'RolPersonal', 'idRol', 'idPersonal')
            ->withPivot([
                'idRolPersonal',
                'fechaHoraAsignacionRolPersonal',
                'fechaHoraBajaAsignacionRolPersonal',
            ]);
    }
}
