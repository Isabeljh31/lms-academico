# 09. HTTP, consola, colas, notificaciones, correo y providers

Alcance: `app/Http/Controllers` (6), `app/Console/Commands` (4), `routes/console.php` (programador), `config/queue.php`, `app/Notifications` (12), `app/Mail` (1), `resources/views/emails`, `app/Providers` (2), `bootstrap/app.php`, `bootstrap/providers.php`, `app/View/Components` (2) y `app/Enums` (1). Los middleware y las policies están en el archivo 06.

## 1. Controladores

| Controlador | Ruta | Qué hace | Autorización | Pruebas |
|---|---|---|---|---|
| `ArchivoController` (62 líneas) | `archivos.entrega`, `archivos.mensaje`, `archivos.portada`, `archivos.foto` | Redirige a una URL temporal de 10 min del archivo privado (RNF-06); `Cache-Control: private, max-age=300` | entrega: `EntregaPolicy::ver`; mensaje: remitente o destinatario; portada: `CursoPolicy::ver`; foto: cualquier autenticado | `Seguridad/ArchivosPrivadosTest` |
| `RecursoLeccionController` (50) | `cursos.lecciones.recurso` | Abre el video, el material o un recurso adicional; si la lección se completa "al abrir su recurso", registra `LeccionCompletada` (RF-67) | `CursoPolicy::ver`; la lección debe ser del curso; al estudiante, lección y módulo visibles | `RecursosLeccionTest`, `ArchivosPrivadosTest` |
| `Api\MatriculaController` (96) | `POST api/v1/matriculas` | RF-100 a RF-106: exige JSON (415), valida, busca el curso por id o código, crea la cuenta si no existe y sincroniza la matrícula en una transacción; responde 201 o 200 | middleware `throttle:api-integracion` y `ValidarApiKey` | `Seguridad/ApiIntegracionTest` |
| `Reportes\BoletaPdfController` (27) | `reportes.boleta` (`cursos/{curso}/boleta.pdf`) | Genera un PDF con dompdf | `CursoPolicy::verProgreso` | `ReportesTest:73-96` |
| `Auth\VerifyEmailController` (27) | `verification.verify` | Marca el correo como verificado (Breeze) | `signed`, `throttle:6,1` | `Auth/EmailVerificationTest` |
| `Controller` (8) | — | Clase base vacía | — | — |

Problemas de los controladores:
- **H-23 (Medio). Hecho:** la "Boleta de notas" en PDF (`resources/views/reportes/boleta-pdf.blade.php`, 65 líneas) muestra solo el promedio y el detalle de **tareas y quizzes de práctica** (`ProgresoAcademicoService::progresoEstudiante`). No muestra los rubros del esquema, sus pesos ni la nota final ponderada, que es lo que RF-59 define como desglose del estudiante y lo que sí muestra la boleta en pantalla (`Cursos\BoletaEstudiante`). Además mezcla en un documento llamado "boleta" las notas de práctica que RF-48 y RF-60 dicen mantener aparte. La prueba `ReportesTest::test_estudiante_inscrito_puede_descargar_su_boleta_en_pdf` solo comprueba el tipo de contenido.
- Hecho: `BoletaPdfController` autoriza con `verProgreso`, que también deja pasar al profesor y al Supervisor, pero siempre genera la boleta de `auth()->user()` (línea 17). Un profesor obtiene un PDF vacío con su propio nombre como "Estudiante" (archivo 06).
- Hecho: `Api\MatriculaController` no comprueba el estado del curso: se puede matricular por API en un curso en borrador o despublicado (solo se rechaza el archivado, en `GestorMatriculas`). Opinión: aceptable (la matrícula puede preceder a la publicación).
- Hecho: si la cuenta ya existe, la API no actualiza el nombre recibido (líneas 66-79) y, si el correo pertenece a un profesor o supervisor, la transacción falla con 422 ("Solo se pueden matricular cuentas con rol Estudiante"), sin crear nada. Correcto.
- Hecho: `RecursoLeccionController` hace `redirect()->away()` a la URL que escribió el profesor (`video_url`, recurso tipo enlace). Es una redirección abierta controlada por un docente del curso. Riesgo bajo.

## 2. Arranque (`bootstrap/app.php`, 41 líneas, y `bootstrap/providers.php`)

| Elemento | Configuración real | Evidencia |
|---|---|---|
| Rutas | `web`, `api`, `console` y salud en `/up` | líneas 15-20 |
| Proxies | `trustProxies(at: '*')` con `X-Forwarded-Proto`, `Host` y `Port` | línea 24 |
| Middleware global agregado | `ForzarHttps` | línea 25 |
| Alias | `role`, `permission`, `role_or_permission` (Spatie) y `primer.login` | líneas 26-31 |
| Grupo `web` | se agregan `CuentaActiva` y `PrimerLogin` | líneas 33-35 |
| Excepciones | JSON para `api/*` o si se espera JSON | líneas 37-40 |
| Providers | `AppServiceProvider` y `VoltServiceProvider` | `bootstrap/providers.php` |

- **H-22 (Alto, condicionado). Hecho verificado con una prueba temporal** (`HostReenviadoTest`, en el scratchpad de esta sesión, fuera del repositorio; pasa = el defecto existe): como se confía en `X-Forwarded-Host` de **cualquier** origen (`at: '*'`), una petición con `X-Forwarded-Host: atacante.example` hace que Laravel genere URL absolutas con ese dominio (la página de login mostró `https://atacante.example/forgot-password`). No hay `TrustHosts` ni `URL::forceRootUrl`; en producción solo se fuerza el esquema (`AppServiceProvider.php:67-69`).
  - Impacto: si el proxy de Railway reenvía el `X-Forwarded-Host` que manda el cliente en vez de reemplazarlo, un atacante puede pedir el restablecimiento de contraseña de otra persona y el correo llevará el enlace con el token hacia su dominio (*password reset poisoning*). También afecta a los enlaces de los correos encolados si se generan en una petición.
  - No verificado: el comportamiento del proxy de Railway con ese encabezado.
- Hecho: `X-Forwarded-For` no es de confianza (H-08). La intención documentada es que la IP no se pueda falsear, pero el *host* sí se puede.

## 3. Comandos artisan y programador

### 3.1 Comandos propios (4)

| Comando | Clase | Qué hace | Programado | Pruebas |
|---|---|---|---|---|
| `bot:detectar-atrasos` | `DetectarAtrasosBot` (25 líneas) | Aplica la zona horaria y llama a `PropuestaBotService::detectarAtrasos` | diario 06:00 | `PropuestaBotTest` |
| `comunicados:publicar` | `PublicarComunicadosProgramados` (40) | Notifica los comunicados institucionales ya publicados y no notificados; marca `notificado` | cada 5 min | `Flujos/CasosDeUsoTest` |
| `contenido:notificar-programado` | `NotificarContenidoProgramado` (115) | Avisa (una vez, `aviso_programado_en`) el contenido programado cuya fecha llegó, solo si es visible; en tareas y quizzes respeta `ver` por estudiante | cada 5 min | `Flujos/CasosDeUsoTest` |
| `archivos:privatizar` | `PrivatizarArchivos` (144) | Simulación por defecto; con `--aplicar` mueve archivos del disco `public` al `local` y pasa a `private` los de Cloudinary de la carpeta `lms-academico/`, y reemplaza la URL por `privado://` en 6 tablas y en `modulos.materiales` | manual | `Seguridad/ArchivosPrivadosTest` |

Problemas:
- Hecho: ninguna tarea programada usa `withoutOverlapping()` ni `onOneServer()` (`routes/console.php:13-19`). Si un envío de comunicados tarda más de 5 minutos, o si hay dos procesos de programador (por ejemplo, `railway.json` y el `Procfile` a la vez, archivo 03 § 5), se pueden enviar avisos duplicados: `comunicados:publicar` marca `notificado` **después** de enviar (línea 33).
- Hecho: `comunicados:publicar` y `contenido:notificar-programado` envían notificaciones síncronas a todos los destinatarios dentro del comando (sin cola).
- Hecho: `PrivatizarArchivos` actualiza la base fila por fila sin transacción y no es idempotente ante fallos parciales en Cloudinary: si `rename` funciona y la actualización de la base falla, la URL pública queda en la base apuntando a un recurso que ya es privado (enlace roto). Riesgo bajo; el comando informa los fallidos.
- Hecho: `PrivatizarArchivos` lee el archivo completo en memoria (`Storage::get`, línea 107).
- Hecho: los datos de la base local no tienen ninguna referencia `privado://` (0 de 71 entregas, archivo 04b § 7): el comando no se ha ejecutado con `--aplicar` en la base local. En producción, no verificado.

### 3.2 Cómo se ejecuta el programador (hecho)
- `railway.json:8`: un bucle de shell `while true; do php artisan schedule:work; sleep 5; done` en segundo plano dentro del mismo contenedor web, con salida a `storage/logs/scheduler.log`.
- `Procfile`: procesos separados `scheduler` y `worker`.
- Riesgos (detalle en el archivo 15): logs en archivos del contenedor efímero; si el proceso web cae, el bucle sigue; si se escala a dos réplicas, hay dos programadores.

## 4. Colas

| Aspecto | Hecho | Evidencia |
|---|---|---|
| Conexión | `database` por defecto; `retry_after` 90 s; `after_commit=false` | `config/queue.php:16,38-44` |
| Worker | `queue:work --tries=3 --max-time=3600` en bucle | `railway.json:8`, `Procfile` |
| Trabajos encolados | `BienvenidaMail` (`ShouldQueue` + `ShouldBeEncrypted`) y `MatriculadoEnCurso` (`ShouldQueue`) | `app/Mail/BienvenidaMail.php:23`, `app/Notifications/MatriculadoEnCurso.php:16` |
| Jobs propios | ninguno (`app/Jobs` no existe) | archivo 01 |
| Cifrado | Solo el correo de bienvenida (lleva la contraseña temporal); el payload se cifra con `APP_KEY` | `BienvenidaMail.php:18-21` |
| Zona horaria del worker | UTC (no aplica la configuración de la institución) | H-21, archivo 08 § 1 |

Problemas:
- Hecho: `after_commit=false`. En `Api\MatriculaController` el correo de bienvenida y la notificación de matrícula se encolan **dentro** de la transacción (`DB::transaction`, líneas 65-84). Como la cola `database` usa la misma conexión, si la transacción se revierte la fila del trabajo también se revierte (por análisis). Si en el futuro la cola pasa a Redis o SQS, se enviarían correos de matrículas revertidas.
- Hecho: si `APP_KEY` cambia, los trabajos cifrados pendientes no se pueden descifrar y fallan.
- Hecho: los fallidos van a `failed_jobs` (configuración por defecto de Laravel). No hay alertas ni revisión programada de esa tabla.

## 5. Notificaciones (12)

| Notificación | Canales | Cola | Quién la dispara | RF |
|---|---|---|---|---|
| `MatriculadoEnCurso` | database, mail | Sí | `GestorMatriculas` (manual y API) | RF-33, regla 10, RF-84 |
| `NuevaTareaAsignada` | database | No | `Tareas\Formulario`, `Tareas\Index`, comando de programado | RF-84 |
| `CambioFechaTarea` | database | No | `Tareas\Formulario` | RF-84 |
| `TareaCalificada` | database | No | `Tareas\Detalle`, `Docente\PorCalificar` (no `LibroCalificaciones`, H-16) | RF-84 |
| `EntregaDevuelta` | database | No | `Tareas\Detalle::devolver` | RF-49 |
| `NuevoQuizPublicado` | database | No | `Quizzes\Formulario`, `Quizzes\Index`, comando de programado | RF-84 |
| `CambioFechaQuiz` | database | No | `Quizzes\Formulario` | RF-84 |
| `NotaQuizModificada` | database | No | `Quizzes\Formulario::persistir` (recálculo) | RF-53 |
| `NotaRubroRegistrada` | database | No | `LibroCalificaciones::guardarNotaRubro` | RF-84 |
| `NuevoContenidoPublicado` | database | No | `Lecciones\Index`, comando de programado | RF-84 |
| `NuevoMensaje` | database | No | `Mensajeria\Bandeja::enviar` | RF-83 |
| `NuevoComunicado` | database | No | `Admin\Comunicados`, `Cursos\Show::publicarAnuncio`, comando `comunicados:publicar` | RF-84, RF-87 |

Cobertura de RF-84 ("nueva tarea, nuevo quiz, nueva calificación, mensaje nuevo, nuevo contenido, cambio de fecha, nuevo comunicado y matrícula/acceso habilitado"): las 8 categorías tienen notificación. Excepciones: la calificación de tareas desde el libro (H-16) y la reactivación del acceso local de una matrícula de API, que sí notifica solo cuando `GestorMatriculas` la trata como nueva (`$nueva`, `GestorMatriculas.php:95-118`).

RF-85 (contador visible desde cualquier pantalla) y RF-86 (marcar como leídas): `Notificaciones\Campanita` en el layout (07b § 4.1).

Problemas:
- Hecho: 11 de 12 notificaciones son síncronas. Notificar a todo un curso o a toda la institución se hace dentro de la petición del profesor o del Supervisor (07a § 3.1).
- Hecho: el texto de cada notificación se guarda en `notifications.data` en el momento de crearla. Si después cambia la fecha o el título, el aviso conserva el texto viejo (esperado).
- Hecho: `TareaCalificada` muestra la nota con el formato del decimal de la base (por ejemplo, `18.00/20`), sin usar el formato regional.
- Hecho: los textos de `MailMessage` (`MatriculadoEnCurso`) están en español, pero la plantilla de correo de Laravel (botón, pie "If you're having trouble clicking...") queda en inglés porque no hay traducciones (H-17). Lo mismo con el correo de restablecimiento de contraseña de Laravel (`ResetPassword`), que no fue personalizado (búsqueda de `ResetPassword` y `toMail` en `app/`: solo `MatriculadoEnCurso`).

## 6. Correo

- `BienvenidaMail` (50 líneas): asunto "Bienvenido a LearnUp — Tus credenciales de acceso"; vista `resources/views/emails/bienvenida.blade.php` (50 líneas) con la contraseña temporal (línea 35) y el enlace de login. Encolado y cifrado en la cola.
- Driver: SMTP según `.env` local (`MAIL_MAILER=smtp`; no se leyeron credenciales). No se probó un envío real (archivo 11).
- Hecho: la contraseña temporal viaja en claro dentro del correo (es lo que pide RF-04). El enlace no es de un solo uso y la contraseña no caduca: un correo leído meses después sigue sirviendo mientras el usuario no haya hecho su primer ingreso. El ERS no fija caducidad (opinión: conviene).
- Hecho: los enlaces de los correos encolados se generan en el worker con `APP_URL` (no hay petición). Si `APP_URL` no está bien configurado en Railway, los enlaces apuntan a `http://localhost` (archivo 15).

## 7. Providers, componentes de vista y enum

### 7.1 AppServiceProvider (108 líneas)
- `register`: enlaza `ProveedorIA` con `ClaudeBotService` si `services.ia.proveedor = claude`, si no `GroqProveedor` (líneas 41-45); `ConfiguracionPlataforma` como *singleton* (línea 48).
- `boot`:
  - registra las 7 policies a mano (líneas 56-62);
  - `aplicarConfiguracionRegional` (zona horaria en peticiones web y pruebas, y 3 macros de Carbon) (líneas 87-106);
  - en producción `URL::forceScheme('https')` (líneas 67-69);
  - `RateLimiter` `api-integracion`: 60 por minuto por `$request->ip()` (líneas 73-75; H-08).
- Problemas:
  - Hecho: `aplicarZonaHoraria` consulta la tabla `configuraciones_plataforma` en cada petición web (una consulta por petición, memorizada luego en el *singleton*).
  - Hecho: no se define `Password::defaults()` (H-18) ni `Model::shouldBeStrict()` / `preventLazyLoading()` en desarrollo, que ayudarían a detectar N+1 (opinión; archivo 14).

### 7.2 VoltServiceProvider (24 líneas)
- Monta Volt sobre `resources/views/livewire` y `resources/views/pages`. La carpeta `resources/views/pages` no existe (búsqueda en `resources/views`); los Volt viven en `livewire/pages` y `livewire/profile`. Inofensivo.

### 7.3 View\Components\AppLayout y GuestLayout (17 líneas cada uno)
- Devuelven `layouts.app` y `layouts.guest` para `<x-app-layout>` y `<x-guest-layout>` (Breeze). Se usan en `dashboard.blade.php` y `profile.blade.php` (archivo 10).

### 7.4 Enums\Rol (19 líneas)
- `Supervisor`, `Profesor`, `Estudiante` con `etiqueta()`. Usado en componentes, servicios y seeders. Coincide con los 3 roles del ERS (§ 2.3).

## 8. Hallazgos nuevos de este archivo

| ID | Severidad | Resumen |
|---|---|---|
| H-22 | Alto (condicionado al proxy) | Se confía en `X-Forwarded-Host` de cualquier origen: las URL generadas (incluido el enlace de restablecimiento) pueden apuntar a un dominio del atacante |
| H-23 | Medio | La boleta en PDF no muestra rubros ni nota final (RF-59); solo práctica |
