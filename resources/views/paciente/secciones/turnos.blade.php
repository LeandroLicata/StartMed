<article class="rounded-lg bg-white p-5 shadow-sm">
    <h2 class="flex items-center gap-2 text-sm font-bold text-hu-azul"><x-icono nombre="event" /> Turnos médicos</h2>
    <div class="mt-4 divide-y divide-hu-gris-suave">
        @foreach([
            ['Evaluación con anestesista', 'Dr. Ramos · Viernes 13/06 · 14:00 · Consultorio 4', 'Confirmado'],
            ['Evaluación cardiológica', 'Dra. Vidal · Jueves 12/06 · 11:30 · Consultorio 8', 'Confirmado'],
            ['Evaluación clínica preoperatoria', 'No requerida en este caso', 'No requerida'],
        ] as [$titulo, $detalle, $estado])
            <div class="flex items-center gap-3 py-4 first:pt-0 last:pb-0">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-md {{ $estado === 'Confirmado' ? 'bg-emerald-100 text-emerald-700' : 'bg-hu-gris-tenue text-hu-gris-medio' }}"><x-icono :nombre="$estado === 'Confirmado' ? 'check' : 'stethoscope'" /></span>
                <div class="min-w-0 flex-1"><p class="text-sm font-bold text-hu-azul">{{ $titulo }}</p><p class="text-xs text-hu-gris-medio">{{ $detalle }}</p></div>
                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-800">{{ $estado }}</span>
            </div>
        @endforeach
    </div>
</article>
<aside class="mt-4 rounded-lg border border-hu-azul/20 bg-hu-azul-tenue p-4 text-xs text-hu-azul"><strong>¿Por qué estos turnos?</strong><p class="mt-1">El anestesista elige el tipo de anestesia más seguro y el cardiólogo verifica que tu corazón esté en condiciones.</p></aside>
