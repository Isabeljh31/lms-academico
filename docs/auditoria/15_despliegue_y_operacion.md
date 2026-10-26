# 15. Despliegue y operación

Fuentes: `railway.json`, `Procfile`, `docker-compose.yml`, `.env.example` (valores no secretos; los secretos solo se reportan como "con valor"), `config/*.php`, seeders, `docs/BRIEFING_PROYECTO.md` § 5 y `docs/AUDITORIA.md`. No se tuvo acceso a Railway, Supabase, Cloudinary ni al servidor SMTP: todo lo que depende de ellos está marcado como **no verificado**. Una prueba temporal nueva (`HealthcheckHttpTest`, scratchpad de esta sesión).

## 1. `railway.json` (sin commitear, archivo 02 § 4)

| Elemento | Valor | Observación |
|---|---|---|
| Builder | Nixpacks | La versión de PHP y Node la elige Nixpacks (no fijada; `package.json` sin `engines`) |
| Build | `composer install --no-dev --optimize-autoloader && npm ci && npm run build` | `npm ci` instala dependencias de desarrollo con 10 avisos (13 § 12.2); solo afectan el paso de compilación |
| Arranque | `migrate --force`, `storage:link --force`, `config:cache`, `route:cache`, `view:cache`; programador y worker en bucles de shell en segundo plano; `PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload --host=0.0.0.0 --port=$PORT` | Todo en **un** contenedor |
| Healthcheck | `/up`, 120 s | Ver § 3 |
| Reinicio | `ON_FAILURE`, 10 intentos | Solo vigila el proceso principal (`serve`) |

Riesgos (hechos y opiniones marcadas):
- **H-11 (Medio):** `php artisan serve` es el servidor de desarrollo de PHP. Con 4 workers, 4 peticiones lentas simultáneas (por ejemplo, el asistente de IA con su timeout de 20 s, H-15, o una importación de 1000 filas) bloquean al resto de usuarios. Opinión: para producción, Nginx con PHP-FPM, FrankenPHP u Octane.
- Hecho: `migrate --force` en cada arranque. Si Railway escala a 2 réplicas, ambas migran a la vez; con la base remota, una migración larga retrasa el arranque y puede agotar los 120 s del healthcheck.
- Hecho: el programador y el worker escriben en `storage/logs/scheduler.log` y `queue.log` **dentro del contenedor**: no aparecen en los logs de Railway y se pierden en cada despliegue. Si el bucle muere, nadie lo nota (no hay healthcheck de esos procesos).
- Hecho: con más de una réplica habría un programador por réplica, sin `onOneServer()` ni `withoutOverlapping()` (09 § 3): avisos duplicados.
- Hecho: `storage:link` publica `storage/app/public`, donde siguen los archivos anteriores a RNF-06 (13 § 11).

## 2. `Procfile` y `docker-compose.yml`

- `Procfile` define `web` (mismo arranque sin bucles), `scheduler` y `worker` como procesos separados. Si una plataforma usa el `Procfile` **y** `railway.json`, habría programador y worker duplicados (03 § 5). Opinión: elegir uno.
- `docker-compose.yml`: entorno local (no se usa en Railway). No revisado en detalle.

## 3. Healthcheck `/up`

- Hecho: `/up` es la ruta de salud de Laravel (`bootstrap/app.php:19`); solo comprueba que la aplicación arranca, no la base de datos, la cola ni el programador.
- **H-32 (Alto, condicionado). Hecho verificado con prueba temporal** (`HealthcheckHttpTest`): `ForzarHttps` es un middleware global sin excepción para `/up`. En producción, `GET /up` por HTTP sin `X-Forwarded-Proto: https` responde **301** (redirección a HTTPS); con ese encabezado responde 200. Si el healthcheck de Railway llega al contenedor por HTTP sin ese encabezado, el despliegue nunca se marcará como sano y Railway lo revertirá. No verificado cómo hace Railway la comprobación. Este `railway.json` con healthcheck está sin commitear y, según `docs/AUDITORIA.md` § 13.5 (línea 572, "Sin healthcheck ni programador en el despliegue"), todavía no se desplegó.

## 4. Variables de entorno

### 4.1 Valores de `.env.example` relevantes para producción
| Variable | Valor en `.env.example` | Producción esperada | Riesgo |
|---|---|---|---|
| `APP_ENV` | `local` | `production` | Sin `production` no se fuerza HTTPS, no hay HSTS, la cookie no es `secure` y la ruta firmada devuelve 403 en vez de 404 |
| `APP_DEBUG` | `true` | `false` | Con `true` se muestran trazas con rutas y datos de configuración |
| `APP_NAME` | `"LearnUp"` | `LearnUp` | `docs/BRIEFING_PROYECTO.md` § 5 punto 9 pide cambiarlo en producción: el valor actual de producción no es verificable |
| `APP_URL` | `http://localhost` | URL pública con `https` | Los enlaces de los correos encolados usan `APP_URL` (09 § 6) |
| `APP_TIMEZONE` | `UTC` | `UTC` (la zona de la institución se aplica en tiempo de ejecución) | H-21 en el worker |
| `LOG_LEVEL` | `debug` | `warning` o `error` | Logs voluminosos |
| `DB_CONNECTION` / `DB_PORT` | `pgsql` / `6543` | — | Pooler de Supabase en modo transacción (§ 5) |
| `DB_HOST`, `DB_USERNAME` | **con valor** (no se imprime) | — | Identificadores del proyecto de Supabase expuestos en `.env.example` (sin contraseña) |
| `DB_PASSWORD`, `APP_KEY`, `CLOUDINARY_URL`, `GROQ_API_KEY`, `ANTHROPIC_API_KEY`, `MAIL_USERNAME`, `MAIL_PASSWORD` | vacíos | — | Correcto |
| `MAIL_HOST` | con valor (no se imprime) | — | Proveedor SMTP configurado de ejemplo |
| `SESSION_DRIVER` / `SESSION_ENCRYPT` | `database` / `false` | `SESSION_ENCRYPT=true` recomendado | H-30 |
| `QUEUE_CONNECTION` | `database` | — | Worker requerido |
| `FILESYSTEM_DISK` | `local` | — | Sin `CLOUDINARY_URL`, los archivos van al disco del contenedor y **se pierden en cada despliegue** (Railway sin volumen; no verificado si hay volumen) |
| `IA_PROVEEDOR` | `groq` | — | Sin clave, modo simulado |

### 4.2 Variables que usa el código y faltan en `.env.example`
`ARCHIVOS_MAX_KB`, `LOGIN_MAX_INTENTOS`, `LOGIN_MINUTOS_BLOQUEO`, `API_INTEGRACION_LIMITE_POR_MINUTO`, `DB_URL`, `DB_SSLMODE`, `SESSION_SECURE_COOKIE` (además de las variables por defecto de Laravel). Todas tienen un valor por defecto razonable en `config/`, pero no están documentadas para quien despliega.

### 4.3 `.env` local (solo nombres y valores no secretos, archivo 01 § 2)
`DB_CONNECTION=sqlite`; tiene las mismas claves que `.env.example` salvo `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD`. No se leyeron secretos.

## 5. Base de datos: Supabase

- Hecho: `.env.example` apunta al **pooler** de Supabase (comentario "Pooler IPv4", puerto 6543 = modo transacción). `config/database.php` activa `PDO::ATTR_EMULATE_PREPARES => true` en `pgsql` (línea 101), necesario con PgBouncer en modo transacción, y `sslmode` por defecto `prefer` (línea 99).
- Riesgos (por análisis, no verificado contra Supabase):
  - Las migraciones por el pooler en modo transacción funcionan en general, pero Supabase recomienda la conexión directa (5432) o el pooler en modo sesión para migraciones y DDL. El trigger de la bitácora usa `DB::unprepared` (04b § 2).
  - `sslmode=prefer` permite caer a una conexión sin TLS si el servidor lo ofrece; conviene `require`.
  - La aplicación usa la misma base para sesiones, caché, cola y datos: cada petición hace varias consultas a una base remota.
- Hecho: según `docs/AUDITORIA.md` § 11 (tabla de riesgos, línea 284), hay migraciones nuevas "aún no aplicadas en Supabase". El estado real de Supabase **no es verificable** desde aquí. Las 2 migraciones posteriores al 05/10 están sin commitear (H-07).
- Hecho: H-06: el commit `3f413c0` modificó 11 migraciones ya existentes (solo sangría según `git diff -w`), lo que viola la regla del equipo de "solo migraciones aditivas"; si Supabase las tenía aplicadas, no hay efecto en el esquema, pero se perdió la trazabilidad de qué versión se ejecutó.
- No se ejecutó ninguna migración ni *seed* en esta auditoría.

## 6. Cloudinary

- Hecho: `SubidorArchivos` usa Cloudinary solo si existe `CLOUDINARY_URL`; si no, el disco `local` del contenedor (08 § 9).
- Hecho: el SDK es `cloudinary_php` 1.20.2 (API estática 1.x). La corrección del error de producción ("toda subida a Cloudinary fallaba", 03 § 5) está **sin commitear** y nunca se probó con una cuenta real (11 § 5).
- Hecho: `CLOUDINARY_UPLOAD_PRESET` se lee en `config/services.php:43` pero no se usa en `app/` (búsqueda).
- Riesgo (opinión): si en producción falta `CLOUDINARY_URL`, todas las entregas se guardan en el disco efímero del contenedor y desaparecen en el siguiente despliegue, sin error visible.

## 7. Correo (SMTP)

- Hecho: `MAIL_MAILER=smtp` (local y ejemplo). Correos: bienvenida (cola, cifrado), matrícula (cola) y restablecimiento de contraseña (síncrono, plantilla de Laravel en inglés).
- No verificado: entrega real, SPF/DKIM del remitente, que el worker esté corriendo en Railway.
- Hecho: si el worker no corre, los correos de bienvenida quedan en la tabla `jobs` y el Supervisor ve "correo enviado" igualmente (08 § 10).

## 8. Seeders y cuentas de demostración

| Seeder | Qué crea | Contraseña | Riesgo |
|---|---|---|---|
| `RoleAndUserSeeder` | Roles y 3 cuentas (supervisor, profesor y estudiante) con correo `@lms.edu.pe` | `password123` (`RoleAndUserSeeder.php:28,39,50`) | Cuentas con contraseña conocida |
| `CursoSeeder` | Cursos con módulos "Unidad 1/2/3" (`CursoSeeder.php:33`) | — | Textos heredados |
| `DemoAcademicoSeeder` | Datos de ejemplo completos (30 usuarios en la base local) | `password123` (`DemoAcademicoSeeder.php:46`), `primer_login=false` salvo 2 cuentas (línea 111) | Las cuentas de demostración **no** pasan por el cambio obligatorio (`DatosDeEjemploTest::test_las_cuentas_de_demostracion_ingresan_sin_cambio_obligatorio`) |
| `PropuestaBotSeeder` | Propuestas del asistente | — | — |

- Hecho: `DatabaseSeeder` llama a los 4 seeders sin comprobar el entorno (no hay `app()->isProduction()` ni `App::environment`; búsqueda en `database/seeders`). El arranque de Railway no ejecuta `db:seed`.
- **H-33 (Alto si se ejecuta en producción).** Un `php artisan db:seed` en producción (por error o para "probar") crea cuentas de Supervisor y otras con `password123` y sin cambio obligatorio. No verificable si la base de Supabase ya tiene estas cuentas. Arreglo: proteger los seeders de demostración con una comprobación de entorno y desactivar o cambiar las cuentas si existen.

## 9. Estado del checklist de producción (`docs/BRIEFING_PROYECTO.md` § 5)

| # | Paso | Estado verificable | Evidencia |
|---|---|---|---|
| 1 | Respaldar Supabase y `migrate` | No verificado | Sin acceso a Supabase; 2 migraciones sin commitear |
| 2 | Configurar `CLOUDINARY_URL` y probar una subida real | No verificado | Código sin commitear; sin prueba real |
| 3 | `archivos:privatizar` y `--aplicar` | No verificado | — |
| 4 | Verificar SMTP | No verificado | — |
| 5 | Revisar programador y cola | No verificado | Los logs quedan dentro del contenedor (§ 1) |
| 6 | Medir tiempos en Railway | No verificado | RNF-11 |
| 7 | Probar el trigger en PostgreSQL | No verificado | H-05 |
| 8 | Asignar un profesor a cada curso | No verificado | `Admin\Profesores` muestra "cursos sin profesor" |
| 9 | `APP_NAME` = LearnUp | No verificado | `.env.example` ya dice "LearnUp" |
| 10 | Prueba con usuarios y servidor de producción real | Pendiente | H-11 |

Pasos que faltan en ese checklist (opinión, con evidencia en esta auditoría):
- Commitear y fusionar el trabajo pendiente (H-07).
- Fijar `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con `https`, `LOG_LEVEL` alto, `SESSION_ENCRYPT=true`.
- Resolver el healthcheck frente a `ForzarHttps` (H-32) y la confianza en `X-Forwarded-Host` (H-22).
- Proteger los seeders (H-33).
- Configurar un volumen o asegurar `CLOUDINARY_URL` (§ 6).
- Revisar `X-Forwarded-For` y el rate limit (H-08).

## 10. Monitoreo, respaldos y continuidad

- Hecho: no hay APM, rastreo de errores (Sentry o similar) ni alertas (03 § 5; búsqueda en `composer.json`).
- Hecho: el canal de log por defecto es `stack` (`LOG_CHANNEL=stack`); los errores de la web salen por el proceso de `serve` (visibles en Railway), los del programador y la cola no.
- No verificado: respaldos automáticos de Supabase (depende del plan) y de Cloudinary.
- RNF-13 (99 %): depende del hosting; el único proceso supervisado por Railway es `serve`.

## 11. Hallazgos nuevos de este archivo

| ID | Severidad | Resumen |
|---|---|---|
| H-32 | Alto (condicionado) | `ForzarHttps` redirige `/up` con 301 en producción si el healthcheck llega por HTTP sin `X-Forwarded-Proto` |
| H-33 | Alto (si se ejecuta en producción) | Seeders sin guarda de entorno crean cuentas con `password123` y sin cambio obligatorio |

Hallazgos previos detallados aquí: H-06, H-07, H-11, H-21, H-30.
