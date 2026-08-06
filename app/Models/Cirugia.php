<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cirugia extends Model
{
    protected $table = 'Cirugia';

    protected $primaryKey = 'idCirugia';

    public $timestamps = false;

    protected $fillable = [
        'idPersonaPaciente',
        'idTipoCirugia',
        'idPersonalCirujano',
        'idPersonalAnestesista',
        'fechaHoraCirugia',
        'fechaHoraFinCirugia',
        'descripcionCirugia',
        'observacionesPaciente',
        'requiereImplante',
    ];

    protected function casts(): array
    {
        return [
            'fechaHoraCirugia' => 'datetime',
            'fechaHoraFinCirugia' => 'datetime',
            'requiereImplante' => 'boolean',
        ];
    }

    public function anestesista(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'idPersonalAnestesista', 'idPersonal');
    }

    public function cirujano(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'idPersonalCirujano', 'idPersonal');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'idPersonaPaciente', 'idPersona');
    }

    public function tipoCirugia(): BelongsTo
    {
        return $this->belongsTo(TipoCirugia::class, 'idTipoCirugia', 'idTipoCirugia');
    }

    public function autCirugias(): HasMany
    {
        return $this->hasMany(AutCirugia::class, 'idCirugia', 'idCirugia');
    }

    public function cirugiaEstados(): HasMany
    {
        return $this->hasMany(CirugiaEstado::class, 'idCirugia', 'idCirugia');
    }

    public function cirugiaPersonales(): HasMany
    {
        return $this->hasMany(CirugiaPersonal::class, 'idCirugia', 'idCirugia');
    }

    public function cirugiaQuirofanos(): HasMany
    {
        return $this->hasMany(CirugiaQuirofano::class, 'idCirugia', 'idCirugia');
    }

    public function cirugiaTipoEstudios(): HasMany
    {
        return $this->hasMany(CirugiaTipoEstudio::class, 'idCirugia', 'idCirugia');
    }

    public function consentimientoPacientes(): HasMany
    {
        return $this->hasMany(ConsentimientoPaciente::class, 'idCirugia', 'idCirugia');
    }

    public function evaluacionAnestesicas(): HasMany
    {
        return $this->hasMany(EvaluacionAnestesica::class, 'idCirugia', 'idCirugia');
    }

    public function examenCirugiaPreAnestesicas(): HasMany
    {
        return $this->hasMany(ExamenCirugiaPreAnestesica::class, 'idCirugia', 'idCirugia');
    }

    public function hisopadoSarms(): HasMany
    {
        return $this->hasMany(HisopadoSarm::class, 'idCirugia', 'idCirugia');
    }

    public function pedidoHemoderivados(): HasMany
    {
        return $this->hasMany(PedidoHemoderivado::class, 'idCirugia', 'idCirugia');
    }

    public function pedidoMateriales(): HasMany
    {
        return $this->hasMany(PedidoMaterial::class, 'idCirugia', 'idCirugia');
    }

    public function preparacionPacientes(): HasMany
    {
        return $this->hasMany(PreparacionPaciente::class, 'idCirugia', 'idCirugia');
    }
}
