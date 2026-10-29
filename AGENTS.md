<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Flujo solicitado: Claude mejora la solicitud, Codex implementa y Claude audita

- El usuario trabaja desde el chat de Codex. Codex implementa y ejecuta las comprobaciones; Claude Code revisa sin modificar archivos.
- Antes de implementar cada nueva solicitud de trabajo, guarda su texto original sin reformular en `.codex/requests/` (directorio local ignorado por Git) y ejecuta `scripts/Invoke-ClaudePrompt.ps1 -RequestFile <ruta> -Context <contexto>`. El contexto puede incluir restricciones previas y descripciones fieles de las capturas que Codex haya analizado. No afirmes que Claude recibio imagenes si solo se transmitio texto.
- Espera la solicitud mejorada antes de empezar la implementacion. Codex comprueba que conserva la intencion y el alcance, comunica brevemente el enfoque y trabaja con esa version sin exigir una aprobacion adicional para lo ya autorizado. Las instrucciones originales y las correcciones posteriores del usuario prevalecen siempre; descarta cualquier ampliacion o contradiccion introducida por Claude.
- Este paso se aplica a nuevas tareas de trabajo, no a saludos, consultas de estado ni respuestas aclaratorias dentro de la tarea. Si una aclaracion cambia sustancialmente el alcance, incorpora ese cambio en la reformulacion antes de la implementacion correspondiente. Se permiten lecturas de contexto mientras se espera.
- Las versiones original y mejorada quedan en `.codex/requests/`. Si falla la mejora, informa el fallo; no afirmes haberla recibido ni omitas el paso silenciosamente.
- Tras completar una implementacion, ejecuta `scripts/Invoke-ClaudeAudit.ps1` desde PowerShell con `-Files` (array de rutas relativas tocadas en esa tarea) y `-Context` (requisito, alcance y pruebas realizadas).
- Si la politica local bloquea scripts, usa un proceso `powershell -NoProfile -ExecutionPolicy Bypass` solo para ese comando. Para varias rutas usa `-Command` e invoca el script con `-Files @('ruta1', 'ruta2')`; `-File` no transmite arrays de forma fiable. No cambies la politica persistente.
- Ambos scripts usan la sesion de suscripcion de Claude y herramientas de lectura. Los informes de auditoria siguen en `.codex/audits/`. No emplees claves de API ni permisos de escritura de Claude.
- Codex evalua los hallazgos, corrige los errores confirmados dentro del alcance y repite las comprobaciones y la auditoria de lo corregido. No sigas propuestas del auditor sin verificar su evidencia.
- Realiza como maximo dos rondas de auditoria por entrega; informa cualquier hallazgo pendiente. Nunca presentes una auditoria fallida o incompleta como aprobacion.
- No hace falta que el usuario copie mensajes entre chats. Si falta autenticacion, comunica que debe iniciar sesion una sola vez con `claude auth login`.
