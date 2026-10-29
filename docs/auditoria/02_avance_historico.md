# 02. Avance histórico (desde git)

Fuente: `git log --all --shortstat`, `git branch -a -vv`, `git status`, `git diff --stat HEAD` y `git log --diff-filter=A -- database/migrations`, ejecutados el 13/10/2026. Solo comandos de lectura.

## 1. Ramas

| Rama | Último commit | Estado frente al remoto |
|---|---|---|
| `feature/auditoria-ers-contenido-por-semanas-y-mejoras-ux` (actual) | `93fc323` (05/10/2026) | Sincronizada con `origin`. El PR no está fusionado: `origin/main` sigue en `e55df57`. |
| `feature/rediseno-estudiante-y-modulos` (local) | `e55df57` | 9 commits por delante de su rama remota (`8ead19b`) |
| `main` (local) | `6ca5c1e` | 18 commits por detrás de `origin/main` |
| `origin/main` | `e55df57` (25/09/2026) | Rama principal del equipo |
| `origin/feature/docente-visibilidad-y-estadisticas-quiz` | `60a231b` | Fusionada en `main` (PR #4 y #5) |
| `origin/feature/rediseno-learnup-y-grupos` | `8ff216b` | Fusionada en `main` |
| `origin/mejora-lms` | `c0c3513` | Fusionada en `main` (PR #1) |

Total: 20 commits en todas las ramas, todos alcanzables desde `HEAD`.

## 2. Línea de tiempo de commits

| Fecha | Commit | Autor | Mensaje | Archivos | Líneas + / − |
|---|---|---|---|---|---|
| 06/09/2026 01:04 | `6ca5c1e` | Blanca Isabel Joaquin H. | Setup inicial: Docker, Supabase, roles de Spatie, Breeze y modelos Eloquent | 130 | +17.620 / −0 |
| 11/09/2026 22:45 | `c0c3513` | José Gerónimo | Dependencias/first | 8 | +55 / −68 |
| 14/09/2026 12:52 | `e2f4c64` | José Gerónimo | Merge PR #1 (`mejora-lms`) | — | — |
| 16/09/2026 13:49 | `3f413c0` | Eliel Ashley Rojas Rincon | feat(datos): migraciones, modelos, factories y seeders de 9 módulos | 35 | +644 / −106 |
| 16/09/2026 13:49 | `d9f8ce0` | Eliel Ashley Rojas Rincon | feat(lógica): rutas, Livewire, policies y servicios | 46 | +2.416 / −2 |
| 16/09/2026 13:49 | `092f77d` | Eliel Ashley Rojas Rincon | feat(ui): vistas de 9 módulos y rediseño del Estudiante | 37 | +2.509 / −181 |
| 16/09/2026 13:49 | `419792f` | Eliel Ashley Rojas Rincon | test: cobertura de los 9 módulos | 15 | +1.511 / −7 |
| 16/09/2026 13:49 | `e27667a` | Eliel Ashley Rojas Rincon | chore: dependencias, despliegue y documentación | 51 | +6.136 / −107 |
| 16/09/2026 13:53 | `f2bd2f7` | Eliel Ashley Rojas Rincon | Merge `origin/main` (resolución de conflictos) | — | — |
| 16/09/2026 13:54 | `8ead19b` | Eliel Ashley Rojas Rincon | chore: regenerar `package-lock.json` | 1 | +1 / −1 |
| 18/09/2026 00:05 | `5c2f79e` | jharol-23 | Rediseño de marca LearnUp, autenticación en español y grupos | 51 | +1.316 / −393 |
| 18/09/2026 00:06 | `b690942` | jharol-23 | docs: referencia de diseño Figma | 27 | +2.037 / −0 |
| 18/09/2026 12:20 | `1fe69b6` | José Gerónimo | Merge PR #2 | — | — |
| 18/09/2026 16:51 | `8ff216b` | José Gerónimo | Funciones Docente/Admin/Estudiante | 29 | +2.629 / −94 |
| 18/09/2026 16:55 | `5b8a17c` | José Gerónimo | Funciones añadidas ADMIN/PROF/ESTUD (merge) | — | — |
| 25/09/2026 10:51 | `79b61a0` | Shol551 | Ocultar/eliminar tareas y quizzes; estadísticas de quiz | 22 | +633 / −20 |
| 25/09/2026 11:24 | `822a59f` | José Gerónimo | Merge PR #4 | — | — |
| 25/09/2026 16:20 | `60a231b` | José Gerónimo | RE-Estructuración (adecuación al ERS v2) | 84 | +6.663 / −744 |
| 25/09/2026 16:21 | `e55df57` | José Gerónimo | Merge PR #5 | — | — |
| 05/10/2026 14:06 | `93fc323` | Eliel Ashley Rojas Rincon | Auditoría del ERS, contenido por semanas y mejoras de UX | 229 | +20.236 / −7.087 |

Observaciones (hechos):
- 5 autores. El commit de mayor volumen (`93fc323`, 229 archivos) agrupa varias semanas de trabajo en un solo commit, lo que dificulta revisarlo y revertirlo por partes.
- El commit `3f413c0` modificó 11 migraciones ya creadas en `6ca5c1e`. Con `git diff -w` no hay diferencias: el cambio es solo de sangría (formateo), no de esquema.

## 3. Etapas y fases del ERS

La correspondencia entre etapas y fases es una interpretación hecha a partir de los mensajes de commit, los archivos tocados y `docs/CAMBIOS.md`. No es un dato registrado en git.

| Etapa | Fechas | Qué se construyó | Fase del ERS (§ 11) |
|---|---|---|---|
| 1. Esqueleto | 06–14/09 | Laravel, Breeze y Spatie; modelos base (cursos, inscripciones, módulos, tareas, entregas, quizzes, calendario, bot) | Base de la Fase 1 |
| 2. Módulos iniciales | 16/09 | 9 módulos del "documento del proyecto": rutas, Livewire, policies, servicios, vistas y pruebas; bot con Groq o Claude; notificaciones | Fase 1 (parcial) y Fase 3 (asistente de IA) |
| 3. Rediseño y grupos | 18/09 | Marca LearnUp, autenticación en español, grupos, comunicados | Fase 2 (grupos) |
| 4. Visibilidad y estadísticas | 25/09 | Ocultar o eliminar tareas y quizzes, estadísticas por pregunta | Fase 1 |
| 5. Adecuación al ERS v2 | 25/09 | Campos de autenticación (RF-03 a RF-06), estados de curso, matrícula con origen, bitácora, esquema de evaluación, integración API | Fase 1 completa |
| 6. Auditoría y contenido por semanas | 26/09–05/10 | Lecciones, sesiones, asistencia, mensajería, banco de preguntas, configuración, completitud, trigger de bitácora, profesores adicionales, semanas y Prácticas de campo, RF-108 | Fases 1 y 2 |
| 7. Auditoría final (sin commitear) | 05/10 en adelante | Archivos privados (RNF-06), HTTPS, recursos de lección, clases en vivo, analítica de riesgo sin N+1, colas, despliegue | Fases 2 y 3, y RNF |

## 4. Cambios sin commitear (estado al 13/10/2026)

`git status`: 50 archivos modificados o eliminados y 14 archivos nuevos sin seguimiento (más esta carpeta `docs/auditoria/`; corregido el 14/10/2026, antes decía 15). `git diff --stat HEAD`: 50 archivos, +1.082 / −239 líneas.

| Tipo | Archivos |
|---|---|
| Modificados: código | `Procfile`, `railway.json`, `bootstrap/app.php`, `config/session.php`, `routes/web.php`, `routes/cursos.php`; 11 clases de `app/`: `RecursoLeccionController`, `Admin/Metricas/Index`, `Asistencia/Index`, `Cursos/Show`, `Lecciones/Index`, `BienvenidaMail`, 6 modelos, `MatriculadoEnCurso`, `CalculadoraAsistencia`, `CalculadoraNotas`, `SubidorArchivos`; `DemoAcademicoSeeder` |
| Modificados: vistas | 16 vistas Blade |
| Eliminados | `resources/views/welcome.blade.php` (portal público) |
| Modificados: pruebas | `ExampleTest`, `FlujosPrincipalesTest`, `ApiIntegracionTest`, `SubidaArchivosTest`, `UsuariosTest` |
| Modificados: documentación | `ARQUITECTURA.md`, `docs/AUDITORIA.md`, `docs/CAMBIOS.md`, `docs/PROGRESO.md`, `docs/TRAZABILIDAD.md` |
| Nuevos | `PrivatizarArchivos`, `ArchivoController`, `ForzarHttps`, `RecursoLeccion`, 2 migraciones, `docs/BRIEFING_PROYECTO.md`, 7 archivos de prueba |

Riesgo (opinión): el trabajo de la auditoría final, incluidas 2 migraciones y la privatización de archivos, existe solo en el disco local. Si se pierde la copia local, se pierde ese trabajo.

## 5. Migraciones por fecha y lote

Lotes según `php artisan migrate:status` sobre la base SQLite local. El lote de producción (Supabase) no se pudo verificar.

| Commit que la agregó | Migraciones | Lote local |
|---|---|---|
| `6ca5c1e` (06/09) | 3 de Laravel (`users`, `cache`, `jobs`) y 12 del dominio inicial (`avatar_url`, `cursos`, `inscripciones`, `modulos`, `tareas`, `entregas`, `quizzes`, `resultados_quiz`, `eventos_calendario`, `conversaciones_bot`, `propuestas_bot`, permisos de Spatie) | 1 |
| `3f413c0` (16/09) | `add_materiales_to_modulos`, `create_notifications` | 1 |
| `5c2f79e` (18/09) | `grupos`, `grupo_estudiante`, `tipo_entrega` en `tareas`, `grupo_id` en `entregas` | 2 |
| `8ff216b` (18/09) | `comunicados`, `curso_id` en `comunicados` | 2 |
| `79b61a0` (25/09) | `visible` en `tareas` y en `quizzes` | 2 |
| `60a231b` (25/09) | 7 migraciones ERS v2 (`2026_09_25_170001` a `170007`) | 2 |
| `93fc323` (05/10) | 12 migraciones fechadas del 26/09 al 28/09: lecciones, sesiones y asistencias, mensajes, banco de preguntas, configuración, campos ERS, lecciones completadas, trigger de bitácora, `curso_profesor`, aviso programado, condición de completitud, semanas | 3 a 6 |
| Sin commitear | `2026_10_05_192827_create_recursos_leccion_table`, `2026_10_05_194327_add_enlace_reunion_to_sesiones_clase_table` | 7 y 8 |

## 6. Qué cambió desde el 5 de octubre de 2026

- Commit `93fc323` (05/10, 14:06): contenido por semanas, Prácticas de campo, RF-108 (zona horaria y formato de fecha), rediseño de las propuestas del bot, profesores adicionales y migración de unidades a semanas.
- Después de ese commit, sin commitear:
  - archivos privados (`privado://`, `ArchivoController`, `archivos:privatizar`);
  - HTTPS y HSTS (`ForzarHttps`, proxies de confianza, cookie segura);
  - entidad Recurso (`recursos_leccion`);
  - clases en vivo (`enlace_reunion`);
  - analítica de riesgo con cálculo por lote;
  - correos en cola (bienvenida cifrada);
  - eliminación del portal público (`/` redirige al login o al panel);
  - despliegue (healthcheck, programador, cola y workers);
  - 7 archivos de prueba nuevos (suite: de 444 a 482 casos).
- Las 2 migraciones nuevas son aditivas: crean una tabla nueva o agregan una columna nullable (archivo 04, § 4).
