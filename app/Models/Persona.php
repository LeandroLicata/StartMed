<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Persona extends Model
{
    protected $table = 'Persona';

    protected $primaryKey = 'idPersona';

    public $timestamps = false;

    protected $guarded = ['idPersona'];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_edad_aproximada' => 'date',
            'fechaHoraBajaPersona' => 'datetime',
            'usar_edad_aproximada' => 'boolean',
            'tiene_incapacidad' => 'boolean',
            'es_cronico' => 'boolean',
            'no_acepta_donacion_sanguinea' => 'boolean',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellidos}, {$this->nombres}");
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id', 'idTipoDocumento');
    }

    public function grupoSanguineo(): BelongsTo
    {
        return $this->belongsTo(GrupoSanguineo::class, 'grupo_sanguineo_id', 'idGrupoSanguineo');
    }

    /**
     * El legajo de esta persona como miembro del staff, si lo tiene.
     */
    public function personal(): HasOne
    {
        return $this->hasOne(Personal::class, 'idPersona', 'idPersona');
    }

    /**
     * Cirugias en las que esta persona es el paciente.
     */
    public function cirugias(): HasMany
    {
        return $this->hasMany(Cirugia::class, 'idPersonaPaciente', 'idPersona');
    }

    public function planObraSociales(): HasMany
    {
        return $this->hasMany(PlanObraSocial::class, 'idPersona', 'idPersona');
    }
}
