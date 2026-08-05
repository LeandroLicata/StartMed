{{-- $vista: 'mes' | 'semana' --}}
<div class="inline-flex shrink-0 rounded-full border border-hu-gris-suave bg-white p-1" role="group" aria-label="Vista de la agenda">
        <a
        href="{{ route('cirujano.agenda') }}"
        class="rounded-full px-4 py-1.5 text-sm font-semibold transition-colors
            {{ $vista === 'mes' ? 'bg-hu-azul text-white' : 'text-hu-gris-medio hover:text-hu-azul' }}"
    >
        Mes
    </a>
        <a
        href="{{ route('cirujano.agenda', ['vista' => 'semana']) }}"
        class="rounded-full px-4 py-1.5 text-sm font-semibold transition-colors
            {{ $vista === 'semana' ? 'bg-hu-azul text-white' : 'text-hu-gris-medio hover:text-hu-azul' }}"
    >
        Semana
    </a>
</div>
