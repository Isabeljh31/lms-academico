<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido a LearnUp</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #4f7f7a 0%, #2f5a5a 100%); padding: 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .header p { color: rgba(255,255,255,.7); margin: 8px 0 0; }
        .body { padding: 32px; }
        .cred-box { background: #f8f4f0; border: 1px solid #e8ddd5; border-radius: 8px; padding: 20px; margin: 24px 0; }
        .cred-box p { margin: 0 0 8px; font-size: 13px; color: #666; }
        .cred-box .value { font-family: monospace; font-size: 16px; font-weight: bold; color: #2f5a5a; }
        .btn { display: inline-block; background: #4f7f7a; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .warning { background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #7a6000; margin-top: 16px; }
        .footer { background: #f8f8f8; padding: 20px 32px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🎓 Bienvenido a LearnUp</h1>
        <p>Tu cuenta ha sido creada</p>
    </div>
    <div class="body">
        <p>Hola <strong>{{ $usuario->name }}</strong>,</p>
        <p>El Supervisor ha creado tu cuenta en la plataforma LearnUp. A continuación encontrarás tus credenciales de acceso:</p>

        <div class="cred-box">
            <p>Correo electrónico:</p>
            <span class="value">{{ $usuario->email }}</span>
            <p style="margin-top: 16px;">Contraseña temporal:</p>
            <span class="value">{{ $passwordTemporal }}</span>
        </div>

        <a href="{{ $urlLogin }}" class="btn">Iniciar sesión en LearnUp</a>

        <div class="warning">
            ⚠️ <strong>Importante:</strong> Al iniciar sesión por primera vez, el sistema te pedirá que cambies tu contraseña temporal. Este paso es obligatorio antes de acceder a cualquier otra sección.
        </div>
    </div>
    <div class="footer">
        LearnUp — Plataforma de Aprendizaje en Línea<br>
        Si no esperabas este mensaje, puedes ignorarlo.
    </div>
</div>
</body>
</html>
