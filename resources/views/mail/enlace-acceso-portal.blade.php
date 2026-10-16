<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:24px;background:#eef2f5;font-family:Montserrat,Arial,sans-serif;color:#59595B;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;">
        <h1 style="margin:0 0 16px;font-size:18px;color:#003764;">Hospital Universitario</h1>

        <p style="margin:0 0 16px;font-size:14px;line-height:1.5;">
            Hola {{ $nombre }}, pediste ingresar al portal del paciente.
        </p>

        <p style="margin:0 0 24px;">
            <a href="{{ $enlace }}" style="display:inline-block;padding:12px 24px;border-radius:999px;background:#003764;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;">
                Ingresar al portal
            </a>
        </p>

        <p style="margin:0 0 8px;font-size:12px;line-height:1.5;">
            El enlace vale {{ $minutos }} minutos y sirve una sola vez. Si pedís otro, este deja de funcionar.
        </p>
        <p style="margin:0;font-size:12px;line-height:1.5;">
            Si no fuiste vos, ignorá este mensaje: no se entra a ninguna cuenta sin hacer clic en el enlace.
        </p>
    </div>
</body>
</html>
