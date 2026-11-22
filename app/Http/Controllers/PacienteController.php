<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormularioPersona;
use App\Http\Requests\PersonaRequest;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PacienteController extends Controller
{
    use FormularioPersona;

    /**
     * Listado y busqueda de pacientes activos.
     */
    public function index(Request $request): View
    {
        $query = Persona::query()
            ->with('tipoDocumento')
            ->whereNull('fechaHoraBajaPersona');

        // Búsqueda por nombre, apellido o DNI (incluye DNI con puntos o espacios).
        if ($search = trim((string) $request->query('buscar', ''))) {
            $query->buscar($search);
        }

        // Filtro por género.
        if (in_array($genero = $request->query('genero'), ['M', 'F', 'X'], true)) {
            $query->where('genero', $genero);
        }

        // Filtro por crónico: ConvertEmptyStringsToNull convierte "" a null,
        // por eso validamos explícitamente contra '0' y '1'.
        $esCronico = $request->query('es_cronico');
        if (in_array($esCronico, ['0', '1', 0, 1], true)) {
            $query->where('es_cronico', (bool) $esCronico);
        }

        $pacientes = $query
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(20)
            ->withQueryString();

        return view('pacientes.index', compact('pacientes'));
    }

    public function create(): View
    {
        return view('pacientes.formulario', [
            'paciente' => null,
            ...$this->catalogosPersona(),
        ]);
    }

    public function store(PersonaRequest $request): RedirectResponse
    {
        $persona = $this->crearPersonaAuditada($request->validated());

        return redirect()
            ->route('pacientes.show', $persona)
            ->with('exito', 'Paciente '.$persona->nombre_completo.' registrado correctamente.');
    }

    /**
     * Ficha del paciente y su historial de cirugias.
     */
    public function show(Persona $paciente): View
    {
        $paciente->load([
            'tipoDocumento',
            'grupoSanguineo',
            'cirugias' => fn ($q) => $q->orderBy('fechaHoraCirugia', 'desc'),
            'cirugias.tipoCirugia',
            'cirugias.cirujano.persona',
            'cirugias.cirugiaEstados.estadoCirugia',
        ]);

        $puedeGestionarCoberturas = auth()->user()?->tieneRol('Gestor de quirófano') ?? false;

        return view('pacientes.show', [
            'paciente' => $paciente,
            'puedeGestionarCoberturas' => $puedeGestionarCoberturas,
            ...$this->datosCoberturasPersona($paciente, $puedeGestionarCoberturas),
        ]);
    }

    public function edit(Persona $paciente): View
    {
        return view('pacientes.formulario', [
            'paciente' => $paciente,
            ...$this->catalogosPersona(),
        ]);
    }

    public function update(PersonaRequest $request, Persona $paciente): RedirectResponse
    {
        $this->actualizarPersonaAuditada($paciente, $request->validated());

        return redirect()
            ->route('pacientes.show', $paciente)
            ->with('exito', 'Datos del paciente actualizados correctamente.');
    }
}
