<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    protected $table = 'TipoDocumento';

    protected $primaryKey = 'idTipoDocumento';

    public $timestamps = false;

    protected $fillable = ['nombreTipoDocumento', 'fechaBajaTipoDocumento'];

    protected function casts(): array
    {
        return ['fechaBajaTipoDocumento' => 'datetime'];
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'tipo_documento_id', 'idTipoDocumento');
    }
}
