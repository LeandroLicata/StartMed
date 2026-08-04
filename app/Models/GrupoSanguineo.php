<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoSanguineo extends Model
{
    protected $table = 'GrupoSanguineo';

    protected $primaryKey = 'idGrupoSanguineo';

    public $timestamps = false;

    protected $fillable = ['nombreGrupoSanguineo', 'fechaBajaGrupoSanguineo'];

    protected function casts(): array
    {
        return ['fechaBajaGrupoSanguineo' => 'datetime'];
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'grupo_sanguineo_id', 'idGrupoSanguineo');
    }
}
