<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsentimientoRequest;
use App\Models\ConfigConsentimiento;
use App\Models\TipoCirugia;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Plantillas de consentimiento informado, una por tipo de cirugia.
 *
 * No entran en el ABM generico de catalogos porque no son una fila con un
 * nombre: son texto con vigencia por fechas, y de ahi que el trato sea por
 * versiones. Publicar una version nueva cierra la vigente y abre otra, igual
 * que el esquema modela el resto de sus historiales.
 *
 * Que exista el versionado no es por integridad —ConsentimientoPaciente guarda
 * su propio snapshot inmutable del texto firmado, con su hash— sino por
 * trazabilidad: poder decir que texto estaba en vigencia en una fecha dada.
 */
class ConsentimientoController extends Controller
{
    public function index(): View
    {
        return view('admin.consentimientos.index', [
            'tipos' => TipoCirugia::query()
                ->whereNull('fechaBajaTipoCirugia')
                ->with(['configConsentimientos' => fn ($q) => $q
                    ->whereNull('fechaFinConfigConsentimiento')])
                ->withCount('configConsentimientos')
                ->orderBy('nombreTipoCirugia')
                ->paginate(25),
        ]);
    }

    /**
     * Historial de versiones de un tipo de cirugia, de la vigente hacia atras.
     */
    public function show(TipoCirugia $tipoCirugia): View
    {
        return view('admin.consentimientos.versiones', [
            'tipo' => $tipoCirugia,
            'versiones' => $tipoCirugia->configConsentimientos()
                ->withCount('consentimientoPacientes')
                ->orderByRaw('fechaFinConfigConsentimiento IS NULL DESC')
                ->orderByDesc('fechaInicioConfigConsentimiento')
                ->orderByDesc('idConfigConsentimiento')
                // De a pocas: cada version muestra el texto completo, asi que
                // el historial de un procedimiento viejo no entra en pantalla.
                ->paginate(5),
        ]);
    }

    public function create(TipoCirugia $tipoCirugia): View
    {
        $vigente = $this->vigente($tipoCirugia);

        return view('admin.consentimientos.formulario', [
            'tipo' => $tipoCirugia,
            // Se arranca de la vigente: casi siempre se retoca, no se reescribe.
            'texto' => $vigente?->textoConfigConsentimiento ?? '',
            'version' => null,
        ]);
    }

    public function store(ConsentimientoRequest $request, TipoCirugia $tipoCirugia): RedirectResponse
    {
        $texto = $request->validated()['textoConfigConsentimiento'];

        $version = DB::transaction(function () use ($tipoCirugia, $texto) {
            $this->vigente($tipoCirugia)?->update(['fechaFinConfigConsentimiento' => now()]);

            return ConfigConsentimiento::create([
                'idTipoCirugia' => $tipoCirugia->idTipoCirugia,
                'textoConfigConsentimiento' => $texto,
                'fechaInicioConfigConsentimiento' => now(),
            ]);
        });

        Auditor::registrar(Auditor::ALTA, $version, $this->comoSeLlama($tipoCirugia));

        return redirect()
            ->route('admin.consentimientos.show', $tipoCirugia)
            ->with('exito', 'Nueva versión publicada. La anterior queda en el historial.');
    }

    public function edit(TipoCirugia $tipoCirugia, ConfigConsentimiento $version): View
    {
        abort_unless($this->seEdita($version), 403, 'Esta versión ya se usó: publicá una nueva.');

        return view('admin.consentimientos.formulario', [
            'tipo' => $tipoCirugia,
            'texto' => $version->textoConfigConsentimiento,
            'version' => $version,
        ]);
    }

    /**
     * Corregir una version que todavia no firmo nadie. Si ya se uso, el cambio
     * tiene que ser una version nueva: si no, la auditoria diria que un
     * consentimiento salio de un texto que hoy es otro.
     */
    public function update(ConsentimientoRequest $request, TipoCirugia $tipoCirugia, ConfigConsentimiento $version): RedirectResponse
    {
        abort_unless($this->seEdita($version), 403, 'Esta versión ya se usó: publicá una nueva.');

        $antes = Auditor::foto($version);
        $version->update($request->validated());

        if ($cambios = Auditor::diferencia($version, $antes)) {
            Auditor::registrar(Auditor::EDICION, $version, $this->comoSeLlama($tipoCirugia), $cambios);
        }

        return redirect()
            ->route('admin.consentimientos.show', $tipoCirugia)
            ->with('exito', 'Versión corregida.');
    }

    /**
     * Retirar la plantilla sin reemplazarla: cierra la vigencia y ese tipo de
     * cirugia queda sin consentimiento hasta que se publique otra.
     */
    public function destroy(TipoCirugia $tipoCirugia): RedirectResponse
    {
        $vigente = $this->vigente($tipoCirugia);

        if (! $vigente) {
            return back()->with('aviso', 'Ese tipo de cirugía ya no tiene plantilla vigente.');
        }

        $vigente->update(['fechaFinConfigConsentimiento' => now()]);

        Auditor::registrar(Auditor::BAJA, $vigente, $this->comoSeLlama($tipoCirugia));

        return redirect()
            ->route('admin.consentimientos.show', $tipoCirugia)
            ->with('exito', 'Plantilla retirada. Queda en el historial y se puede publicar una nueva.');
    }

    private function vigente(TipoCirugia $tipo): ?ConfigConsentimiento
    {
        return $tipo->configConsentimientos()
            ->whereNull('fechaFinConfigConsentimiento')
            ->first();
    }

    /**
     * Se corrige solo lo que todavia no firmo nadie.
     */
    private function seEdita(ConfigConsentimiento $version): bool
    {
        return $version->consentimientoPacientes()->doesntExist();
    }

    private function comoSeLlama(TipoCirugia $tipo): string
    {
        return 'Consentimiento de «'.$tipo->nombreTipoCirugia.'»';
    }
}
