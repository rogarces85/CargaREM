<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;overflow:hidden;">
                    <tr>
                        <td style="background:#343a40;color:#ffffff;padding:16px 24px;font-size:16px;font-weight:bold;">
                            Cargas REM · Servicio de Salud Osorno
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <h1 style="margin:0 0 8px;font-size:20px;">{{ $titulo }}</h1>
                            <p style="margin:0 0 20px;color:#6c757d;font-size:14px;">
                                Estimado/a {{ $destinatario->name ?? '' }}:
                            </p>

                            <p style="margin:0 0 20px;font-size:14px;line-height:1.5;">
                                {{ $mensaje }}
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:6px 0;color:#6c757d;width:140px;">Establecimiento</td>
                                    <td style="padding:6px 0;">
                                        {{ $envio->establecimiento->codigo }} — {{ $envio->establecimiento->nombre }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6c757d;">Período</td>
                                    <td style="padding:6px 0;">{{ $periodo }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6c757d;">Estado</td>
                                    <td style="padding:6px 0;">{{ str_replace('_', ' ', $envio->estado) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6c757d;">Series</td>
                                    <td style="padding:6px 0;">{{ $envio->archivosVigentes->count() }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;">
                                <a href="{{ url('/envios/' . $envio->id) }}"
                                   style="display:inline-block;background:#007bff;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;font-size:14px;">
                                    Ver el envío en el portal
                                </a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;background:#f8f9fa;color:#6c757d;font-size:12px;">
                            Este aviso lo generó el portal de Cargas REM. No responda a este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
