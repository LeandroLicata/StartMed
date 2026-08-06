<div class="grid gap-4 lg:grid-cols-2">
    <article class="rounded-lg bg-white p-5 shadow-sm">
        <h2 class="flex items-center gap-2 text-sm font-bold text-hu-azul"><x-icono nombre="info" /> Datos de mi cirugía</h2>
        <dl class="mt-4 space-y-2 text-sm">
            @foreach(['Procedimiento' => $cirugia['procedimiento'], 'Fecha' => $cirugia['fecha'], 'Hora' => $cirugia['hora'].' hs · llegar a las '.$cirugia['llegada'], 'Cirujano' => $cirugia['cirujano'], 'Anestesista' => $cirugia['anestesista'], 'Dirección' => $cirugia['direccion'], 'Obra social' => $cirugia['cobertura']] as $titulo => $valor)
                <div class="flex flex-wrap justify-between gap-2 rounded-md bg-hu-gris-tenue px-3 py-3"><dt>{{ $titulo }}</dt><dd class="text-right font-bold text-hu-azul">{{ $valor }}</dd></div>
            @endforeach
        </dl>
    </article>
    <article class="rounded-lg bg-white p-5 shadow-sm">
        <h2 class="flex items-center gap-2 text-sm font-bold text-hu-azul"><x-icono nombre="assignment" /> Estado de mi proceso</h2>
        <ul class="mt-3 divide-y divide-hu-gris-suave">
            @foreach([
                ['Turno anestesista', 'Dr. Ramos · Vie 13/06 · 14:00 hs', true],
                ['Turno cardiólogo', 'Dra. Vidal · Jue 12/06', true],
                ['Estudios subidos', 'Hemograma, ECG, Coagulación', true],
                ['Cuestionario preanestésico', 'Completalo antes del 15/06', $portal['estado']['cuestionario'] ?? false],
                ['Consentimiento informado', 'Para firmar antes de la cirugía', $portal['estado']['firmar'] ?? false],
            ] as [$titulo, $detalle, $listo])
                <li class="flex items-center gap-3 py-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-md {{ $listo ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}"><x-icono :nombre="$listo ? 'check' : 'schedule'" /></span>
                    <div class="min-w-0 flex-1"><p class="text-sm font-bold text-hu-azul">{{ $titulo }}</p><p class="text-xs text-hu-gris-medio">{{ $detalle }}</p></div>
                    <span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $listo ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $listo ? 'Listo' : 'Pendiente' }}</span>
                </li>
            @endforeach
        </ul>
    </article>
</div>
