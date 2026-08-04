@extends('layouts.app')

@php
    use App\Support\Consentimiento;

    $corrige = $version !== null;
    $titulo = $corrige ? 'Corregir versión' : 'Nueva versión';

    // La vista previa se arma con lo último tipeado si la validación falló.
    $textoActual = old('textoConfigConsentimiento', $texto);
@endphp

@section('titulo', $titulo.': '.$tipo->nombreTipoCirugia)
@section('subtitulo', 'Administración · Consentimientos informados')

@section('contenido')

    <div class="mb-4">
        <x-boton :href="route('admin.consentimientos.show', $tipo)" variante="fantasma" icono="arrow_back" forma="grupo">
            Volver al historial
        </x-boton>
    </div>

    @unless ($corrige)
        <x-alerta tipo="info" class="mb-4">
            Al publicar, la versión vigente se cierra y queda en el historial. Los consentimientos
            ya firmados conservan su propio texto y no cambian.
        </x-alerta>
    @endunless

    <div class="grid gap-5 lg:grid-cols-2">
        <x-tarjeta :titulo="$titulo" icono="draw">
            <form
                method="POST"
                action="{{ $corrige
                    ? route('admin.consentimientos.update', [$tipo, $version])
                    : route('admin.consentimientos.store', $tipo) }}"
                class="space-y-4"
            >
                @csrf
                @if ($corrige)
                    @method('PUT')
                @endif

                <x-textarea
                    nombre="textoConfigConsentimiento"
                    etiqueta="Texto del consentimiento"
                    :valor="$texto"
                    :filas="18"
                    requerido
                    class="font-mono text-xs leading-relaxed"
                />

                <div class="flex items-center gap-3">
                    <x-boton tipo="submit" icono="check_circle">
                        {{ $corrige ? 'Guardar corrección' : 'Publicar versión' }}
                    </x-boton>

                    <x-boton :href="route('admin.consentimientos.show', $tipo)" variante="fantasma">
                        Cancelar
                    </x-boton>
                </div>
            </form>
        </x-tarjeta>

        <div class="space-y-5">
            <x-tarjeta titulo="Marcadores disponibles" icono="info">
                <dl class="space-y-2 text-sm">
                    @foreach (Consentimiento::MARCADORES as $marcador => $significado)
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <dt>
                                <code class="rounded bg-hu-azul-tenue px-1.5 py-0.5 text-xs text-hu-azul">{{ $marcador }}</code>
                            </dt>
                            <dd class="text-xs text-hu-gris-medio">{{ $significado }}</dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-3 text-xs text-hu-gris-medio">
                    {{-- Entidades HTML: un {{…}} literal lo intentaría interpretar Blade. --}}
                    Cualquier otro <code class="text-xs">&#123;&#123;…&#125;&#125;</code> se rechaza al guardar:
                    quedaría literal en el documento que firma el paciente.
                </p>
            </x-tarjeta>

            <x-tarjeta titulo="Vista previa" icono="description">
                <p class="mb-3 text-xs text-hu-gris-medio">
                    Con datos de ejemplo. Sirve para comprobar que los marcadores estén bien escritos,
                    que es lo único que no se ve leyendo la plantilla.
                </p>

                <pre class="max-h-96 overflow-auto rounded-xl bg-hu-gris-tenue/40 p-4 font-sans text-sm
                            leading-relaxed whitespace-pre-wrap text-hu-gris">{{ Consentimiento::ejemplo($textoActual) }}</pre>
            </x-tarjeta>
        </div>
    </div>

@endsection
