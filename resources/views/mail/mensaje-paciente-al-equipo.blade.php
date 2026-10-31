<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:24px;background:#eef2f5;font-family:Montserrat,Arial,sans-serif;color:#59595B;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;">
        <h1 style="margin:0 0 16px;font-size:18px;color:#003764;">Mensaje para el equipo</h1>

        <p style="margin:0 0 16px;font-size:14px;line-height:1.5;">
            <strong>{{ $paciente }}</strong> (DNI {{ $dni }}) envió una consulta sobre su cirugía desde el portal del paciente.
        </p>

        <p style="margin:0 0 4px;font-size:14px;">
            Cirugía: <strong>{{ $tipoCirugia }}</strong> (N.º {{ $idCirugia }})
        </p>
        <p style="margin:0 0 16px;font-size:14px;">
            Fecha: <strong>{{ $fechaCirugia ?? 'Sin fecha asignada' }}</strong>
        </p>

        <p style="margin:0 0 8px;font-size:14px;font-weight:600;color:#003764;">Mensaje</p>
        <div style="margin:0 0 16px;padding:12px 16px;background:#f8fafc;border-left:4px solid #003764;border-radius:4px;font-size:14px;line-height:1.6;white-space:pre-wrap;">{{ $mensaje }}</div>

        <p style="margin:0;font-size:12px;line-height:1.5;">
            @if ($replyToEmail)
                Podés responder directamente a este correo (responde a {{ $replyToEmail }}) o comunicarte con el paciente por teléfono.
            @else
                El paciente no tiene un correo electrónico registrado. Comunicate por teléfono.
            @endif
        </p>
    </div>
</body>
</html>
