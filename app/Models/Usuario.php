<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

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

    /**
     * Nombres de los roles vigentes del usuario.
     *
     * @return Collection<int, string>
     */
    public function roles(): Collection
    {
        return $this->personal?->rolesVigentes->pluck('nombreRol') ?? collect();
    }

    /**
     * El administrador entra a todas las secciones sin necesidad de tener
     * asignado cada rol funcional.
     */
    public function tieneRol(string ...$roles): bool
    {
        $propios = $this->roles();

        return $propios->contains('Administrador')
            || $propios->intersect($roles)->isNotEmpty();
    }

    /**
     * Panel al que se manda al usuario despues de iniciar sesion. Cada rol
     * tiene el suyo, asi que no se puede mandar a todos al mismo lado.
     *
     * Devuelve null si el usuario no tiene ningun rol que le de acceso.
     */
    public function rutaInicial(): ?string
    {
        return match (true) {
            // Va primero: tieneRol() da true en cualquier consulta para un
            // administrador, asi que si no, aterrizaria en el tablero de gestion.
            $this->tieneRol('Administrador') => 'admin.inicio',
            $this->tieneRol('Gestor de quirófano', 'Dirección médica') => 'dashboard',
            $this->tieneRol('Cirujano') => 'cirujano',
            $this->tieneRol('Anestesista') => 'anestesista',
            default => null,
        };
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'idPersonal', 'idPersonal');
    }
}
