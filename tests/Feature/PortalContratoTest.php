<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\AccesoController;
use App\Models\Cirugia;
use App\Models\Persona;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El contrato del portal del paciente: guard propio, puerta propia y
 * pertenencia de la cirugia. Es lo que las demas pantallas del portal dan por
 * sentado, asi que se prueba aparte de cualquiera de ellas.
 */
class PortalContratoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    private function maria(): Persona
    {
        return Persona::where('documento', AccesoController::DOCUMENTO_DEMO)->firstOrFail();
    }

    public function test_sin_sesion_el_portal_manda_a_su_propia_puerta_y_no_al_login_del_staff(): void
    {
        $this->get('/portal')->assertRedirect(route('portal.ingresar'));
        $this->get('/portal/ingresar')->assertOk();

        // Las pantallas del staff siguen yendo a su login.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_el_paciente_entra_al_portal_pero_no_a_las_pantallas_del_staff(): void
    {
        $this->actingAs($this->maria(), 'paciente');

        $this->get('/portal')->assertOk()->assertSee('García, María');
        $this->get('/portal/historial')->assertOk();
        $this->get('/portal/perfil')->assertOk();

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_una_sesion_de_staff_no_abre_el_portal(): void
    {
        $this->actingAs(Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail());

        $this->get('/portal')->assertRedirect(route('portal.ingresar'));
    }

    public function test_el_paciente_ve_su_cirugia_y_las_ajenas_dan_404(): void
    {
        $maria = $this->maria();
        $propia = Cirugia::where('idPersonaPaciente', $maria->idPersona)->firstOrFail();
        $ajena = Cirugia::where('idPersonaPaciente', '!=', $maria->idPersona)->firstOrFail();

        $this->actingAs($maria, 'paciente');

        $this->get("/portal/cirugias/{$propia->idCirugia}")->assertOk();
        // Ajena e inexistente responden igual: no se confirma que la cirugia exista.
        $this->get("/portal/cirugias/{$ajena->idCirugia}")->assertNotFound();
        $this->get('/portal/cirugias/999999')->assertNotFound();
    }

    public function test_toda_ruta_con_cirugia_verifica_la_pertenencia(): void
    {
        $sinVerificar = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'portal/') && in_array('cirugia', $ruta->parameterNames()))
            ->reject(fn ($ruta) => in_array('cirugia.del.paciente', $ruta->gatherMiddleware()))
            ->map(fn ($ruta) => $ruta->uri());

        $this->assertEmpty($sinVerificar, 'Rutas del portal con {cirugia} fuera del subgrupo que verifica la pertenencia: '.$sinVerificar->implode(', '));
    }

    public function test_salir_cierra_solo_la_sesion_del_paciente(): void
    {
        $this->actingAs(Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail());
        $this->actingAs($this->maria(), 'paciente');

        $this->post('/portal/salir')->assertRedirect(route('portal.ingresar'));

        $this->assertGuest('paciente');
        $this->assertAuthenticated('web');
    }

    public function test_el_atajo_de_desarrollo_no_existe_fuera_de_local(): void
    {
        $this->assertFalse(Route::has('portal.entrar-local'));
        $this->get('/portal/entrar-local')->assertNotFound();
    }
}
