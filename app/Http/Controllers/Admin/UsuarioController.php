<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaveRequest;
use App\Http\Requests\UsuarioRequest;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Support\Auditor;
use App\Support\FiltroBaja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Alta y mantenimiento de los usuarios del sistema.
 *
 * Un usuario no es una fila sola: cuelga de la cadena Persona -> Personal ->
 * Usuario, y los roles viven en RolPersonal con su propio historial. En el
 * alta tambien permite vincular una Persona existente (por ?persona={id})
 * para darle acceso y roles sin duplicar su ficha.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->string('estado')->toString();
        $busqueda = trim($request->string('q')->toString());
        $rol = $request->integer('rol') ?: null;

        return view('admin.usuarios.index', [
            'usuarios' => FiltroBaja::aplicar(
                Usuario::query()->with(['personal.persona', 'personal.rolesVigentes']),
                $estado,
                'fechaBajaUsuario',
            )
                ->when($busqueda !== '', fn ($q) => $q->where(
                    // Un solo campo para buscar: la gente no sabe de antemano
                    // si se acuerda del apellido, del alias o del legajo.
                    fn ($q) => $q
                        ->where('nombreUsuario', 'like', "%{$busqueda}%")
                        ->orWhereHas('personal', fn ($p) => $p
                            ->where('legajoPersonal', 'like', "%{$busqueda}%")
                            ->orWhereHas('persona', fn ($per) => $per
                                ->where('apellidos', 'like', "%{$busqueda}%")
                                ->orWhere('nombres', 'like', "%{$busqueda}%")))
                ))
                // Por RolPersonal y no por la pivote de la relación: así queda
                // explícito que solo cuentan las asignaciones vigentes.
                ->when($rol, fn ($q) => $q->whereHas('personal.rolPersonales', fn ($rp) => $rp
                    ->where('idRol', $rol)
                    ->whereNull('fechaHoraBajaAsignacionRolPersonal')))
                ->orderBy('nombreUsuario')
                ->paginate(25)
                ->withQueryString(),
            'filtros' => ['q' => $busqueda, 'rol' => $rol, 'estado' => $estado],
            'roles' => Rol::query()
                ->whereNull('fechaHoraBajaRol')
                ->orderBy('nombreRol')
                ->pluck('nombreRol', 'idRol')
                ->all(),
            // Sobre el total, no sobre lo filtrado: son el estado del padron,
            // y quien filtra por «dados de baja» igual quiere ver cuantos hay.
            'indicadores' => [
                'usuarios' => Usuario::query()->whereNull('fechaBajaUsuario')->count(),
                'dadosDeBaja' => Usuario::query()->whereNotNull('fechaBajaUsuario')->count(),
                'sinRol' => Usuario::query()
                    ->whereNull('fechaBajaUsuario')
                    ->whereDoesntHave('personal.rolesVigentes')
                    ->count(),
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $personaExistente = null;

        if ($idPersona = $request->integer('persona')) {
            $personaExistente = Persona::with('personal.usuario')->findOrFail($idPersona);

            if ($personaExistente->fechaHoraBajaPersona !== null) {
                return redirect()
                    ->route('admin.personas.index')
                    ->with('error', 'No podés crear un usuario para una persona dada de baja.');
            }

            if ($usuarioExistente = $personaExistente->personal?->usuario) {
                return redirect()
                    ->route('admin.usuarios.edit', $usuarioExistente)
                    ->with('aviso', 'Esta persona ya tiene el usuario '.$usuarioExistente->nombreUsuario.'. Podés editar sus roles acá.');
            }
        }

        return view('admin.usuarios.formulario', $this->datosDelFormulario(null, $personaExistente));
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        DB::transaction(function () use ($datos) {
            $datosPersona = [
                'tipo_documento_id' => $datos['tipo_documento_id'],
                'documento' => $datos['documento'],
                'apellidos' => $datos['apellidos'],
                'nombres' => $datos['nombres'],
                'contacto_email_direccion' => $datos['contacto_email_direccion'],
                'telefono_numero' => $datos['telefono_numero'],
                'direccionPersona' => $datos['direccionPersona'],
            ];

            if (! empty($datos['persona_id'])) {
                $persona = Persona::findOrFail($datos['persona_id']);
                $persona->update($datosPersona);
            } else {
                $persona = Persona::create($datosPersona);
            }

            $personal = Personal::updateOrCreate(
                ['idPersona' => $persona->idPersona],
                [
                    'legajoPersonal' => $datos['legajoPersonal'] ?? null,
                    'matriculaProvincial' => $datos['matriculaProvincial'] ?? null,
                    'matriculaNacional' => $datos['matriculaNacional'] ?? null,
                    'mailInstitucional' => $datos['mailInstitucional'] ?? null,
                ],
            );

            $usuario = Usuario::create([
                'idPersonal' => $personal->idPersonal,
                'nombreUsuario' => $datos['nombreUsuario'],
                // El cast 'hashed' de Usuario se encarga del hash.
                'passwordUsuario' => $datos['password'],
            ]);

            $personal->sincronizarRoles($datos['roles'] ?? []);

            Auditor::registrar(Auditor::ALTA, $usuario, $this->comoSeLlama($usuario));
        });

        return redirect()
            ->route('admin.usuarios.index')
            ->with('exito', 'Usuario '.$datos['nombreUsuario'].' creado correctamente.');
    }

    public function edit(Usuario $usuario): View
    {
        return view('admin.usuarios.formulario', $this->datosDelFormulario($usuario));
    }

    public function update(UsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $datos = $request->validated();
        $roles = array_map('intval', $datos['roles'] ?? []);

        if ($mensaje = $this->seEstaDejandoAfuera($request, $usuario, $roles)) {
            return back()->withInput()->with('error', $mensaje);
        }

        DB::transaction(function () use ($datos, $roles, $usuario) {
            $personal = $usuario->personal;
            $persona = $personal->persona;

            // Un usuario se reparte en tres tablas: la auditoría junta los
            // cambios de las tres en un solo registro, más el juego de roles.
            $antes = [
                'persona' => Auditor::foto($persona),
                'personal' => Auditor::foto($personal),
                'usuario' => Auditor::foto($usuario),
                'roles' => $personal->rolesVigentes->pluck('nombreRol')->all(),
            ];

            $persona->update([
                'tipo_documento_id' => $datos['tipo_documento_id'],
                'documento' => $datos['documento'],
                'apellidos' => $datos['apellidos'],
                'nombres' => $datos['nombres'],
                'contacto_email_direccion' => $datos['contacto_email_direccion'],
                'telefono_numero' => $datos['telefono_numero'],
                'direccionPersona' => $datos['direccionPersona'],
            ]);

            $personal->update([
                'legajoPersonal' => $datos['legajoPersonal'] ?? null,
                'matriculaProvincial' => $datos['matriculaProvincial'] ?? null,
                'matriculaNacional' => $datos['matriculaNacional'] ?? null,
                'mailInstitucional' => $datos['mailInstitucional'] ?? null,
            ]);

            $usuario->update(['nombreUsuario' => $datos['nombreUsuario']]);

            $personal->sincronizarRoles($roles);

            $cambios = [
                ...Auditor::diferencia($persona, $antes['persona']),
                ...Auditor::diferencia($personal, $antes['personal']),
                ...Auditor::diferencia($usuario, $antes['usuario']),
                ...Auditor::listaCambiada(
                    'roles',
                    $antes['roles'],
                    $personal->rolesVigentes->pluck('nombreRol')->all(),
                ),
            ];

            if ($cambios) {
                Auditor::registrar(Auditor::EDICION, $usuario, $this->comoSeLlama($usuario), $cambios);
            }
        });

        return redirect()
            ->route('admin.usuarios.index')
            ->with('exito', 'Usuario '.$datos['nombreUsuario'].' actualizado correctamente.');
    }

    public function clave(ClaveRequest $request, Usuario $usuario): RedirectResponse
    {
        $usuario->update(['passwordUsuario' => $request->validated()['password']]);

        // Sin cambios: la contraseña no se registra ni siquiera hasheada.
        Auditor::registrar(Auditor::CLAVE, $usuario, $this->comoSeLlama($usuario));

        return redirect()
            ->route('admin.usuarios.edit', $usuario)
            ->with('exito', 'Contraseña de '.$usuario->nombreUsuario.' actualizada.');
    }

    public function destroy(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No podés darte de baja a vos mismo.');
        }

        $usuario->update(['fechaBajaUsuario' => now()]);

        Auditor::registrar(Auditor::BAJA, $usuario, $this->comoSeLlama($usuario));

        return redirect()
            ->route('admin.usuarios.index')
            ->with('exito', 'Usuario '.$usuario->nombreUsuario.' dado de baja. Ya no puede iniciar sesión.');
    }

    public function restore(Usuario $usuario): RedirectResponse
    {
        $usuario->update(['fechaBajaUsuario' => null]);

        Auditor::registrar(Auditor::REACTIVACION, $usuario, $this->comoSeLlama($usuario));

        return redirect()
            ->route('admin.usuarios.index', ['estado' => FiltroBaja::BAJAS])
            ->with('exito', 'Usuario '.$usuario->nombreUsuario.' reactivado.');
    }

    /**
     * Como se nombra el usuario en la auditoría.
     */
    private function comoSeLlama(Usuario $usuario): string
    {
        return 'Usuario «'.$usuario->nombreUsuario.'»';
    }

    /**
     * Un administrador que se quita a si mismo el rol Administrador se queda
     * sin forma de volver a entrar a esta seccion.
     *
     * @param  list<int>  $roles
     */
    private function seEstaDejandoAfuera(Request $request, Usuario $usuario, array $roles): ?string
    {
        if (! $usuario->is($request->user())) {
            return null;
        }

        $administrador = Rol::query()->where('nombreRol', 'Administrador')->value('idRol');

        return in_array($administrador, $roles, true)
            ? null
            : 'No podés quitarte el rol Administrador: quedarías sin acceso a esta sección.';
    }

    /**
     * Todo lo que necesita la vista del formulario, tanto en el alta como en
     * la edicion. Deja los valores planos para que la vista no ande navegando
     * relaciones que en un alta no existen.
     *
     * @return array<string, mixed>
     */
    private function datosDelFormulario(?Usuario $usuario, ?Persona $personaExistente = null): array
    {
        $personal = $usuario?->personal;
        $persona = $personal?->persona ?? $personaExistente;
        $dniPorDefecto = $persona === null
            ? TipoDocumento::query()
                ->where('nombreTipoDocumento', 'DNI')
                ->whereNull('fechaBajaTipoDocumento')
                ->value('idTipoDocumento')
            : null;

        return [
            'usuario' => $usuario,
            'personaExistente' => $personaExistente,
            'tiposDocumento' => TipoDocumento::query()
                ->whereNull('fechaBajaTipoDocumento')
                ->orderBy('nombreTipoDocumento')
                ->pluck('nombreTipoDocumento', 'idTipoDocumento')
                ->all(),
            'roles' => Rol::query()
                ->whereNull('fechaHoraBajaRol')
                ->orderBy('nombreRol')
                ->get(),
            'rolesActuales' => $personal?->rolesVigentes->pluck('idRol')->all() ?? [],
            'valores' => [
                'tipo_documento_id' => $persona?->tipo_documento_id ?? $dniPorDefecto,
                'documento' => $persona?->documento,
                'apellidos' => $persona?->apellidos,
                'nombres' => $persona?->nombres,
                'contacto_email_direccion' => $persona?->contacto_email_direccion,
                'telefono_numero' => $persona?->telefono_numero,
                'direccionPersona' => $persona?->direccionPersona,
                'legajoPersonal' => $personal?->legajoPersonal,
                'matriculaProvincial' => $personal?->matriculaProvincial,
                'matriculaNacional' => $personal?->matriculaNacional,
                'mailInstitucional' => $personal?->mailInstitucional,
                'nombreUsuario' => $usuario?->nombreUsuario,
            ],
        ];
    }
}
