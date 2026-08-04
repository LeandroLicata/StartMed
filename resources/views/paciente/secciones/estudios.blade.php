<article class="rounded-lg bg-white p-5 shadow-sm">
    <div class="flex items-center justify-between gap-3"><h2 class="flex items-center gap-2 text-sm font-bold text-hu-azul"><x-icono nombre="description" /> Mis estudios</h2></div>
    <div class="mt-4 space-y-3">
        @foreach([['Hemograma y coagulación', 'hemograma_garcia.pdf'], ['Electrocardiograma', 'ecg_garcia.pdf']] as [$titulo, $archivo])
            <div class="flex items-center gap-3 rounded-lg border border-hu-gris-suave bg-hu-gris-tenue/50 p-3"><span class="flex size-10 items-center justify-center rounded-md bg-hu-azul-tenue text-hu-azul"><x-icono nombre="description" /></span><div class="min-w-0 flex-1"><p class="text-sm font-bold text-hu-azul">{{ $titulo }}</p><p class="truncate text-xs text-hu-gris-medio">Subido · {{ $archivo }}</p></div><span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-800">OK</span></div>
        @endforeach
        @if($portal['estado']['estudio'] ?? false)<div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">Nuevo estudio recibido en esta demostración.</div>@endif
    </div>
</article>
<form method="POST" enctype="multipart/form-data" action="{{ route('paciente.accion', 'estudio') }}" class="mt-4 rounded-lg border border-dashed border-hu-gris-suave bg-white p-6 text-center">@csrf
    <x-icono nombre="upload_file" class="text-4xl text-hu-gris-medio" /><label for="archivo" class="mt-2 block text-sm font-bold text-hu-azul">Subir nuevo estudio</label><p class="mt-1 text-xs text-hu-gris-medio">PDF, JPG o PNG · máximo 20 MB</p>
    <input id="archivo" name="archivo" type="file" accept=".pdf,.jpg,.jpeg,.png" class="mx-auto mt-4 block max-w-full text-xs" required><button class="mt-4 rounded-lg bg-hu-azul px-5 py-2 text-sm font-bold text-white hover:bg-hu-azul-claro">Subir</button>
</form>
