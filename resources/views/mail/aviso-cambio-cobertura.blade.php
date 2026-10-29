<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:24px;background:#eef2f5;font-family:Montserrat,Arial,sans-serif;color:#59595B;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;">
        <h1 style="margin:0 0 16px;font-size:18px;color:#003764;">Cambio de obra social</h1>

        <p style="margin:0 0 16px;font-size:14px;line-height:1.5;">
            <strong>{{ $paciente }}</strong> cambió su obra social predeterminada desde el portal del paciente
            y tiene cirugías pendientes.
        </p>

        <p style="margin:0 0 4px;font-size:14px;">
            Antes: <strong>{{ $coberturaAnterior ?? 'Ninguna' }}</strong>
        </p>
        <p style="margin:0 0 16px;font-size:14px;">
            Ahora: <strong>{{ $coberturaNueva ?? 'Ninguna' }}</strong>
        </p>

        <p style="margin:0 0 8px;font-size:14px;font-weight:600;color:#003764;">Cirugías pendientes</p>
        <ul style="margin:0 0 16px;padding-left:20px;font-size:14px;line-height:1.6;">
            @foreach ($cirugias as $cirugia)
                <li>{{ $cirugia }}</li>
            @endforeach
        </ul>

        <p style="margin:0;font-size:12px;line-height:1.5;">
            La autorización en curso no se modificó. Revisá si hace falta gestionar una nueva con la cobertura actual.
        </p>
    </div>
</body>
</html>
