<?php

namespace Database\Seeders;

use App\Models\ObraSocial;
use App\Models\Persona;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MatiasRodriguezSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dniTipo = TipoDocumento::where('nombreTipoDocumento', 'DNI')->first();

        // Crear o buscar la obra social Osep
        $obraSocial = ObraSocial::firstOrCreate(
            ['nombreObraSocial' => 'Osep'],
            ['diasVigenciaOrden' => 30] // Valor por defecto
        );

        // Crear un plan simulado para Osep
        $plan = Plan::firstOrCreate(
            ['nombrePlan' => 'Plan Classic (Simulado)', 'idobrasocial' => $obraSocial->idObraSocial],
            ['es_sin_cobertura' => false]
        );

        // Crear la persona
        $persona = Persona::firstOrCreate(
            ['documento' => '44437211', 'tipo_documento_id' => $dniTipo->idTipoDocumento],
            [
                'nombres' => 'Matias',
                'apellidos' => 'Rodriguez',
                'fecha_nacimiento' => Carbon::create(1995, 5, 10), // Fecha simulada
                'genero' => 'M',
            ]
        );

        // Asignarle el plan
        PlanObraSocial::firstOrCreate(
            [
                'idPersona' => $persona->idPersona,
                'idPlan' => $plan->idPlan,
            ],
            [
                'nroBeneficiaroPlanObraSocial' => '12345678',
                'fechaInicioPlanObraSocial' => Carbon::now()->subYears(2),
            ]
        );
    }
}
