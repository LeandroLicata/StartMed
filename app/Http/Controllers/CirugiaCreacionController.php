<?php

namespace App\Http\Controllers;

use App\Models\AutCirugia;
use App\Models\AutoCirugiaEstado;
use App\Models\Cirugia;
use App\Models\CirugiaEstado;
use App\Models\CirugiaPersonal;
use App\Models\CirugiaQuirofano;
use App\Models\EstadoAutCirugia;
use App\Models\EstadoCirugia;
use App\Models\EstadoHisopadoSarm;
use App\Models\HisopadoSarm;
use App\Models\HisopadoSarmEstado;
use App\Models\ObraSocial;
use App\Models\PedidoHemoderivado;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use App\Models\Quirofano;
use App\Models\Rol;
use App\Models\TipoCirugia;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Alta de una cirugia nueva por el gestor de quirofano, todo en una sola
 * pantalla: buscar/dar de alta al paciente por DNI, cargar su cobertura,
 * elegir quirofano y equipo (comprobando disponibilidad), y dejar la cirugia
 * en el estado inicial que le corresponde.
 */
class CirugiaCreacionController extends Controller
{
    public function buscar(Request $request): View
    {
        $q = $request->query('q');
        $idPersona = $request->query('persona');
        $persona = null;
        $resultados = null;

        if ($idPersona) {
            $persona = Persona::findOrFail($idPersona);
            abort_if($persona->fechaHoraBajaPersona !== null, 403, 'Esta persona está dada de baja.');
        } elseif ($q) {
            $resultados = Persona::where('documento', 'like', "%{$q}%")
                ->orWhere('apellidos', 'like', "%{$q}%")
                ->orWhere('nombres', 'like', "%{$q}%")
                ->orderBy('apellidos')
                ->limit(15)
                ->get();
        }

        return view('cirugias.nueva', array_merge([
            'q' => $q,
            'resultados' => $resultados,
            'persona' => $persona,
            'fecha' => $request->query('fecha'),
        ], $persona ? $this->datosFormulario($persona) : []));
    }

    public function crearPaciente(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'apellidos' => ['required', 'string', 'max:120'],
            'nombres' => ['required', 'string', 'max:120'],
            'documento' => ['required', 'string', 'max:60'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', 'string', 'max:1'],
            'contacto_email_direccion' => ['nullable', 'email', 'max:60'],
            'contacto_telefono_numero' => ['nullable', 'string', 'max:20'],
        ]);

        $persona = Persona::firstOrCreate(
            ['tipo_documento_id' => $this->idDni(), 'documento' => $datos['documento']],
            [
                'apellidos' => $datos['apellidos'],
                'nombres' => $datos['nombres'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                'genero' => $datos['genero'] ?? null,
                'contacto_email_direccion' => $datos['contacto_email_direccion'] ?? null,
                'contacto_telefono_numero' => $datos['contacto_telefono_numero'] ?? null,
            ],
        );

        return redirect()->route('cirugias.crear', array_filter([
            'persona' => $persona->idPersona,
            'fecha' => $request->input('fecha'),
        ]));
    }

    /**
     * Botón "Comprobar disponibilidad": mismo chequeo de franja horaria que
     * store(), pero sin guardar nada. Vuelve al formulario con lo que ya
     * estaba cargado (withInput) más el resultado por cada recurso.
     */
    public function comprobar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'idPersona' => ['required', 'exists:Persona,idPersona'],
            'idQuirofano' => ['required', 'exists:Quirofano,idQuirofano'],
            'fechaHoraCirugia' => ['required', 'date'],
            'fechaHoraFinCirugia' => ['nullable', 'date', 'after:fechaHoraCirugia'],
            'idPersonalCirujano' => ['nullable', 'exists:Personal,idPersonal'],
            'idPersonalAnestesista' => ['nullable', 'exists:Personal,idPersonal'],
        ]);

        [$inicio, $fin] = $this->resolverRango($datos);

        $resultado = ['quirofano' => ! $this->quirofanoOcupado((int) $datos['idQuirofano'], $inicio, $fin)];

        if (! empty($datos['idPersonalCirujano'])) {
            $resultado['cirujano'] = ! $this->personaOcupada('idPersonalCirujano', (int) $datos['idPersonalCirujano'], $inicio, $fin);
        }

        if (! empty($datos['idPersonalAnestesista'])) {
            $resultado['anestesista'] = ! $this->personaOcupada('idPersonalAnestesista', (int) $datos['idPersonalAnestesista'], $inicio, $fin);
        }

        return redirect()
            ->route('cirugias.crear', array_filter(['persona' => $datos['idPersona'], 'fecha' => $request->input('fecha')]))
            ->withInput()
            ->with('disponibilidad', $resultado);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'idPersona' => ['required', 'exists:Persona,idPersona'],
            'idTipoCirugia' => ['required', 'exists:TipoCirugia,idTipoCirugia'],
            'idQuirofano' => ['required', 'exists:Quirofano,idQuirofano'],
            'idPersonalCirujano' => ['nullable', 'exists:Personal,idPersonal'],
            'idPersonalAnestesista' => ['nullable', 'exists:Personal,idPersonal'],
            'fechaHoraCirugia' => ['required', 'date'],
            'fechaHoraFinCirugia' => ['nullable', 'date', 'after:fechaHoraCirugia'],
            'requiereImplante' => ['nullable', 'boolean'],
            'requiereHemoderivados' => ['nullable', 'boolean'],
            'requiereHisopadoSarm' => ['nullable', 'boolean'],
            'cobertura' => ['required', 'in:particular,existente,nueva'],
            'idPlanObraSocial' => ['required_if:cobertura,existente', 'nullable', 'exists:PlanObraSocial,idPlanObraSocial'],
            'idPlan' => ['required_if:cobertura,nueva', 'nullable', 'exists:Plan,idPlan'],
            'nroBeneficiario' => ['nullable', 'string', 'max:60'],
        ]);

        $inicio = Carbon::parse($datos['fechaHoraCirugia']);
        $finCargado = ! empty($datos['fechaHoraFinCirugia']) ? Carbon::parse($datos['fechaHoraFinCirugia']) : null;
        $finParaConflicto = $finCargado ?? $inicio->copy()->addMinutes(120);

        if ($this->quirofanoOcupado((int) $datos['idQuirofano'], $inicio, $finParaConflicto)) {
            throw ValidationException::withMessages([
                'idQuirofano' => 'Ese quirófano ya tiene una cirugía programada en un horario que se superpone.',
            ]);
        }

        if (! empty($datos['idPersonalCirujano'])
            && $this->personaOcupada('idPersonalCirujano', (int) $datos['idPersonalCirujano'], $inicio, $finParaConflicto)) {
            throw ValidationException::withMessages([
                'idPersonalCirujano' => 'Ese cirujano ya tiene otra cirugía en un horario que se superpone.',
            ]);
        }

        if (! empty($datos['idPersonalAnestesista'])
            && $this->personaOcupada('idPersonalAnestesista', (int) $datos['idPersonalAnestesista'], $inicio, $finParaConflicto)) {
            throw ValidationException::withMessages([
                'idPersonalAnestesista' => 'Ese anestesista ya tiene otra cirugía en un horario que se superpone.',
            ]);
        }

        $cirugia = DB::transaction(function () use ($datos, $inicio, $finCargado) {
            $equipoCompleto = ! empty($datos['idPersonalCirujano']) && ! empty($datos['idPersonalAnestesista']);

            $cirugia = Cirugia::create([
                'idPersonaPaciente' => $datos['idPersona'],
                'idTipoCirugia' => $datos['idTipoCirugia'],
                'idPersonalCirujano' => $datos['idPersonalCirujano'] ?? null,
                'idPersonalAnestesista' => $datos['idPersonalAnestesista'] ?? null,
                'fechaHoraCirugia' => $inicio,
                'fechaHoraFinCirugia' => $finCargado,
                'requiereImplante' => (bool) ($datos['requiereImplante'] ?? false),
            ]);

            CirugiaEstado::create([
                'idCirugia' => $cirugia->idCirugia,
                'idEstadoCirugia' => EstadoCirugia::where('nombreEstadoCirugia', $equipoCompleto ? 'En espera' : 'En espera de confirmación')
                    ->value('idEstadoCirugia'),
                'fechaAsignacionCirugiaEstado' => now(),
            ]);

            CirugiaQuirofano::create([
                'idQuirofano' => $datos['idQuirofano'],
                'idCirugia' => $cirugia->idCirugia,
                'fechaHoraAsignacion' => $inicio,
            ]);

            foreach (['idPersonalCirujano' => 'Cirujano', 'idPersonalAnestesista' => 'Anestesista'] as $campo => $rol) {
                if (! empty($datos[$campo])) {
                    CirugiaPersonal::create([
                        'idCirugia' => $cirugia->idCirugia,
                        'idPersonal' => $datos[$campo],
                        'idRol' => Rol::where('nombreRol', $rol)->value('idRol'),
                        'fechaInicioAsignacionCirugiaPersonal' => now(),
                    ]);
                }
            }

            // Solo la cabecera: el detalle (tipo, cantidad) lo carga despues
            // el gestor o el cirujano sobre la cirugia ya creada.
            if (! empty($datos['requiereHemoderivados'])) {
                PedidoHemoderivado::create([
                    'idCirugia' => $cirugia->idCirugia,
                    'fechaPedidoHemoderivado' => now(),
                ]);
            }

            // Igual que hemoderivados: solo la solicitud. El resultado y la
            // profilaxis derivada se cargan despues, sobre la cirugia ya creada.
            if (! empty($datos['requiereHisopadoSarm'])) {
                $hisopado = HisopadoSarm::create([
                    'idCirugia' => $cirugia->idCirugia,
                    'fechaSolicitacionHisopadoSarm' => now(),
                ]);

                HisopadoSarmEstado::create([
                    'idHisopadoSarm' => $hisopado->idHisopadoSarm,
                    'idEstadoHisopadoSarm' => EstadoHisopadoSarm::where('nombreEstadoHisopadoSarm', 'Pendiente')
                        ->value('idEstadoHisopadoSarm'),
                    'fechaInicioAsignacionHisopadoSarmEstado' => now(),
                ]);
            }

            $plan = $this->resolverPlan($datos);

            $autCirugia = AutCirugia::create([
                'idCirugia' => $cirugia->idCirugia,
                'idPlan' => $plan->idPlan,
                'fechaLimiteEnvioAutorizacion' => $plan->obrasocial?->diasVigenciaOrden
                    ? $inicio->copy()->subDays($plan->obrasocial->diasVigenciaOrden)
                    : null,
            ]);

            if (! $plan->es_sin_cobertura) {
                AutoCirugiaEstado::create([
                    'idAutCirugia' => $autCirugia->idAutCirugia,
                    'idEstadoAutCirugia' => EstadoAutCirugia::where('nombreEstadoAutCirugia', 'Pendiente de envío')
                        ->value('idEstadoAutCirugia'),
                    'fechaInicioAutoCirugiaEstado' => now(),
                ]);
            }

            return $cirugia;
        });

        return redirect()->route('cirugias.show', $cirugia)
            ->with('exito', 'Cirugía creada correctamente.');
    }

    /** @return array<string, mixed> */
    private function datosFormulario(Persona $persona): array
    {
        return [
            'tiposCirugia' => TipoCirugia::whereNull('fechaBajaTipoCirugia')
                ->orderBy('nombreTipoCirugia')->get(),
            'cirujanos' => $this->personalConRol('Cirujano'),
            'anestesistas' => $this->personalConRol('Anestesista'),
            'quirofanos' => $this->quirofanosDisponibles(),
            'coberturas' => $persona->planObraSociales()
                ->whereNull('fechaFinPlanObraSocial')
                ->with('plan.obrasocial')
                ->get(),
            'obrasSociales' => ObraSocial::whereNull('fechaBajaObraSocial')
                ->where('nombreObraSocial', '!=', 'Sin obra social')
                ->with(['planes' => fn ($q) => $q->whereNull('fechaBajaPlan')])
                ->orderBy('nombreObraSocial')
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function resolverPlan(array $datos): Plan
    {
        return match ($datos['cobertura']) {
            'particular' => Plan::where('es_sin_cobertura', true)->firstOrFail(),
            'existente' => PlanObraSocial::findOrFail($datos['idPlanObraSocial'])->plan,
            'nueva' => tap(Plan::findOrFail($datos['idPlan']), function (Plan $plan) use ($datos) {
                PlanObraSocial::create([
                    'idPersona' => $datos['idPersona'],
                    'idPlan' => $plan->idPlan,
                    'nroBeneficiaroPlanObraSocial' => $datos['nroBeneficiario'] ?? null,
                    'fechaInicioPlanObraSocial' => now(),
                ]);
            }),
        };
    }

    /**
     * Inicio y fin efectivos para chequear superposicion: si no cargaron fin,
     * se asume una duracion de 2 hs (mismo default que ResumenCirugia).
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverRango(array $datos): array
    {
        $inicio = Carbon::parse($datos['fechaHoraCirugia']);
        $fin = ! empty($datos['fechaHoraFinCirugia'])
            ? Carbon::parse($datos['fechaHoraFinCirugia'])
            : $inicio->copy()->addMinutes(120);

        return [$inicio, $fin];
    }

    private function quirofanoOcupado(int $idQuirofano, Carbon $inicio, Carbon $fin): bool
    {
        $query = Cirugia::whereHas(
            'cirugiaQuirofanos',
            fn ($q) => $q->where('idQuirofano', $idQuirofano)->whereNull('fechaHoraDesasignacion'),
        );

        return $this->seSuperponeConOtraCirugia($query, $inicio, $fin);
    }

    private function personaOcupada(string $columna, int $idPersonal, Carbon $inicio, Carbon $fin): bool
    {
        return $this->seSuperponeConOtraCirugia(Cirugia::where($columna, $idPersonal), $inicio, $fin);
    }

    /**
     * Superposicion real de franja horaria (no solo instante exacto): dos
     * rangos [inicio,fin) se cruzan si cada uno empieza antes de que el otro
     * termine. Ignora cirugias suspendidas, que liberan el horario.
     */
    private function seSuperponeConOtraCirugia(Builder $query, Carbon $inicio, Carbon $fin): bool
    {
        return $query->with('cirugiaEstados.estadoCirugia')->get()
            ->contains(function (Cirugia $otra) use ($inicio, $fin) {
                $otroFin = $otra->fechaHoraFinCirugia ?? $otra->fechaHoraCirugia->copy()->addMinutes(120);

                if (! ($inicio->lt($otroFin) && $otra->fechaHoraCirugia->lt($fin))) {
                    return false;
                }

                $vigente = $otra->cirugiaEstados->firstWhere('fechaDesasignacionCirugiaEstado', null);

                return $vigente?->estadoCirugia?->nombreEstadoCirugia !== 'Suspendida';
            });
    }

    /**
     * Un quirofano sin ningun QuirofanoEstado asignado se considera
     * disponible por defecto (hoy nada en el sistema carga ese historial
     * todavia); solo se excluye si su estado vigente dice explicitamente
     * otra cosa (mantenimiento, fuera de servicio).
     *
     * @return Collection<int, Quirofano>
     */
    private function quirofanosDisponibles(): Collection
    {
        return Quirofano::whereNull('fechaBajaQuirofano')
            ->with(['quirofanoEstados' => fn ($q) => $q->whereNull('fechaFinQuirofanoEstado')->with('estadoQuirofano')])
            ->orderBy('nroQuirofano')
            ->get()
            ->filter(function (Quirofano $q) {
                $estado = $q->quirofanoEstados->first()?->estadoQuirofano?->nombreEstadoQuirofano;

                return $estado === null || $estado === 'Disponible';
            })
            ->values();
    }

    /** @return Collection<int, Personal> */
    private function personalConRol(string $rol): Collection
    {
        return Personal::with('persona')
            ->whereHas('rolesVigentes', fn ($q) => $q->where('nombreRol', $rol))
            ->get();
    }

    private function idDni(): int
    {
        return TipoDocumento::where('nombreTipoDocumento', 'DNI')->value('idTipoDocumento');
    }
}
