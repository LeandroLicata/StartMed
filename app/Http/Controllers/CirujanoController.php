<?php

namespace App\Http\Controllers;

use App\Models\Cirugia;
use App\Models\TipoCirugia;
use App\Support\Paginador;
use App\Support\ResumenCirugia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CirujanoController extends Controller
{
    /**
     * Los tres listados del panel crecen sin techo: la agenda de un cirujano,
     * lo que le falta a cada caso y el catalogo de procedimientos. Cada uno
     * pagina con su propio nombre de pagina para no arrastrar a los otros.
     */
    private const POR_PAGINA = 10;

    private const PROCEDIMIENTOS_POR_PAGINA = 12;

    public function __invoke(Request $request): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        // ... (TU CÓDIGO EXISTENTE DE $proximas, $hoy, $delMes, etc. se mantiene igual) ...
        $proximas = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->where('fechaHoraCirugia', '>=', Carbon::today())
            ->orderBy('fechaHoraCirugia')
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        $hoy = $proximas->filter(fn (ResumenCirugia $r) => $r->esDeHoy())->values();
        $desde = Carbon::today()->startOfMonth();
        $delMes = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereBetween('fechaHoraCirugia', [$desde, Carbon::today()->endOfMonth()])
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        $realizadas = $delMes->filter(fn (ResumenCirugia $r) => $r->estado() === 'Realizada');
        $suspendidas = $delMes->filter(fn (ResumenCirugia $r) => $r->estado() === 'Suspendida');
        $realizadasCompletas = $realizadas->filter(fn (ResumenCirugia $r) => $r->estaLista());
        $cerradas = $realizadas->count() + $suspendidas->count();

        // >>> NUEVO: Obtener las últimas 3 cirugías (históricas) <<<
        $ultimasCirugias = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->orderByDesc('fechaHoraCirugia')
            ->limit(3)
            ->get()
            ->map(fn (Cirugia $c) => new ResumenCirugia($c));

        // Los indicadores y "que falta resolver" salen de la colección entera:
        // lo que se pagina es lo que se dibuja, no lo que se cuenta.
        $conPendientes = $proximas->reject(fn (ResumenCirugia $r) => $r->estaLista())->values();

        return view('paneles.cirujano', [
            'personal' => $personal,
            'hoy' => $hoy,
            'proximas' => Paginador::deColeccion($proximas, $request, self::POR_PAGINA, 'proximas'),
            'conPendientes' => Paginador::deColeccion($conPendientes, $request, self::POR_PAGINA, 'pendientes'),
            'ultimasCirugias' => $ultimasCirugias, // >>> PASARLO A LA VISTA <<<
            'conImplante' => $proximas->filter(fn (ResumenCirugia $r) => $r->cirugia->requiereImplante)->count(),
            'indicadores' => [
                'realizadas' => $realizadas->count(),
                'realizadasCompletas' => $realizadasCompletas->count(),
                'suspendidas' => $suspendidas->count(),
                'tasaSuspension' => $cerradas > 0 ? round($suspendidas->count() / $cerradas * 100, 1) : 0.0,
                'mes' => $desde->translatedFormat('F'),
            ],
            'procedimientos' => TipoCirugia::query()
                ->whereNull('fechaBajaTipoCirugia')
                ->withCount('cirugias')
                ->orderByDesc('cirugias_count')
                ->paginate(self::PROCEDIMIENTOS_POR_PAGINA, ['*'], 'procedimientos')
                ->withQueryString(),
        ]);
    }

    // >>> NUEVO MÉTODO: Para ver el historial completo <<<
    public function historial(Request $request): View
    {
        $personal = $request->user()->personal;
        abort_if($personal === null, 403, 'Tu usuario no tiene un legajo asociado.');

        $historial = Cirugia::query()
            ->with(ResumenCirugia::RELACIONES)
            ->where('idPersonalCirujano', $personal->idPersonal)
            ->whereNotNull('fechaHoraCirugia')
            ->orderByDesc('fechaHoraCirugia')
            ->paginate(10); // Paginado de 10 en 10

        // Mantenemos la consistencia usando ResumenCirugia en la colección paginada
        $historialResumen = $historial->through(fn ($c) => new ResumenCirugia($c));

        return view('paneles.cirujano-historial', [
            'personal' => $personal,
            'historial' => $historialResumen,
        ]);
    }

    // ... (Tu método agenda() se mantiene igual) ...
}
