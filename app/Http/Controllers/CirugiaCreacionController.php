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
use App\Models\ObraSocial;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Plan;
use App\Models\PlanObraSocial;
use App\Models\Quirofano;
use App\Models\Rol;
use App\Models\TipoCirugia;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Alta de una cirugia nueva por el gestor de quirofano: buscar/dar de alta al
 * paciente por DNI, cargar su cobertura, elegir quirofano y equipo, y dejar
 * la cirugia en el estado inicial que le corresponde.
 */
class CirugiaCreacionController extends Controller
{
    public function buscar(Request $request): View
    {
        $documento = $request->query('documento');
        $persona = null;

        if ($documento) {
            $persona = Persona::where('tipo_documento_id', $this->idDni())
                ->where('documento', $documento)
                ->first();
        }

        return view('cirugias.buscar-paciente', [
            'documento' => $documento,
            'buscado' => (bool) $documento,
            'persona' => $persona,
        ]);
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

        return redirect()->route('cirugias.crear.formulario', $persona);
    }

    public function formulario(Persona $persona): View
    {
        abort_if($persona->fechaHoraBajaPersona !== null, 403, 'Esta persona está dada de baja.');

        return view('cirugias.nueva', [
            'persona' => $persona,
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
        ]);
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
            'cobertura' => ['required', 'in:particular,existente,nueva'],
            'idPlanObraSocial' => ['required_if:cobertura,existente', 'nullable', 'exists:PlanObraSocial,idPlanObraSocial'],
            'idPlan' => ['required_if:cobertura,nueva', 'nullable', 'exists:Plan,idPlan'],
            'nroBeneficiario' => ['nullable', 'string', 'max:60'],
        ]);

        $inicio = Carbon::parse($datos['fechaHoraCirugia']);
        $fin = isset($datos['fechaHoraFinCirugia']) ? Carbon::parse($datos['fechaHoraFinCirugia']) : null;

        if ($this->quirofanoOcupado((int) $datos['idQuirofano'], $inicio)) {
            throw ValidationException::withMessages([
                'idQuirofano' => 'Ese quirófano ya tiene una cirugía programada en ese horario.',
            ]);
        }

        $cirugia = DB::transaction(function () use ($datos, $inicio, $fin) {
            $equipoCompleto = ! empty($datos['idPersonalCirujano']) && ! empty($datos['idPersonalAnestesista']);

            $cirugia = Cirugia::create([
                'idPersonaPaciente' => $datos['idPersona'],
                'idTipoCirugia' => $datos['idTipoCirugia'],
                'idPersonalCirujano' => $datos['idPersonalCirujano'] ?? null,
                'idPersonalAnestesista' => $datos['idPersonalAnestesista'] ?? null,
                'fechaHoraCirugia' => $inicio,
                'fechaHoraFinCirugia' => $fin,
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
     * Ocupado = mismo quirofano + mismo horario de inicio exacto, en una
     * cirugia que no esta suspendida.
     */
    private function quirofanoOcupado(int $idQuirofano, Carbon $inicio): bool
    {
        return CirugiaQuirofano::query()
            ->where('idQuirofano', $idQuirofano)
            ->whereNull('fechaHoraDesasignacion')
            ->whereHas('cirugia', fn ($q) => $q->where('fechaHoraCirugia', $inicio->format('Y-m-d H:i:s')))
            ->with('cirugia.cirugiaEstados.estadoCirugia')
            ->get()
            ->contains(function (CirugiaQuirofano $asignacion) {
                $vigente = $asignacion->cirugia->cirugiaEstados->firstWhere('fechaDesasignacionCirugiaEstado', null);

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
