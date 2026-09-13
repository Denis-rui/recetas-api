<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 560px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #f59e0b;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 32px 28px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .message {
            font-size: 15px;
            color: #475569;
            margin-bottom: 24px;
        }
        .code-box {
            background-color: #fef3c7;
            border: 2px dashed #f59e0b;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 24px 0;
        }
        .code-label {
            font-size: 13px;
            font-weight: 600;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .code-value {
            font-size: 36px;
            font-weight: 800;
            font-family: 'Courier New', Courier, monospace;
            color: #b45309;
            letter-spacing: 8px;
            margin: 0;
        }
        .validity {
            font-size: 14px;
            color: #64748b;
            text-align: center;
            margin-top: 8px;
        }
        .security-notice {
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 16px;
            font-size: 13px;
            color: #64748b;
            margin-top: 28px;
            border-left: 4px solid #94a3b8;
        }
        .footer {
            padding: 20px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>¿Qué Cocinamos?</h1>
        </div>
        <div class="content">
            <p class="greeting">Hola,</p>
            <p class="message">
                Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.
                Introduce el siguiente código en la aplicación para continuar con el proceso:
            </p>

            <div class="code-box">
                <div class="code-label">Tu código de recuperación</div>
                <div class="code-value">{{ $codigo }}</div>
                <div class="validity">
                    Este código es válido durante <strong>{{ $minutosValidez }} minutos</strong> y puede usarse una sola vez.
                </div>
            </div>

            <div class="security-notice">
                <strong>Aviso de seguridad:</strong> Si no solicitaste este código, puedes ignorar este mensaje.
                Tu contraseña actual permanecerá segura y no se realizará ningún cambio en tu cuenta.
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} ¿Qué Cocinamos?. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
