<?php

namespace Database\Seeders;

use App\Models\GrupoSanguineo;
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
        foreach (['DNI', 'Pasaporte', 'Libreta Civica', 'Libreta de Enrolamiento'] as $nombre) {
            TipoDocumento::firstOrCreate(['nombreTipoDocumento' => $nombre]);
        }

        foreach (['0-', '0+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'] as $nombre) {
            GrupoSanguineo::firstOrCreate(['nombreGrupoSanguineo' => $nombre]);
        }

        foreach (['Administrador', 'Cirujano', 'Anestesista', 'Instrumentador', 'Enfermero'] as $nombre) {
            Rol::firstOrCreate(['nombreRol' => $nombre]);
        }

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
    }
}
