# 17. Hallazgos y plan de corrección

34 hallazgos (H-01 a H-34). Severidades:
- **Crítico:** impide operar o expone datos de forma segura y general. No se asignó ninguno: los dos riesgos que podrían serlo (H-22 y H-32) dependen de un comportamiento del proxy de Railway que no se pudo verificar.
- **Alto:** defecto confirmado que rompe un requisito o regla importante, o riesgo de seguridad u operación con alta probabilidad.
- **Medio:** defecto o riesgo acotado, con solución alternativa o impacto limitado.
- **Bajo:** mejora, inconsistencia o riesgo menor.

Esfuerzo: **S** = horas (un cambio localizado y su prueba); **M** = 1 a 3 días (varias clases, migración aditiva o refactor menor); **L** = más de 3 días.

"Confirmado" = verificado con una prueba temporal fuera del repositorio (archivo 11 § 1.1). "Condicionado" = el riesgo existe en el código, pero su efecto depende de un dato externo no verificable desde aquí.

## 1. Resumen

| Severidad | Cantidad | IDs |
|---|---|---|
| Crítico | 0 | — |
| Alto | 10 | H-01, H-02, H-03, H-04, H-07, H-12, H-22, H-24, H-32, H-33 |
| Medio | 11 | H-05, H-08, H-10, H-11, H-13, H-14, H-15, H-20, H-21, H-23, H-26 |
| Bajo | 13 | H-06, H-09, H-16, H-17, H-18, H-19, H-25, H-27, H-28, H-29, H-30, H-31, H-34 |

## 2. Hallazgos de severidad Alta

| ID | Hallazgo | Evidencia | Impacto | Arreglo | Esf. |
|---|---|---|---|---|---|
| H-01 | El panel de inicio del estudiante muestra tareas programadas, en borrador, de cursos no publicados, de cursos dados de baja y de otros grupos | `Estudiante/PanelInicio.php:47-51`; confirmado (2 pruebas) | Viola RF-38, regla 9 y la baja (RF-30): revela títulos antes de tiempo | Usar `Curso::whereHas('inscripcionesActivas')->visiblesParaEstudiantes()` y `Tarea::visiblesParaEstudiante()` + `TareaPolicy::ver` | S |
| H-02 | El avance (RF-66) cuenta tareas grupales de otros grupos y no llega a 100 % | `ProgresoAcademicoService.php:90,100`; confirmado | Avance, panel, reportes y "bajo rendimiento" incorrectos en cursos con tareas grupales asignadas | Filtrar con `TareaPolicy::ver` como en `estadoModulos` (línea 130); aplicar igual en `resumenDashboardEstudiante`, `resumenCurso` y `PropuestaBotService` | S |
| H-03 | El Supervisor reabre cursos archivados y con ello se pueden cambiar notas y esquema del periodo cerrado | `Cursos/Formulario.php:190-213`; confirmado | Rompe la regla 8, RF-62, RF-65 y RNF-14 | Prohibir salir de `archivado` (o exigir motivo, confirmación y una nueva bitácora específica, si el negocio lo quiere) | S |
| H-04 | En tareas grupales, la entrega de un integrante (nuevo o con intentos) sobrescribe estado, nota e intentos de todo el grupo | `Tareas/Detalle.php:114-170`; confirmado | Pérdida de notas sin registro en bitácora (RF-95, regla 11) | Validar intentos y "calificada" del grupo entero; no sobrescribir entregas calificadas; registrar en bitácora; prueba de regresión | M |
| H-07 | El trabajo de la auditoría final (archivos privados, HTTPS, recursos, 2 migraciones, 7 pruebas, despliegue) está sin commitear | `git status` (02 § 4; 16 § 5) | Riesgo de pérdida; lo desplegado no coincide con lo auditado | Commit por partes, PR y fusión antes de desplegar | S |
| H-12 | El Supervisor puede cambiar el rol de cualquier usuario a `supervisor`, sin confirmación y sin revisar cursos o matrículas | `Usuarios/Index.php:122-146`, `usuarios/index.blade.php:106-107` | Escalada de privilegios por error o abuso; cursos sin gestor | Limitar a `ROLES_CREABLES`, pedir confirmación, impedir cambios con cursos o matrículas activas | S |
| H-22 | Se confía en `X-Forwarded-Host` de cualquier origen; un cliente cambia el dominio de las URL generadas | `bootstrap/app.php:24`; confirmado en local | Si el proxy reenvía el encabezado del cliente: envenenamiento del enlace de restablecimiento de contraseña (toma de cuentas) | Confiar solo en `X-Forwarded-Proto`, o `URL::forceRootUrl(config('app.url'))` en producción, o `TrustHosts` | S |
| H-24 | El panel del estudiante muestra anuncios de cursos en los que no está matriculado | `dashboard.blade.php:16-20`; confirmado | Fuga de información entre cursos (RF-81) | Agregar `whereNull('curso_id')` (institucionales) o filtrar por cursos con matrícula activa | S |
| H-32 | En producción, `/up` por HTTP responde 301 por `ForzarHttps` | `ForzarHttps.php`; confirmado en local | Si el healthcheck de Railway no envía `X-Forwarded-Proto: https`, el despliegue no se marca sano y se revierte | Excluir `/up` de la redirección o probar el healthcheck en Railway antes de fusionar | S |
| H-33 | Los seeders no comprueban el entorno y crean cuentas (incluido un Supervisor) con `password123` y sin cambio obligatorio | `RoleAndUserSeeder.php:28,39,50`; `DemoAcademicoSeeder.php:46,111` | Si se ejecuta `db:seed` en producción, acceso trivial con rol Supervisor | `if (app()->isProduction()) return;` en los seeders de demostración; verificar si esas cuentas existen en Supabase | S |

## 3. Hallazgos de severidad Media

| ID | Hallazgo | Evidencia | Impacto | Arreglo | Esf. |
|---|---|---|---|---|---|
| H-05 | Trigger de inmutabilidad de la bitácora sin pruebas; no cubre `TRUNCATE`; permite anonimizar FK | `2026_09_26_202325_...php:21-53` (04b § 2) | RF-99 y regla 11 sin verificación real | Probar en PostgreSQL (staging o CI); trigger de `TRUNCATE`; separar privilegios | M |
| H-08 | `X-Forwarded-For` no es de confianza: rate limit de la API e IP de bitácora y logs son las del proxy | `bootstrap/app.php:24`; `S/HttpsTest::test_la_ip_reenviada_por_el_cliente_no_reemplaza_la_ip_real` | Un solo cupo de 60/min para todas las instituciones; IP inútil en auditoría | Confiar en `X-Forwarded-For` solo desde los rangos del proxy de Railway (confirmar con Railway) | S |
| H-10 | `Tarea::estadoPara` usa `updated_at` como momento de entrega | `Tarea.php:149-168` | "Fuera de plazo" falso tras cambios de la fila (por ejemplo, H-04) | Columna `entregado_en` (migración aditiva) y usarla | M |
| H-11 | `php artisan serve` con 4 workers como servidor de producción | `railway.json:8` | 4 peticiones lentas bloquean a todos; RNF-11 y RNF-13 | FrankenPHP, Octane o Nginx + PHP-FPM | M |
| H-13 | El tiempo límite del quiz se reinicia al recargar la página | `Quizzes/Resolver.php:36,49,92` | RF-45 evadible | Persistir el inicio del intento (tabla o columna, migración aditiva) | M |
| H-14 | Clonar acepta cualquier usuario como titular del curso nuevo | `Cursos/Show.php:446`; `Curso.php:250-256` | Curso con titular sin rol profesor | Validar rol profesor como en `Formulario` | S |
| H-15 | Asistente de IA sin límite de solicitudes por usuario | `BotSugerencias/Widget::enviar` | Consumo de cuota y bloqueo de workers (con H-11) | `RateLimiter` por usuario | S |
| H-20 | Cambiar la nota mínima cambia la condición de cursos archivados | `CalculadoraNotas::notaMinima`, `PanelAdmin/Configuracion` | Altera históricos (RNF-14) | Guardar la nota mínima vigente en el curso al archivarlo | M |
| H-21 | El worker de colas no aplica la zona horaria de la institución | `AppServiceProvider.php:89`; `railway.json:8` | Fechas en UTC en lo encolado (notificación de matrícula) | Aplicar la zona también en `queue:work` (por ejemplo, `Queue::before`) | S |
| H-23 | La boleta PDF no muestra rubros ni nota final | `reportes/boleta-pdf.blade.php`; `BoletaPdfController` | Documento oficial incompleto y engañoso (RF-59) | Generar el PDF con `resumenRubros`; limitar a estudiantes | S |
| H-26 | Login sin límite por IP | `Forms/LoginForm.php` | Bloqueo dirigido de cuentas y *password spraying* | `RateLimiter` por IP y por correo | S |

## 4. Hallazgos de severidad Baja

| ID | Hallazgo | Evidencia | Impacto | Arreglo | Esf. |
|---|---|---|---|---|---|
| H-06 | El commit `3f413c0` modificó 11 migraciones ya existentes (viola "solo aditivas"; solo sangría) | 02 § 2; 04b § 1 | Trazabilidad del esquema | Regla de revisión en PR | S |
| H-09 | Preguntas de quiz sin ID estable (se ligan por posición) | 04b § 3 | Recálculos frágiles; base de H-19 | IDs por pregunta en el JSON | L |
| H-16 | El libro de calificaciones califica tareas sin notificar y con motivo fijo | `LibroCalificaciones.php:170-213` | RF-84 y RF-95 a medias | Servicio único de calificación | M |
| H-17 | Mensajes del framework en inglés; la recuperación revela si el correo existe | `lang/` solo `es.json`; verificado con `tinker` | RNF-07; enumeración de cuentas | `lang/es/{auth,passwords,validation}.php`; mensaje neutro en recuperación | S |
| H-18 | Dos políticas de contraseña | `CambiarPasswordPrimerLogin.php:36`; Volt de perfil y restablecimiento | Contraseñas débiles después del primer ingreso | `Password::defaults()` en `AppServiceProvider` | S |
| H-19 | El bloqueo de estructura de un quiz con intentos se evita reemplazando `preguntas` | `Quizzes/Formulario.php:151,184-206` | Recálculo erróneo | Comparar cantidad de preguntas y opciones en el servidor | S |
| H-25 | El `<title>` es fijo; los `->title()` no se usan | `layouts/app.blade.php:64` | Usabilidad y accesibilidad | `<title>{{ $title ?? config('app.name') }}</title>` | S |
| H-27 | Sin cabeceras `X-Frame-Options`/CSP, `X-Content-Type-Options`, `Referrer-Policy` | 13 § 14 | *Clickjacking* | Middleware de cabeceras | S |
| H-28 | Flatpickr desde CDN sin SRI | `resources/js/app.js:24-38` | Integridad de código de terceros | `integrity` o empaquetarlo con Vite (requiere aprobar la dependencia) | S |
| H-29 | `league/commonmark` con 2 avisos (producción); 10 avisos npm (desarrollo) | 13 § 12.2 | Exposición baja | `composer update league/commonmark`; actualizar herramientas de desarrollo | S |
| H-30 | Contraseña temporal en sesión de base de datos sin cifrar | `config/session.php:50` | Lectura desde la base o respaldos | `SESSION_ENCRYPT=true` | S |
| H-31 | N+1 fuera de `RendimientoTest`; 3 copias de la lógica de calificación | 14 § 1 y § 4 | Rendimiento con volumen; defectos repetidos | Consultas por lote; servicio de calificación; ampliar `RendimientoTest` | M |
| H-34 | Documentación con cifras desactualizadas; `README.md` por defecto; `INFORME_FINAL.md` obsoleto; 6 comentarios desactualizados | 16 § 2-3 | Decisiones sobre datos falsos | Actualizar documentos y comentarios | S |

## 5. Plan de corrección en tandas

| Tanda | Objetivo | Hallazgos | Depende de | Esfuerzo total |
|---|---|---|---|---|
| 0. Sin código (ya) | Asegurar el trabajo y aclarar lo externo | H-07 (commit y PR); respuestas a § 6 preguntas 1, 2 y 7; verificar `APP_ENV`, `APP_DEBUG`, `APP_URL` en Railway; respaldo de Supabase | — | S |
| 1. Bloqueantes de producción | Que el despliegue arranque y no exponga datos ni cuentas | H-32, H-22, H-33, H-24, H-01, H-12, H-03 | Tanda 0 (respuesta de Railway para H-22/H-32; decisión de negocio para H-03) | ~2 días |
| 2. Integridad académica | Notas y avance correctos | H-04, H-10, H-02, H-16, H-20, H-23 | H-10 antes que H-04 (columna `entregado_en`); H-16 (servicio único) antes de corregir H-04 en las 3 vías | ~4 días |
| 3. Seguridad complementaria | Endurecer autenticación y navegador | H-26, H-15, H-17, H-18, H-27, H-28, H-30, H-29, H-08 | H-08 depende de los rangos de IP del proxy; H-28 puede requerir aprobar una dependencia | ~2 días |
| 4. Funcionalidad y reglas | Cerrar parciales del ERS | H-13, H-14, H-19, H-21, RF-55 (semana obligatoria), RF-82 (hilos), RF-108 (nombre de la institución) | H-13 requiere migración aditiva | ~3 días |
| 5. Operación y calidad | Producción sostenible | H-11, H-05, H-31, H-09, H-25, H-34, H-06 | H-05 requiere un PostgreSQL de pruebas; H-11 cambia `railway.json` | 1-2 semanas |

Cada corrección de las tandas 1 y 2 debe convertir la prueba temporal correspondiente (11 § 1.1) en una prueba de regresión del repositorio, que hoy **pasa** porque el defecto existe y debe pasar a comprobar el comportamiento correcto.

## 6. Preguntas abiertas

1. ¿El proxy de Railway reemplaza `X-Forwarded-Host` y envía `X-Forwarded-Proto: https` en el healthcheck? (H-22, H-32)
2. ¿Qué migraciones están aplicadas en Supabase? ¿Existen en producción las cuentas de demostración con `password123`? (H-33, H-06)
3. ¿El Supervisor debe poder reabrir un curso archivado? Si sí, ¿con qué control? El ERS no lo define (12b, E-5). (H-03)
4. ¿Se acepta Cloudinary en lugar de Supabase Storage? Si sí, hay que actualizar RNF-06 del ERS (E-1).
5. ¿Debe poder asignarse el rol Supervisor desde la interfaz? (H-12)
6. ¿La nota mínima aprobatoria debe congelarse por ciclo? (H-20)
7. ¿Railway tiene un volumen persistente? Si no, ¿`CLOUDINARY_URL` está configurada en producción? (15 § 6)
8. ¿Cuál es el alcance esperado de "Clases en vivo" (Fase 2) y de "conversaciones" en RF-82? (12b § 7, RF-82)
9. ¿Qué retención se exige para los logs de la API y del login, y se necesita un aviso de privacidad para el asistente de IA? (13 § 12.1, § 16)
10. ¿El rubro debe tener semana obligatoria (RF-55)?

## 7. Archivos cubiertos solo por grupo (verificación final de cobertura)

Se comparó la lista de los 352 archivos de `app/`, `routes/`, `database/`, `resources/`, `tests/`, `config/` y `docs/` (versionados más los 15 sin seguimiento) con el texto de todos los archivos de esta auditoría (búsqueda del nombre base). **294** aparecen por nombre. Los **58** restantes están cubiertos por grupo, no por nombre:

| Grupo | Archivos | Dónde se cubren |
|---|---|---|
| Migraciones (32 de 46) | `0001_01_01_000001_create_cache_table`, `0001_01_01_000002_create_jobs_table` y 30 migraciones del dominio de `2026_09_06` a `2026_09_26` (inscripciones, tareas, entregas, quizzes, resultados, eventos, conversaciones y propuestas del bot, materiales, notificaciones, grupos, comunicados, visible, campos ERS, bitácora, esquema, integraciones, lecciones, sesiones y asistencias, mensajes, banco de preguntas, configuración, lecciones completadas, `curso_profesor`) | 04a (por tabla) y 04b § 1 (revisión de las 46) |
| Factories (8) | `CursoFactory`, `EntregaFactory`, `InscripcionFactory`, `ModuloFactory`, `QuizFactory`, `ResultadoQuizFactory`, `TareaFactory`, `UserFactory` | 05 § 4 (por modelo) y 11 § 7 |
| Componentes Blade de Breeze (9) | `action-message`, `application-logo`, `auth-session-status`, `danger-button`, `dropdown-link`, `input-error`, `input-label`, `responsive-nav-link`, `secondary-button` | 10 § 1 (grupo "Breeze"); no se leyeron línea por línea |
| Vistas de componentes Livewire (8) | `asistencia/mi-historial`, `auth/cambiar-password-primer-login`, `bot-sugerencias/resumen-semanal`, `cursos/boleta-estudiante`, `cursos/esquema-evaluacion`, `reportes/panel-gestion`, `usuarios/importar-csv`, `tareas/partials/formulario-entrega` | 07a § 1 (convención de vista por componente) y el componente de cada una; no se leyeron completas |
| Marcador | `resources/views/livewire/.gitkeep` | Sin contenido |

Archivos no revisados línea por línea (además de los anteriores): la mayoría de las 83 vistas (se revisaron por patrones, archivo 01 § 7), `config/permission.php`, `config/cache.php`, `config/logging.php` (solo claves relevantes), `docker-compose.yml` y los archivos generados de `bootstrap/cache/`.
