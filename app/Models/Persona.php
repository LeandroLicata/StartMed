<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tambien es lo que autentica el guard `paciente` (portal del paciente).
 *
 * Un paciente es una Persona sin legajo, asi que no puede entrar por Usuario,
 * que cuelga de Personal. El portal lo identifica por su Persona: sin
 * contrasena (el acceso es por link al email) y sin tablas nuevas.
 */
class Persona extends Model implements AuthenticatableContract
{
    use Authenticatable;

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

    /**
     * Persona no tiene columna remember_token: "recordarme" no aplica.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
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
