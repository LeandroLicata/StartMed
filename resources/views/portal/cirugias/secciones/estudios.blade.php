{{--
    Pestaña "estudios" del detalle. A cargo de: Leandro. Recibe $caso y $soloLectura.

    Un estudio pendiente se sube desde aca; uno subido solo se ve (ver
    Portal\EstudioController por que el paciente no reemplaza). El link de
    "Ver" va a una ruta que firma la URL al pedirla: el puntero no sale al HTML.

    "Ver" abre el archivo en una pestaña nueva; si la entrega falla, en esa
    pestaña queda una pagina suelta (documentos/no-disponible), no otra copia
    del portal. Y no se ofrece cuando ya se sabe que va a fallar (puntero de
    otro gestor).
--}}

@inject('gestor', 'App\Support\GestorDocumental')
@php($estudios = $caso->cirugia->cirugiaTipoEstudios)

<x-tarjeta titulo="Mis estudios" icono="upload_file">
    @if ($estudios->isEmpty())
        <p class="py-6 text-center text-sm text-hu-gris-medio">No te pidieron estudios para esta cirugía.</p>
    @else
        @unless ($soloLectura)
            <p class="mb-2 text-sm text-hu-gris">
                Subí el resultado de cada estudio en PDF o como foto (JPG o PNG), hasta 20 MB.
            </p>
        @endunless

        <ul class="divide-y divide-hu-gris-suave/70">
            @foreach ($estudios as $estudio)
                @php($subido = filled($estudio->urlArchivoCirugiaTipoEstudio))
                @php($errores = $errors->getBag(\App\Http\Controllers\Portal\EstudioController::bolsaDeErrores($estudio)))

                <li class="py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-hu-azul">
                                {{ $estudio->tipoEstudio?->nombreTipoEstudio ?? 'Estudio' }}
                            </p>
                            <p class="text-xs text-hu-gris-medio">
                                @if ($subido)
                                    Subido el {{ $estudio->fechaSubidaCirugiaTipoEstudio?->translatedFormat('j \d\e F') }}
                                @elseif ($soloLectura)
                                    No se subió el resultado.
                                @else
                                    Subilo antes del {{ ($estudio->fechaEsperadaResultadoCirugiaTipoEstudio ?? $caso->cuando())?->translatedFormat('j \d\e F') }}
                                @endif
                            </p>
                        </div>

                        <x-estado :tono="$subido ? 'exito' : 'aviso'">
                            {{ $subido ? 'Subido' : 'Pendiente' }}
                        </x-estado>
                    </div>

                    @if ($subido && $gestor->reconoce($estudio->urlArchivoCirugiaTipoEstudio))
                        <a
                            href="{{ route('portal.cirugias.estudios.archivo', [$caso->cirugia, $estudio]) }}"
                            target="_blank"
                            rel="noopener"
                            class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-hu-azul hover:text-hu-azul-claro"
                        >
                            <x-icono nombre="visibility" class="text-lg" />
                            Ver el resultado
                        </a>
                    @elseif ($subido)
                        <p class="mt-2 inline-flex items-start gap-1 text-sm text-hu-gris-medio">
                            <x-icono nombre="error" class="text-lg text-hu-dorado" />
                            El archivo no está disponible para ver. Si lo necesitás, consultá al equipo del hospital.
                        </p>
                    @elseif (! $soloLectura)
                        <form
                            method="POST"
                            action="{{ route('portal.cirugias.estudios.subir', [$caso->cirugia, $estudio]) }}"
                            enctype="multipart/form-data"
                            class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            @csrf
                            <label class="sr-only" for="archivo-{{ $estudio->idCirugiaTipoEstudio }}">
                                Resultado de {{ $estudio->tipoEstudio?->nombreTipoEstudio }}
                            </label>
                            <input
                                type="file"
                                name="archivoResultadoEstudio"
                                id="archivo-{{ $estudio->idCirugiaTipoEstudio }}"
                                accept=".pdf,image/jpeg,image/png"
                                required
                                @class([
                                    'block w-full min-w-0 rounded-xl border text-sm text-hu-gris',
                                    'file:mr-3 file:border-0 file:bg-hu-azul-tenue file:px-4 file:py-2.5 file:font-semibold file:text-hu-azul',
                                    'border-red-600' => $errores->any(),
                                    'border-hu-gris-suave' => ! $errores->any(),
                                ])
                            >
                            <x-boton icono="upload_file" class="shrink-0">Subir</x-boton>
                        </form>

                        @foreach ($errores->all() as $mensaje)
                            <p class="mt-1 text-xs text-red-700">{{ $mensaje }}</p>
                        @endforeach
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-tarjeta>
