<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Personal extends Model
{
    protected $table = 'Personal';

    protected $primaryKey = 'idPersonal';

    public $timestamps = false;

    protected $fillable = [
        'idPersona',
        'matriculaProvincial',
        'matriculaNacional',
        'legajoPersonal',
        'mailInstitucional',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class, 'idPersonal', 'idPersonal');
    }

    /**
     * Cirugias donde figura como cirujano titular (Cirugia.idPersonalCirujano).
     * Para el equipo completo de una cirugia ver CirugiaPersonal.
     */
    public function cirugiasComoCirujano(): HasMany
    {
        return $this->hasMany(Cirugia::class, 'idPersonalCirujano', 'idPersonal');
    }

    public function cirugiasComoAnestesista(): HasMany
    {
        return $this->hasMany(Cirugia::class, 'idPersonalAnestesista', 'idPersonal');
    }

    public function cirugiaPersonales(): HasMany
    {
        return $this->hasMany(CirugiaPersonal::class, 'idPersonal', 'idPersonal');
    }

    public function rolPersonales(): HasMany
    {
        return $this->hasMany(RolPersonal::class, 'idPersonal', 'idPersonal');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'RolPersonal', 'idPersonal', 'idRol')
            ->withPivot([
                'idRolPersonal',
                'fechaHoraAsignacionRolPersonal',
                'fechaHoraBajaAsignacionRolPersonal',
            ]);
    }

    /**
     * Solo los roles cuya asignacion sigue vigente.
     */
    public function rolesVigentes(): BelongsToMany
    {
        return $this->roles()->wherePivotNull('fechaHoraBajaAsignacionRolPersonal');
    }

    /**
     * Deja vigentes exactamente los roles indicados.
     *
     * No usa sync(): RolPersonal es historial, no una pivote comun. Quitar un
     * rol es cerrar su asignacion con una fecha de baja, nunca borrar la fila,
     * asi queda registrado quien tuvo que rol y hasta cuando.
     *
     * @param  list<int>  $idsRol
     */
    public function sincronizarRoles(array $idsRol): void
    {
        $vigentes = $this->rolesVigentes()->pluck('Rol.idRol');

        RolPersonal::query()
            ->where('idPersonal', $this->idPersonal)
            ->whereNotIn('idRol', $idsRol)
            ->whereNull('fechaHoraBajaAsignacionRolPersonal')
            ->update(['fechaHoraBajaAsignacionRolPersonal' => now()]);

        $this->roles()->attach(
            collect($idsRol)->diff($vigentes)->all(),
            ['fechaHoraAsignacionRolPersonal' => now()],
        );

        $this->unsetRelation('roles')->unsetRelation('rolesVigentes');
    }
}
