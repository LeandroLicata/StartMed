<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FormularioPersona;
use App\Http\Controllers\Controller;
use App\Http\Requests\PersonaRequest;
use App\Models\Persona;
use App\Support\Auditor;
use App\Support\FiltroBaja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Padron completo de Personas para el Administrador.
 *
 * Permite cargar o actualizar los datos personales (incluyendo mail de acceso
 * al portal, telefono y direccion) y promover rapidamente una persona
 * existente a usuario de staff sin duplicar el registro.
 */
class PersonaController extends Controller
{
    use FormularioPersona;

    public function index(Request $request): View
    {
        $estado = $request->string('estado')->toString();
        $busqueda = trim($request->string('q')->toString());
        $tipo = $request->string('tipo')->toString();

        if (! in_array($tipo, ['paciente', 'usuario'], true)) {
            $tipo = '';
        }

        $personas = FiltroBaja::aplicar(
            Persona::query()->with(['tipoDocumento', 'personal.usuario', 'personal.rolesVigentes']),
            $estado,
            'fechaHoraBajaPersona',
        )
            ->when($busqueda !== '', fn ($q) => $q->buscar($busqueda, incluirEmail: true))
            ->when($tipo === 'usuario', fn ($q) => $q->whereHas('personal.usuario'))
            ->when($tipo === 'paciente', fn ($q) => $q->whereDoesntHave('personal.usuario'))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(25)
            ->withQueryString();

        return view('admin.personas.index', [
            'personas' => $personas,
            'filtros' => ['q' => $busqueda, 'tipo' => $tipo, 'estado' => $estado],
            'indicadores' => [
                'personas' => Persona::query()->whereNull('fechaHoraBajaPersona')->count(),
                'conUsuario' => Persona::query()->whereNull('fechaHoraBajaPersona')->whereHas('personal.usuario')->count(),
                'soloPacientes' => Persona::query()->whereNull('fechaHoraBajaPersona')->whereDoesntHave('personal.usuario')->count(),
                'dadasDeBaja' => Persona::query()->whereNotNull('fechaHoraBajaPersona')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.personas.formulario', [
            'persona' => null,
            ...$this->catalogosPersona(),
        ]);
    }

    public function store(PersonaRequest $request): RedirectResponse
    {
        $persona = $this->crearPersonaAuditada($request->validated());

        if ($request->input('accion') === 'crear_usuario') {
            return redirect()
                ->route('admin.usuarios.create', ['persona' => $persona->idPersona])
                ->with('exito', 'Persona '.$persona->nombre_completo.' creada. Completá ahora los datos de usuario y roles.');
        }

        return redirect()
            ->route('admin.personas.index')
            ->with('exito', 'Persona '.$persona->nombre_completo.' creada correctamente.');
    }

    public function edit(Persona $persona): View
    {
        $persona->load(['personal.usuario', 'personal.rolesVigentes']);

        return view('admin.personas.formulario', [
            'persona' => $persona,
            ...$this->catalogosPersona(),
        ]);
    }

    public function update(PersonaRequest $request, Persona $persona): RedirectResponse
    {
        $this->actualizarPersonaAuditada($persona, $request->validated());

        if ($request->input('accion') === 'crear_usuario' && ! $persona->personal?->usuario) {
            return redirect()
                ->route('admin.usuarios.create', ['persona' => $persona->idPersona])
                ->with('exito', 'Datos guardados. Completá ahora los datos de usuario y roles.');
        }

        return redirect()
            ->route('admin.personas.index')
            ->with('exito', 'Persona '.$persona->nombre_completo.' actualizada correctamente.');
    }

    public function destroy(Request $request, Persona $persona): RedirectResponse
    {
        if ($persona->personal?->usuario?->is($request->user())) {
            return back()->with('error', 'No podés darte de baja a vos mismo.');
        }

        $persona->update(['fechaHoraBajaPersona' => now()]);

        Auditor::registrar(Auditor::BAJA, $persona, 'Persona «'.$persona->nombre_completo.'»');

        return redirect()
            ->route('admin.personas.index')
            ->with('exito', 'Persona '.$persona->nombre_completo.' dada de baja.');
    }

    public function restore(Persona $persona): RedirectResponse
    {
        $persona->update(['fechaHoraBajaPersona' => null]);

        Auditor::registrar(Auditor::REACTIVACION, $persona, 'Persona «'.$persona->nombre_completo.'»');

        return redirect()
            ->route('admin.personas.index', ['estado' => FiltroBaja::BAJAS])
            ->with('exito', 'Persona '.$persona->nombre_completo.' reactivada.');
    }
}
