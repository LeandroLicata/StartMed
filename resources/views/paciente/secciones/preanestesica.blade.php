@if($portal['estado']['cuestionario'] ?? false)
<div class="rounded-lg border border-emerald-300 bg-emerald-50 p-5 text-emerald-900"><h2 class="font-bold">Cuestionario enviado</h2><p class="mt-1 text-sm">El Dr. Ramos ya puede revisar tus respuestas.</p></div>
@else
<div class="mb-4 rounded-lg border border-hu-azul/20 bg-hu-azul-tenue p-4 text-sm text-hu-azul"><strong>Cuestionario preanestésico</strong><p class="mt-1 text-xs">Completalo antes de tu turno con el Dr. Ramos. Solo te lleva 3 minutos.</p></div>
<form method="POST" action="{{ route('paciente.accion', 'cuestionario') }}" class="grid gap-4 lg:grid-cols-2">@csrf
    @foreach([
        ['alergia', '¿Alergia a medicamentos?'], ['cirugias_previas', '¿Cirugías previas?'], ['fuma', '¿Fumás?'], ['complicaciones_anestesia', '¿Complicaciones con anestesias?']
    ] as [$nombre, $etiqueta])
        <fieldset class="rounded-lg bg-white p-5 shadow-sm"><legend class="text-xs font-bold uppercase text-hu-azul">{{ $etiqueta }}</legend><div class="mt-3 grid grid-cols-2 gap-2">@foreach(['no' => 'No', 'si' => 'Sí'] as $valor => $texto)<label class="flex cursor-pointer items-center gap-2 rounded-lg border border-hu-gris-suave p-3 text-sm font-semibold has-[:checked]:border-hu-azul has-[:checked]:bg-hu-azul-tenue"><input type="radio" name="{{ $nombre }}" value="{{ $valor }}" class="accent-hu-azul" required> {{ $texto }}</label>@endforeach</div></fieldset>
    @endforeach
    <label class="rounded-lg bg-white p-5 text-xs font-bold uppercase text-hu-azul shadow-sm">Medicación habitual<input name="medicacion" class="mt-3 block w-full rounded-lg border border-hu-gris-suave p-3 text-sm font-normal normal-case" value="Losartán 50 mg · Aspirina 100 mg"></label>
    <label class="rounded-lg bg-white p-5 text-xs font-bold uppercase text-hu-azul shadow-sm">Enfermedad crónica<input name="enfermedad_cronica" class="mt-3 block w-full rounded-lg border border-hu-gris-suave p-3 text-sm font-normal normal-case" value="Hipertensión controlada"></label>
    <button class="rounded-lg bg-hu-azul px-5 py-3 text-sm font-bold text-white hover:bg-hu-azul-claro lg:col-span-2"><x-icono nombre="send" class="mr-1 inline text-base" /> Enviar al Dr. Ramos</button>
</form>
@endif
