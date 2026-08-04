<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(CatalogosSeeder::class);

        $dni = TipoDocumento::where('nombreTipoDocumento', 'DNI')->firstOrFail();

        $persona = Persona::firstOrCreate(
            ['tipo_documento_id' => $dni->idTipoDocumento, 'documento' => '30000000'],
            ['apellidos' => 'Admin', 'nombres' => 'Sistema'],
        );

        $personal = Personal::firstOrCreate(
            ['idPersona' => $persona->idPersona],
            ['legajoPersonal' => '0001'],
        );

        $personal->roles()->syncWithoutDetaching([
            Rol::where('nombreRol', 'Administrador')->value('idRol') => [
                'fechaHoraAsignacionRolPersonal' => now(),
            ],
        ]);

        Usuario::firstOrCreate(
            ['nombreUsuario' => 'admin'],
            ['idPersonal' => $personal->idPersonal, 'passwordUsuario' => 'admin1234'],
        );

        // Datos de demostracion: no correr en produccion.
        if (app()->environment('local')) {
            $this->call([
                DemoSeeder::class,
                ExpedienteSeeder::class,
                HistorialSeeder::class,
            ]);
        }
    }
}
