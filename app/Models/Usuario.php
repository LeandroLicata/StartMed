<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['idPersonal', 'nombreUsuario', 'passwordUsuario', 'fechaBajaUsuario'])]
#[Hidden(['passwordUsuario'])]
class Usuario extends Authenticatable
{
    protected $table = 'Usuario';

    protected $primaryKey = 'idUsuario';

    /**
     * El esquema no tiene created_at / updated_at.
     */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'passwordUsuario' => 'hashed',
            'fechaBajaUsuario' => 'datetime',
        ];
    }

    /**
     * El guard de sesion busca la contrasena aca en vez de en `password`.
     */
    public function getAuthPassword(): string
    {
        return $this->passwordUsuario;
    }

    /**
     * `Usuario` no tiene columna remember_token, asi que se deshabilita el
     * "recordarme". Devolver null hace que el SessionGuard lo saltee.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function estaDadoDeBaja(): bool
    {
        return $this->fechaBajaUsuario !== null;
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'idPersonal', 'idPersonal');
    }
}
