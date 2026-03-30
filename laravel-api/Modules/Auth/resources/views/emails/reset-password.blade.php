<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecimiento de Contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f9fafb; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 5px;">
        <!-- Header Corporativo -->
        <h1 style="margin: 0 0 20px 0; font-size: 20px; font-weight: bold; color: #1e3a8a;">Restablecimiento de Contraseña</h1>

        <!-- Content -->
        <p style="margin-bottom: 16px; font-size: 14px; color: #000000;">
            Hola <strong style="font-weight: 600;">{{ $user->name }}</strong>,
        </p>

        @if($reason->isExpiredPassword())
            <div style="background-color: #f0f9ff; border-left: 4px solid #1e3a8a; padding: 16px; margin-bottom: 20px;">
                <strong style="font-weight: 600; font-size: 14px; color: #1e3a8a;">Tu contraseña ha vencido</strong><br>
                <span style="font-size: 14px; color: #000000;">
                    Por políticas de seguridad, las contraseñas deben cambiarse periódicamente.
                    Es necesario que establezcas una nueva contraseña para continuar.
                </span>
            </div>
        @elseif($reason->isForgotPassword())
            <p style="margin-bottom: 16px; font-size: 14px; color: #000000;">
                Hemos recibido una solicitud para restablecer tu contraseña.
            </p>
            <div style="background-color: #f0f9ff; border-left: 4px solid #1e3a8a; padding: 16px; margin-bottom: 20px;">
                <strong style="font-weight: 600; font-size: 14px; color: #1e3a8a;">Nota:</strong>
                <span style="font-size: 14px; color: #000000;">
                    Si no solicitaste este cambio, puedes ignorar este correo.
                </span>
            </div>
        @endif

        <p style="margin-bottom: 16px; font-size: 14px; color: #000000;">
            Haz clic en el siguiente botón para establecer tu nueva contraseña:
        </p>

        <div style="text-align: center; margin: 20px 0;">
            <a href="{{ $resetUrl }}"
               style="display: inline-block; padding: 12px 24px; background-color: #1e3a8a; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: 600;">
                Cambiar mi contraseña
            </a>
        </div>

        <p style="margin-bottom: 8px; font-size: 14px; color: #000000;">
            O copia y pega el siguiente enlace en tu navegador:
        </p>
        <p style="margin-bottom: 20px; font-size: 14px; word-break: break-all; color: #1e40af;">
            {{ $resetUrl }}
        </p>

        <div style="background-color: #f0f9ff; border-left: 4px solid #1e3a8a; padding: 16px; margin-bottom: 20px;">
            <strong style="font-weight: 600; font-size: 14px; color: #1e3a8a;">Requisitos de la nueva contraseña:</strong>
            <ul style="margin-top: 8px; margin-left: 20px; font-size: 14px; color: #000000;">
                <li style="margin: 4px 0;">Mínimo 8 caracteres</li>
                <li style="margin: 4px 0;">Al menos una letra mayúscula (A-Z)</li>
                <li style="margin: 4px 0;">Al menos una letra minúscula (a-z)</li>
                <li style="margin: 4px 0;">Al menos un número (0-9)</li>
                <li style="margin: 4px 0;">Al menos un símbolo especial (!@#$%^&*)</li>
            </ul>
        </div>

        <p style="margin-bottom: 16px; font-size: 14px; color: #000000;">
            <strong style="font-weight: 600;">Importante:</strong> Este enlace es válido por 60 minutos por razones de seguridad.
        </p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0 0 5px 0; font-size: 14px; color: #000000;">Atentamente,</p>
            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #000000;">El equipo de AiProfile</p>
        </div>

    </div>
</body>
</html>
