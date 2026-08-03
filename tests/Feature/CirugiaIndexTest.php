<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\Usuario;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\HistorialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CirugiaIndexTest extends TestCase
{
    use RefreshDatabase;

    private function conDatosDemo(): Usuario
    {
        $this->seed(CatalogosSeeder::class);
        $this->seed(DemoSeeder::class);

        return Usuario::where('nombreUsuario', 'gonzalez')->firstOrFail();
    }

    public function test_un_invitado_no_puede_acceder_al_listado(): void
    {
        $this->get('/cirugias')->assertRedirect(route('login'));
    }

    public function test_un_rol_distinto_de_gestor_no_puede_acceder(): void
    {
        $this->conDatosDemo();
        $cirujano = Usuario::where('nombreUsuario', 'perez')->firstOrFail();

        $this->actingAs($cirujano)->get('/cirugias')->assertForbidden();
    }

    public function test_el_listado_no_esta_acotado_a_la_semana_actual(): void
    {
        $usuario = $this->conDatosDemo();
        $this->seed(HistorialSeeder::class);

        // Ramirez esta 2 dias despues de hoy: puede caer fuera de "esta
        // semana" segun el dia. Se busca por nombre para no depender de en
        // que pagina cae entre cientos de cirugias historicas.
        $this->actingAs($usuario)
            ->get('/cirugias')
            ->assertOk()
            ->assertSee('Nueva cirugía');

        $this->actingAs($usuario)
            ->get('/cirugias?q=Ramírez')
            ->assertOk()
            ->assertSee('Ramírez, Luis');
    }

    public function test_el_listado_se_puede_filtrar_igual_que_el_tablero(): void
    {
        $usuario = $this->conDatosDemo();

        $this->actingAs($usuario)
            ->get('/cirugias?estado=En+riesgo')
            ->assertOk()
            ->assertSee('López, Ramiro')
            ->assertDontSee('García, María');
    }

    public function test_el_boton_nueva_cirugia_lleva_al_alta(): void
    {
        $usuario = $this->conDatosDemo();

        $this->actingAs($usuario)
            ->get('/cirugias')
            ->assertOk()
            ->assertSee(route('cirugias.crear'));
    }

    public function test_el_listado_pagina_cuando_hay_muchas_cirugias(): void
    {
        $usuario = $this->conDatosDemo();
        $this->seed(HistorialSeeder::class);

        $this->assertGreaterThan(20, Cirugia::count());

        $primeraPagina = $this->actingAs($usuario)->get('/cirugias')->assertOk();
        $primeraPagina->assertSee('aria-label="Paginación"', false);

        $segundaPagina = $this->actingAs($usuario)->get('/cirugias?page=2')->assertOk();

        // Las dos paginas devuelven contenido distinto.
        $this->assertNotSame($primeraPagina->getContent(), $segundaPagina->getContent());
    }
}
