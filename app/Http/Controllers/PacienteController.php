<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PacienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Persona::query();

        // Búsqueda por nombre, apellido o DNI
        if ($search = $request->query('buscar')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombres', 'like', "%{$search}%")
                  ->orWhere('apellidos', 'like', "%{$search}%")
                  ->orWhere('documento', 'like', "%{$search}%");
            });
        }

        // Filtro por genero
        if ($genero = $request->query('genero')) {
            if (in_array($genero, ['M', 'F', 'X'])) {
                $query->where('genero', $genero);
            }
        }

        // Filtro por crónico
        if ($request->has('es_cronico') && $request->query('es_cronico') !== '') {
            $query->where('es_cronico', $request->query('es_cronico'));
        }

        // Filtramos para asegurar que traemos personas que no están dadas de baja
        $query->whereNull('fechaHoraBajaPersona');

        $pacientes = $query->orderBy('apellidos')->orderBy('nombres')->paginate(20)->withQueryString();

        return view('pacientes.index', compact('pacientes'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Persona $paciente): View
    {
        // Cargar las cirugías donde la persona es el paciente
        $paciente->load(['cirugias' => function ($q) {
            $q->orderBy('fechaHoraCirugia', 'desc');
        }, 'cirugias.tipoCirugia', 'cirugias.cirujano.persona', 'cirugias.cirugiaEstados.estadoCirugia']);

        return view('pacientes.show', compact('paciente'));
    }
}
