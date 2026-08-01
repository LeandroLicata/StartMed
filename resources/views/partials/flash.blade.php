{{--
    Mensajes de un solo uso que quedaron en la sesion, tipicamente puestos por
    un controlador con ->with('exito', '...') antes de redirigir.
--}}
@foreach (['exito', 'error', 'aviso', 'info'] as $tipo)
    @if (session()->has($tipo))
        <x-alerta :tipo="$tipo" class="mb-4">{{ session($tipo) }}</x-alerta>
    @endif
@endforeach
