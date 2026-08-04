<?php

namespace Tests\Feature;

use App\Models\Cirugia;
use App\Models\CirugiaTipoEstudio;
use App\Models\Establecimiento;
use App\Models\EstadoAutCirugia;
use App\Models\EstadoPedidoTipoHemoderivado;
use App\Models\HisopadoSarm;
use App\Models\PedidoTipoHemoderivado;
use App\Models\TipoEstudio;
use App\Models\TipoHemoderivado;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CirugiaAccionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(DemoSeeder::class);
    }

    private function usuario(string $nombre): Usuario
    {
        return Usuario::where('nombreUsuario', $nombre)->firstOrFail();
    }

    /** Caso con hisopado "Pendiente" (Vidal, Jorge no trae profilaxis en la demo). */
    private function cirugiaConHisopadoPendiente(): Cirugia
    {
        return Cirugia::whereHas('paciente', fn ($q) => $q->where('apellidos', 'Vidal'))->firstOrFail();
    }

    /** Caso con reserva de hemoderivados (Ramírez, Luis). */
    private function cirugiaConHemoderivados(): Cirugia
    {
        return Cirugia::whereHas('paciente', fn ($q) => $q->where('apellidos', 'Ramírez'))->firstOrFail();
    }

    private function otraCirugia(Cirugia $distintaDe): Cirugia
    {
        return Cirugia::where('idCirugia', '!=', $distintaDe->idCirugia)->firstOrFail();
    }

    // --- Hisopado -----------------------------------------------------------

    public function test_el_gestor_actualiza_los_datos_del_hisopado(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $laboratorio = Establecimiento::create(['nombreEstablecimiento' => 'Laboratorio Central']);

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado", [
                'idEstablecimiento' => $laboratorio->idEstablecimiento,
                'observacionesHisopadoSarm' => 'Muestra tomada en preadmisión.',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Muestra tomada en preadmisión.',
            HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->value('observacionesHisopadoSarm'),
        );
    }

    public function test_el_anestesista_no_puede_actualizar_los_datos_del_hisopado(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();

        $this->actingAs($this->usuario('ramos'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado", ['observacionesHisopadoSarm' => 'x'])
            ->assertForbidden();
    }

    public function test_el_cirujano_registra_el_resultado_del_hisopado_y_cierra_el_estado_anterior(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $hisopado = HisopadoSarm::where('idCirugia', $cirugia->idCirugia)->firstOrFail();
        $estadoVigente = $hisopado->hisopadoSarmEstados()->whereNull('fechaFinAsignacionHisopadoSarmEstado')->firstOrFail();

        $this->actingAs($this->usuario('perez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/hisopado/estado", ['estado' => 'Negativo'])
            ->assertRedirect();

        $estadoVigente->refresh();
        $this->assertNotNull($estadoVigente->fechaFinAsignacionHisopadoSarmEstado);

        $nuevoEstado = $hisopado->hisopadoSarmEstados()->whereNull('fechaFinAsignacionHisopadoSarmEstado')->firstOrFail();
        $this->assertSame('Negativo', $nuevoEstado->estadoHisopadoSarm->nombreEstadoHisopadoSarm);
    }

    // --- Autorización ---------------------------------------------------------

    public function test_el_gestor_actualiza_el_estado_de_la_autorizacion(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $idAprobada = EstadoAutCirugia::where('nombreEstadoAutCirugia', 'Aprobada')->value('idEstadoAutCirugia');

        $this->actingAs($this->usuario('gonzalez'))
            ->patch("/cirugias/{$cirugia->idCirugia}/autorizacion/estado", [
                'idEstadoAutCirugia' => $idAprobada,
                'observacionesAutoCirugiaEstado' => 'Confirmada por el financiador.',
            ])
            ->assertRedirect();

        $autorizacion = $cirugia->autCirugias()->firstOrFail();
        $vigente = $autorizacion->autoCirugiaEstados()->whereNull('fechaFinAutoCirugiaEstado')->firstOrFail();
        $this->assertSame($idAprobada, $vigente->idEstadoAutCirugia);
    }

    public function test_el_anestesista_no_puede_actualizar_la_autorizacion(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $idAprobada = EstadoAutCirugia::where('nombreEstadoAutCirugia', 'Aprobada')->value('idEstadoAutCirugia');

        $this->actingAs($this->usuario('ramos'))
            ->patch("/cirugias/{$cirugia->idCirugia}/autorizacion/estado", ['idEstadoAutCirugia' => $idAprobada])
            ->assertForbidden();
    }

    // --- Estudios prequirúrgicos ----------------------------------------------

    public function test_el_anestesista_puede_agregar_un_estudio(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $idTipoEstudio = TipoEstudio::where('nombreTipoEstudio', 'Coagulograma')->value('idTipoEstudio');

        $this->actingAs($this->usuario('ramos'))
            ->post("/cirugias/{$cirugia->idCirugia}/estudios", ['idTipoEstudio' => $idTipoEstudio])
            ->assertRedirect();

        $this->assertDatabaseHas('CirugiaTipoEstudio', [
            'idCirugia' => $cirugia->idCirugia,
            'idTipoEstudio' => $idTipoEstudio,
        ]);
    }

    public function test_no_se_puede_agregar_el_mismo_estudio_dos_veces(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $idTipoEstudio = CirugiaTipoEstudio::where('idCirugia', $cirugia->idCirugia)->value('idTipoEstudio');

        $this->actingAs($this->usuario('gonzalez'))
            ->post("/cirugias/{$cirugia->idCirugia}/estudios", ['idTipoEstudio' => $idTipoEstudio])
            ->assertSessionHas('error');

        $this->assertSame(
            1,
            CirugiaTipoEstudio::where('idCirugia', $cirugia->idCirugia)->where('idTipoEstudio', $idTipoEstudio)->count(),
        );
    }

    public function test_actualizar_un_estudio_que_no_pertenece_a_la_cirugia_da_404(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $otra = $this->otraCirugia($cirugia);
        $estudioDeOtraCirugia = CirugiaTipoEstudio::where('idCirugia', $otra->idCirugia)->firstOrFail();

        $this->actingAs($this->usuario('gonzalez'))
            ->patch(
                "/cirugias/{$cirugia->idCirugia}/estudios/{$estudioDeOtraCirugia->idCirugiaTipoEstudio}",
                ['resultadoCirugiaTipoEstudio' => 'Sin particularidades'],
            )
            ->assertNotFound();
    }

    // --- Hemoderivados ----------------------------------------------------------

    public function test_el_anestesista_crea_un_pedido_de_hemoderivados(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $banco = Establecimiento::create(['nombreEstablecimiento' => 'Banco de Sangre']);
        $idTipoHemoderivado = TipoHemoderivado::where('nombreTipoHemoderivado', 'Plasma fresco congelado')->value('idTipoHemoderivado');

        $this->actingAs($this->usuario('ramos'))
            ->post("/cirugias/{$cirugia->idCirugia}/hemoderivados", [
                'observacionesPedidoHemoderivados' => 'Reserva preventiva.',
                'componentes' => [
                    ['idTipoHemoderivado' => $idTipoHemoderivado, 'idEstablecimiento' => $banco->idEstablecimiento, 'cantidad' => 2],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('PedidoHemoderivado', ['idCirugia' => $cirugia->idCirugia]);
        $this->assertDatabaseHas('PedidoTipoHemoderivado', [
            'idTipoHemoderivado' => $idTipoHemoderivado,
            'cantidadPedidoTipoHemoderivado' => 2,
        ]);
    }

    public function test_el_anestesista_actualiza_el_estado_de_un_componente_de_hemoderivado(): void
    {
        $cirugia = $this->cirugiaConHemoderivados();
        $componente = PedidoTipoHemoderivado::whereHas(
            'pedidoHemoderivado',
            fn ($q) => $q->where('idCirugia', $cirugia->idCirugia),
        )->firstOrFail();
        $idReservado = EstadoPedidoTipoHemoderivado::where('nombreEstadoPedidoTipoHemoderivado', 'Reservado')->value('idEstadoPedidoTipoHemoderivado');

        $this->actingAs($this->usuario('ramos'))
            ->patch(
                "/cirugias/{$cirugia->idCirugia}/hemoderivados/{$componente->idPedidoTipoHemoderivado}/estado",
                ['idEstadoPedidoTipoHemoderivado' => $idReservado],
            )
            ->assertRedirect();

        $vigente = $componente->pedidoTipoHemoderivadoEstados()
            ->whereNull('fechaFinAsignacionPedidoTipoHemoderivadoEstado')
            ->firstOrFail();
        $this->assertSame($idReservado, $vigente->idEstadoPedidoTipoHemoderivado);
    }

    public function test_actualizar_un_componente_que_no_pertenece_a_la_cirugia_da_404(): void
    {
        $cirugia = $this->cirugiaConHisopadoPendiente();
        $componenteDeOtraCirugia = PedidoTipoHemoderivado::whereHas(
            'pedidoHemoderivado',
            fn ($q) => $q->where('idCirugia', '!=', $cirugia->idCirugia),
        )->firstOrFail();
        $idReservado = EstadoPedidoTipoHemoderivado::where('nombreEstadoPedidoTipoHemoderivado', 'Reservado')->value('idEstadoPedidoTipoHemoderivado');

        $this->actingAs($this->usuario('gonzalez'))
            ->patch(
                "/cirugias/{$cirugia->idCirugia}/hemoderivados/{$componenteDeOtraCirugia->idPedidoTipoHemoderivado}/estado",
                ['idEstadoPedidoTipoHemoderivado' => $idReservado],
            )
            ->assertNotFound();
    }
}
