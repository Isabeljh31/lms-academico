# Registro de cambios por módulo

Lista de archivos creados (C), modificados (M) y eliminados (E), para revisar y subir a mano.
No se ejecutó ningún comando de git que modifique el repositorio.

Respaldo inicial (antes de tocar código): `C:\xampp\htdocs\LearnUp_respaldo_20260926_0035`

## Fase 1 — Análisis

- C `docs/TRAZABILIDAD.md`
- C `docs/PANTALLAS.md`
- C `docs/AUDITORIA.md`
- C `docs/PROGRESO.md`
- C `docs/CAMBIOS.md`

> `package-lock.json` ya figuraba como modificado antes de empezar esta tarea (no lo tocó este trabajo).

## Base transversal (usada por varios módulos)

- C `config/archivos.php` — límite único de 10 MB y lista blanca de extensiones.
- M `app/Services/Multimedia/SubidorArchivos.php` — lista blanca, nombre generado por el servidor, errores de subida como validación.
- M `app/Models/BitacoraAuditoria.php` — inmutable (updating/deleting lanzan excepción), tipos de evento, ejecutor opcional.
- M `app/Services/ConfiguracionPlataforma.php` — persistencia en BD (tabla `configuraciones_plataforma`), nota mínima y parámetros generales.
- C `app/Services/Evaluacion/CalculadoraNotas.php` — única fórmula de nota final y validación de pesos.
- C migraciones aditivas:
  - `2026_09_26_054524_create_lecciones_table.php`
  - `2026_09_26_054525_create_sesiones_clase_y_asistencias_tables.php`
  - `2026_09_26_054526_create_mensajes_table.php`
  - `2026_09_26_054527_create_banco_preguntas_table.php`
  - `2026_09_26_054528_create_configuraciones_plataforma_table.php`
  - `2026_09_26_054529_add_campos_contenido_y_evaluacion_ers.php`
  - `2026_09_26_054530_create_lecciones_completadas_table.php`
- C `resources/views/components/gestion/` — `encabezado`, `alertas`, `indicador`, `vacio`, `boton`, `tarjeta`, `modal`, `campo`, `control` (solo Profesor/Supervisor).
- M `resources/views/components/icono.blade.php` — iconos Heroicons nuevos (los existentes no cambian).
- M `resources/views/layouts/app.blade.php` — solo el arreglo `$menu` (entradas de Supervisor; Mensajería oculta al Supervisor). Las entradas del Estudiante no cambian.
- M `routes/web.php`, `routes/admin.php` — middleware de rol por ruta; se elimina la ruta `admin/metricas` duplicada sin `auth`.
- M `database/factories/UserFactory.php`, `CursoFactory.php`, `InscripcionFactory.php` — valores por defecto explícitos y estados (`conPrimerLogin`, `inactivo`, `borrador`, `archivado`, `deApi`, `deBaja`).

## Módulo 01 — Autenticación y cuentas

- M `app/Livewire/Forms/LoginForm.php` — bloqueo configurable, log de intentos fallidos, sin revelar cuentas.
- M `config/auth.php` — `bloqueo_login` (`LOGIN_MAX_INTENTOS`, `LOGIN_MINUTOS_BLOQUEO`).
- M `app/Models/User.php` — atributos por defecto, campos de bloqueo fuera de `$fillable`, `estaMatriculadoEn()`.
- M `app/Livewire/Auth/CambiarPasswordPrimerLogin.php` — sin log de contraseña; política de contraseña.
- C `app/Http/Middleware/CuentaActiva.php`; M `bootstrap/app.php`.
- M `routes/auth.php` — sin ruta de registro.
- E `resources/views/livewire/pages/auth/register.blade.php`
- E `resources/views/livewire/profile/delete-user-form.blade.php`; M `resources/views/profile.blade.php`
- E `routes/test_primer_login.php`
- M `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/ProfileTest.php`

## Módulo 02 — Gestión de usuarios

- C `app/Services/Usuarios/GestorCuentas.php`
- M `app/Livewire/Usuarios/Index.php` + `resources/views/livewire/usuarios/index.blade.php`
- M `app/Livewire/Usuarios/ImportarCSV.php` + `resources/views/livewire/usuarios/importar-csv.blade.php`
- M `app/Livewire/Admin/Profesores/Index.php` + `resources/views/livewire/admin/profesores/index.blade.php`
- M `resources/views/emails/bienvenida.blade.php` — sin emojis ni degradado.
- M tests: `UsuariosTest`, `AdminProfesoresTest`; `Rol::Administrador` → `Rol::Supervisor` en `BotFaqTest`, `CursoShowEstudianteTest`, `CursosTest`, `PanelAdminTest`, `PanelInicioEstudianteTest`.

Respaldo tras los módulos 01–02: `C:\xampp\htdocs\LearnUp_respaldo_modulo_02`

## Módulo 03 — Cursos

- M `app/Policies/CursoPolicy.php` — ver / crear / administrar / gestionar / calificar; sin autoinscripción.
- M `app/Models/Curso.php` — estados visibles para estudiantes, curso finalizado = archivado, clonación completa en transacción.
- C `app/Models/Concerns/TieneEstadoPublicacion.php` — Borrador / Publicado / Programado (módulos, tareas, quizzes).
- M `app/Models/Modulo.php`, `app/Models/Leccion.php`; C `app/Models/LeccionCompletada.php`.
- M `app/Livewire/Cursos/Index.php` + vista (rama Estudiante: solo emoji → icono y subtítulo).
- M `app/Livewire/Cursos/Formulario.php` + vista (solo Supervisor, RF-25, curso archivado bloqueado, auditoría).
- M `app/Livewire/Cursos/Show.php` + vista (módulos editables/ordenables, clonación con modal, lección completada; rama Estudiante: botón "Marcar como completada" y "Mi Asistencia" con el mismo estilo; corregido `</span</p>`).
- M `resources/views/components/badge-estado.blade.php` — estados nuevos (no_entregado, fuera_de_plazo, devuelto); los existentes no cambian.
- M `routes/cursos.php` — rol por ruta; rutas `banco-preguntas` y `mi-asistencia`.
- M tests: `CursosTest`, `CursoShowEstudianteTest`, `RolesYFuncionalidadTest`.

## Módulo 04 — Matrícula

- C `app/Services/Matricula/GestorMatriculas.php`
- M `app/Livewire/Cursos/GestionMatricula.php` + vista (solo Supervisor matricula/da de baja; Profesor consulta).
- M `app/Notifications/MatriculadoEnCurso.php` — canal database + mail, sin emoji.

## Módulo 05 — Contenido

- M `app/Livewire/Lecciones/Index.php` + vista (subida de archivo, estado, orden, IDOR corregido, aviso de publicación).
- C `app/Notifications/NuevoContenidoPublicado.php`

## Módulo 06 — Tareas

- M `app/Models/Tarea.php` (RF-40, RF-41, RF-43 estado calculado), `app/Models/Entrega.php`.
- M `app/Policies/TareaPolicy.php`, `app/Policies/EntregaPolicy.php`.
- M `app/Livewire/Tareas/Index.php`, `Formulario.php`, `Detalle.php` + vistas; C `resources/views/livewire/tareas/partials/formulario-entrega.blade.php`.
- M `app/Livewire/Docente/PorCalificar/Index.php` + vista (IDOR corregido, auditoría).
- C `app/Notifications/EntregaDevuelta.php`, `CambioFechaTarea.php`.
- M tests: `BlackboardDocenteAlumnoTest`, `ListadosEstudianteTest`.

## Módulo 07 — Quizzes y banco de preguntas

- M `app/Models/Quiz.php` (tipos, configuración, intentos, recálculo), `app/Models/ResultadoQuiz.php`, `app/Models/BancoPregunta.php`.
- M `app/Policies/QuizPolicy.php`.
- M `app/Livewire/Quizzes/Index.php`, `Formulario.php`, `Resolver.php`, `Estadisticas.php` + vistas.
- C `app/Livewire/Quizzes/BancoPreguntas.php` + `resources/views/livewire/quizzes/banco-preguntas.blade.php`.
- C `app/Notifications/NuevoQuizPublicado.php`, `NotaQuizModificada.php`.

## Módulo 08 — Evaluación y calificaciones

- M `app/Livewire/Cursos/EsquemaEvaluacion.php` + vista (edición atómica, 100 % en backend, auditoría antes/después).
- M `app/Livewire/Cursos/LibroCalificaciones.php` + vista (solo titular, IDOR corregido, exportación CSV, práctica separada).
- M `app/Livewire/Cursos/BoletaEstudiante.php`; M vista (solo emojis → iconos).

## Módulos 09–10 — Progreso y asistencia

- C `app/Services/Asistencia/CalculadoraAsistencia.php`
- M `app/Livewire/Asistencia/Index.php` + vista; C `app/Livewire/Asistencia/MiHistorial.php` + vista (estilo del Estudiante).

## Módulo 11 — Grupos

- M `app/Livewire/Grupos/Index.php` + vista (renombrar, mover, solo matriculados).

## Módulo 12 — Calendario

- M `app/Livewire/EventoCalendario/Index.php` (tareas, quizzes, sesiones y evaluaciones; vista sin cambios).

## Módulo 13 — Mensajería

- M `app/Livewire/Mensajeria/Bandeja.php`; M vista (solo: casilla de aviso para Profesor, texto 10 MB, `rel="noopener"`).
- M `app/Notifications/NuevoMensaje.php` (sin emoji).

## Módulo 14 — Notificaciones y comunicados

- M `app/Models/Comunicado.php`; M `app/Livewire/Admin/Comunicados/Index.php` + vista.
- C `app/Notifications/NuevoComunicado.php`; C `app/Console/Commands/PublicarComunicadosProgramados.php`; M `routes/console.php`.

## Módulo 18 — Integración API (adelantado por depender de Matrícula)

- M `app/Http/Controllers/Api/MatriculaController.php`, `app/Http/Middleware/ValidarApiKey.php`, `routes/api.php`.
- M `app/Providers/AppServiceProvider.php` (rate limiting, HTTPS en producción), `config/services.php`.
- C `app/Livewire/PanelAdmin/Integraciones.php` + vista; M `app/Models/IntegracionExterna.php`.

Respaldo tras el módulo 14: `C:\xampp\htdocs\LearnUp_respaldo_modulo_14`

## Módulo 15 — Reportes y dashboards (en curso)

- M `app/Services/Reportes/ProgresoAcademicoService.php` — avance con lecciones (RF-66), visibilidad de contenido, reporte por curso (RF-69, RF-89), nota final vía `CalculadoraNotas`.
- M `app/Livewire/Reportes/Dashboard.php`; M `resources/views/livewire/reportes/dashboard-profesor.blade.php` (reporte RF-89 completo).
- M `resources/views/livewire/reportes/dashboard-estudiante.blade.php` — solo textos y datos: nota final por rubros (roja bajo la mínima) en lugar del promedio de práctica; mismas clases.
- C `app/Livewire/Reportes/PanelGestion.php` + `resources/views/livewire/reportes/panel-gestion.blade.php` (RF-88 Profesor, RF-90 Supervisor).
- M `resources/views/dashboard.blade.php` — rama Estudiante: emoji → icono; rama de gestión: usa `reportes.panel-gestion`.
- M `app/Livewire/PanelAdmin/Metricas.php` + `resources/views/livewire/panel-admin/metricas.blade.php` — RF-91 (nuevos estudiantes por período, activos/inactivos, cursos completados, tasa de abandono, promedio/aprobación/abandono por curso), sin emojis.
- M `app/Livewire/Admin/Metricas/Index.php` + `resources/views/livewire/admin/metricas/index.blade.php` — riesgo de abandono con asistencia real y nota mínima configurable, sin emojis.

## Módulo 16 — Asistente de IA

- M `app/Services/Bot/Concerns/ConstruyeContextoIA.php` — reglas contra inyección de instrucciones; contexto solo del curso permitido y de las actividades/nota del propio estudiante; historial recortado.
- M `app/Services/Bot/Contracts/ProveedorIA.php`, `GroqProveedor.php`, `ClaudeBotService.php` — reciben el usuario para armar el contexto.
- M `app/Livewire/BotSugerencias/Widget.php` — `cursoId` bloqueado (#[Locked]) y validado con la policy.
- M `app/Services/Bot/PropuestaBotService.php`, `app/Livewire/BotSugerencias/ResumenSemanal.php` — solo matrículas activas y tareas publicadas.
- M `tests/Feature/BotFaqTest.php` — el test asignaba `cursoId` desde el cliente (la vulnerabilidad corregida).

## Módulo 17 — Auditoría

- M `app/Livewire/PanelAdmin/Auditoria.php` + vista — todos los tipos de evento, filtros tolerantes a valores inválidos, "Sistema externo" para la API, valores antes/después y motivo, exportación CSV, solo lectura.
- C `database/migrations/2026_09_26_202325_proteger_bitacora_auditoria_inmutable.php` — en PostgreSQL, trigger que rechaza UPDATE/DELETE (solo permite que las referencias pasen a NULL por ON DELETE SET NULL). En SQLite no hace nada.

## Módulo 19 — Configuración general

- M `app/Livewire/PanelAdmin/Configuracion.php` + vista — nota mínima aprobatoria (RF-107), institución, zona horaria y formato de fecha (RF-108), auditoría de cada cambio; confirmación al activar/desactivar el asistente.

## Limpieza de Tailwind 4

- M `package.json`, `package-lock.json` — se quitó `@tailwindcss/vite` (^4), que no se importaba en ningún archivo (`vite.config.js` usa PostCSS con Tailwind 3). `npm run build` produce el mismo CSS (hash `app-DjRxxsg4.css`, 89,06 kB) antes y después. `npm audit`: 0 vulnerabilidades.

## Fase 3 — Pruebas

- C `tests/Concerns/PreparaEscenarioAcademico.php` — roles y escenario académico reutilizable.
- C `tests/Unit/Services/Evaluacion/CalculadoraNotasTest.php`, `tests/Unit/Services/Asistencia/CalculadoraAsistenciaTest.php`, `tests/Unit/Models/TareaTest.php`, `tests/Unit/Models/QuizTest.php`.
- C `tests/Feature/Seguridad/` — `AccesoPorRolTest`, `AutenticacionTest`, `IdorTest`, `SubidaArchivosTest`, `ApiIntegracionTest`, `AuditoriaTest`, `AsistenteIaTest`, `ValidacionEntradasTest`.
- C `tests/Feature/Flujos/` — `FlujosPrincipalesTest` (flujos 9.1, 9.3, 9.4 y reglas de negocio), `CasosDeUsoTest` (CU-03 a CU-17, CU-22).
- Defectos encontrados por las pruebas y corregidos:
  - M `app/Services/Matricula/GestorMatriculas.php` — el motivo de la baja no llegaba a la bitácora.
  - M `app/Models/BitacoraAuditoria.php` — notas guardadas como "12.0"; ahora "12".
- `composer audit`: sin vulnerabilidades. `npm audit`: 0 vulnerabilidades.
- Resultado: 332 pruebas en verde (128 al inicio, 97 en verde).

## Fase 4 — Revisión de pantallas

- C `tests/Feature/RevisionPantallasTest.php` — 51 pantallas: 200, contenido clave, sin emojis; con `CAPTURAR_PANTALLAS` guarda el HTML para capturas.
- M `resources/views/livewire/cursos/show.blade.php` — pestaña Tareas del Estudiante: la fecha usaba `fecha_limite` (inexistente) y mostraba "Sin fecha límite"; ahora `fecha_entrega` (mismas clases). Prueba de regresión en `CasosDeUsoTest`.
- M `resources/views/livewire/cursos/libro-calificaciones.blade.php`, `app/Livewire/Cursos/LibroCalificaciones.php`, `app/Livewire/EventoCalendario/Index.php` — peso del rubro sin ceros de relleno.
- M `docs/PANTALLAS.md` — revisión de cada pantalla.
- Resultado: 384 pruebas en verde.

## Fase 5 — Auditoría final

- C `app/Notifications/NotaRubroRegistrada.php` — RF-84: aviso al estudiante cuando se registra o ajusta su nota de un rubro.
- M `app/Livewire/Cursos/LibroCalificaciones.php` — `guardarNotaRubro` notifica solo si la nota es nueva o cambió. Prueba en `FlujosPrincipalesTest`.
- M `docs/TRAZABILIDAD.md` — columna de estado final con implementación y pruebas por requisito.
- M `docs/AUDITORIA.md` — informe final (11 secciones).
- Resultado: 385 pruebas en verde.

## Auditoría de procesos y flujos (segundo pedido)

### Módulo 3.1 — Autenticación
- M `resources/views/livewire/pages/auth/reset-password.blade.php` — recuperar la contraseña limpia el bloqueo por
  intentos fallidos y marca el primer login como cumplido (el usuario ya eligió una contraseña propia).
  Prueba: `AutenticacionTest::test_recuperar_la_contrasena_desbloquea_la_cuenta`.
  - **Decisión:** "exigir recuperación" (RF-06 del pedido) = tras 3 intentos la cuenta se bloquea 15 minutos y el
    mensaje remite a "¿Olvidaste tu contraseña?"; completar la recuperación desbloquea de inmediato.

### Módulo 3.2 — Usuarios
- C `app/Services/HojasCalculo/LectorXlsx.php` — lee la primera hoja de un `.xlsx` con `zip` + `SimpleXML` (sin
  dependencias). Rechaza DOCTYPE (XXE) y XML internos mayores a 20 MB; copia el archivo a una ruta corta porque
  libzip no abre las rutas temporales largas de Livewire en Windows.
- C `app/Services/HojasCalculo/LibroXlsx.php`, `HojaXlsx.php` — escritor `.xlsx` con formato (ver "Exportaciones").
- M `app/Livewire/Usuarios/ImportarCSV.php` + vista — acepta `.xlsx` además de `.csv`. Pruebas en `CasosDeUsoTest`.
  - **Decisión:** no se agregó `phpoffice/phpspreadsheet` (dependencia nueva sin aprobación). Si se aprueba, puede
    reemplazar a `App\Services\HojasCalculo` sin cambiar las pantallas.

### Matriz de permisos (sección 5 del ERS)
- M `app/Policies/CursoPolicy.php` — `gestionar` y `calificar` son solo del Profesor del curso (titular o adicional).
  Nuevas: `supervisar` (consulta: Supervisor + Profesor), `matricular` (Supervisor) y `darDeBaja` (Supervisor + Profesor).
- M `TareaPolicy`, `QuizPolicy`, `GrupoPolicy` (`supervisar`), `EntregaPolicy` (ver = supervisar).
- M `Cursos\Show`, `Tareas\Index`, `Tareas\Detalle`, `Quizzes\Index`, `Quizzes\Estadisticas`, `Cursos\LibroCalificaciones`,
  `Asistencia\Index`, `Grupos\Index` + vistas — el Supervisor consulta en solo lectura; los botones de acción solo
  aparecen para el Profesor del curso.
- M `routes/web.php`, `routes/cursos.php` — lecciones, banco de preguntas y "Por calificar" solo para `profesor`.
  - **Decisión:** la matriz marca "—" al Supervisor en contenido, tareas/quizzes y notas; antes podía hacerlo. Se
    conserva su acceso de consulta porque "Ver reportes: Todos".
- Pruebas actualizadas: `AccesoPorRolTest` (6 rutas pasan a 403 para el Supervisor), `RevisionPantallasTest` (texto).

### Módulo 3.3 — Cursos (RF-24 del pedido = RF-23 del ERS v2)
- C `database/migrations/2026_09_26_234343_create_curso_profesor_table.php` — profesores adicionales (aditiva).
- M `app/Models/Curso.php` — `profesoresAdicionales()`, `esDocente()`, `docentes()`, scope `dictadosPor()`.
- M `app/Models/User.php` — `cursosComoProfesorAdicional()`, `cursosComoDocente()`.
- M `Cursos\Formulario` + vista — selección de profesores adicionales (solo rol profesor; se audita).
- M `Cursos\Index`, `Docente\PorCalificar`, `EventoCalendario\Index`, `Mensajeria\Bandeja`, `Reportes\PanelGestion`,
  `Reportes\Dashboard`, `BotSugerencias\ResumenSemanal`, `Usuarios\Index` — consideran titular y adicionales.
- M `Admin\Profesores\Index` — al retirar al titular, el primer profesor adicional pasa a titular.
  - **Decisión:** "Curso" (plantilla) y "Oferta de Curso" (RF-19/RF-20 del pedido) son la misma entidad `Curso`, como
    en el ERS v2. La clonación cumple el papel de "plantilla" para cada periodo. No se crea una tabla de ofertas.
  - **Decisión:** la clonación no copia los profesores adicionales; el flujo 9.4 indica que el Supervisor asigna el
    profesor del nuevo periodo.

### Módulo 3.4 — Matrícula
- M `app/Livewire/Cursos/GestionMatricula.php` + vista — el Profesor da de baja estudiantes de sus cursos (matriz § 5).
  - **Decisión:** una matrícula que llegó por API solo la desactiva el Supervisor (RF-33 del pedido nombra al
    Supervisor y el sistema externo es la fuente de verdad).
- Pruebas: `FlujosPrincipalesTest` (baja por el Profesor, curso ajeno, API, co-docente, Supervisor en solo lectura),
  `AdminProfesoresTest` (sucesión del titular).

### Módulo 3.5 — Contenido
- C `database/migrations/2026_09_26_235911_add_aviso_programado_a_contenido.php` — columna `aviso_programado_en` en
  módulos, lecciones, tareas y quizzes (aditiva). Lo ya publicado antes de la migración se marca como avisado para no
  notificar en forma retroactiva.
- C `app/Console/Commands/NotificarContenidoProgramado.php` — `contenido:notificar-programado` (cada 5 minutos en
  `routes/console.php`): avisa una sola vez a los matriculados cuando el contenido Programado llega a su fecha
  (RF-84: nuevo contenido, nueva tarea, nuevo quiz). Respeta la visibilidad (curso publicado, módulo visible, grupo).
- M `app/Notifications/NuevoContenidoPublicado.php` — acepta lección o módulo.
  - **Decisión:** "Examen" (RF-38 del pedido) no es una entidad aparte: el ERS v2 (RF-37) enumera Módulo, Lección,
    Tarea y Quiz, y las evaluaciones calificadas son los Rubros del esquema (RF-57). Un examen en línea se crea como Quiz.

### Módulo 3.6 — Tareas y quizzes
- C `app/Notifications/CambioFechaQuiz.php`; M `app/Livewire/Quizzes/Formulario.php` — aviso cuando cambia la fecha
  límite de un quiz ya publicado (RF-84 "cambio de fecha"; antes solo existía para tareas).
- M `app/Models/Curso.php` — `clonar()` copia también el banco de preguntas (RF-47: reutilizable entre periodos).

### Módulo 3.7 — Evaluación y calificaciones
- C `app/Services/Evaluacion/ExportadorLibroCalificaciones.php` — libro de Excel de tres hojas: Resumen (datos del
  curso, indicadores, esquema con total de pesos), Notas por rubro (encabezado fijo con filtro, nota final en verde o
  rojo según la nota mínima, condición, fila de promedios) y Práctica (tareas y quizzes aparte, RF-60).
- M `app/Livewire/Cursos/LibroCalificaciones.php` + vista — "Exportar a Excel" reemplaza a "Exportar CSV".
  Pruebas: `tests/Feature/HojasCalculo/ExportacionesTest.php`.

### Módulo 3.8 — Progreso (RF-62 del pedido = RF-67 del ERS v2)
- C `database/migrations/2026_09_27_000557_add_condicion_completitud_a_modulos_y_lecciones.php` (aditiva).
- M `app/Models/Leccion.php` — `CONDICIONES`: "marcar como vista" o "al abrir su video o material".
- M `app/Models/Modulo.php` — `CONDICIONES`: actividades (lecciones + tareas entregadas + quizzes rendidos, por
  defecto), solo lecciones, o aprobación (nota ≥ mínima).
- M `app/Services/Reportes/ProgresoAcademicoService.php` — `estadoModulos()`.
- C `app/Http/Controllers/RecursoLeccionController.php` + ruta `cursos.lecciones.recurso` — abre el recurso y registra
  la lección como completada cuando corresponde (verifica curso, matrícula y visibilidad).
- M `Lecciones\Index`, `Cursos\Show` + vistas — el Profesor elige la condición; el estudiante ve "x/y completados" o
  "Completado" por módulo (mismas clases de antes).
  - **Decisión:** la condición por defecto del módulo es "actividades" porque conserva lo que el estudiante ya veía
    (avance de tareas) y coincide con RF-66 ("contenido visualizado y/o actividades completadas").
- Prueba actualizada: `CursoShowEstudianteTest` ("1/2 tareas" → "1/2 completados").

### Módulo 3.9 — Asistencia (RF-68 del pedido = RF-73 del ERS v2)
- M `app/Livewire/Asistencia/Index.php` + vista — el Profesor del curso omite o reactiva el control de asistencia
  ("donde el Profesor no lo requiera"); los registros anteriores se conservan. El Supervisor solo consulta.

### Módulo 3.15 — Asistente de IA (RF-87 del pedido = RF-92 del ERS v2)
- M `app/Services/Bot/Concerns/ConstruyeContextoIA.php` — el contexto del estudiante incluye su calendario: fecha de
  hoy, quizzes abiertos que no rindió, sesiones de las próximas 3 semanas, rubros con su semana y sus eventos
  personales. Solo datos propios (RF-94). Prueba en `AsistenteIaTest` (el evento de otro estudiante no aparece).
- M `tests/Feature/Seguridad/AsistenteIaTest.php` — la nota de control pasa de 17 a 18.75 porque la fecha y hora del
  contexto podían contener "17" y volver inestable la prueba.

### Módulo 3.16 — Auditoría
- M `app/Livewire/PanelAdmin/Auditoria.php` + vista — "Exportar a Excel" (hoja de filtros aplicados con conteo por
  tipo + hoja de registros con fecha en formato de fecha, encabezado fijo y filtro). Reemplaza al CSV.

### Datos de ejemplo
- C `database/seeders/DemoAcademicoSeeder.php` (registrado en `DatabaseSeeder`, idempotente por el curso `MAT-201`):
  4 profesores, 25 estudiantes (2 con primer login pendiente, 2 desactivados), 6 cursos (4 publicados, 1 borrador,
  1 archivado del periodo anterior con historial completo), co-profesor, esquemas al 100 %, notas por rubro (con
  desaprobados), un ajuste de nota auditado, tareas en todos los estados (calificada, entregada, fuera de plazo,
  devuelta, no entregada), tarea grupal, quizzes con intentos, 32 sesiones por curso con asistencia (un estudiante en
  riesgo), grupos, anuncios, mensajes, comunicados (uno programado), una integración API, una matrícula por API, una
  baja con motivo, eventos de calendario y notificaciones. Contraseña de todas las cuentas: `password123`.
- C `tests/Feature/DatosDeEjemploTest.php` — los datos cumplen las reglas de negocio y no se duplican al recargar.
- Cargado en la base SQLite local (`php artisan db:seed --class=DemoAcademicoSeeder`). Supabase no se tocó.

### Nota final durante el periodo (hallazgo al revisar el Excel con datos reales)
- M `app/Services/Evaluacion/CalculadoraNotas.php` — `estaCompleta()`, `rendimiento()`, `condicion()`,
  `resumenDelCurso()`, `tasaAprobacion()`, `rendimientoDe()`.
  - **Decisión:** a mitad del periodo todos figuraban "Desaprobado" en rojo, porque los rubros pendientes suman 0 y
    nadie puede superar la mínima antes de rendirlos. Ahora, mientras falten rubros, la nota final se muestra como
    **acumulada** con la condición **"En curso (x de y rubros)"**, sin rojo. Aprobado/Desaprobado y el rojo de RF-64 se
    aplican con el esquema completo. Los reportes (promedio, bajo rendimiento, riesgo) usan el **rendimiento a la
    fecha** (promedio ponderado de los rubros calificados), y la tasa de aprobación cuenta solo esquemas completos.
- M `LibroCalificaciones` + vista, `BoletaEstudiante` + vista (texto "En curso", mismas clases), `Reportes\Dashboard`
  + vistas, `Reportes\PanelGestion`, `PanelAdmin\Metricas`, `Admin\Metricas\Index`, `ProgresoAcademicoService`,
  `ConstruyeContextoIA`, `ExportadorLibroCalificaciones` (columna "Rendimiento a la fecha").
- M `app/Services/HojasCalculo/HojaXlsx.php`, `LibroXlsx.php` — total en porcentaje y ajuste al ancho de página.
- Verificado abriendo el `.xlsx` generado con Microsoft Excel (sin avisos de reparación) y exportándolo a PDF.
- Pruebas: `CalculadoraNotasTest` (+4), `ExportacionesTest` (+1).

### Diseño — Calendario (RF-78/RF-79) para los tres roles
- M `app/Livewire/EventoCalendario/Index.php`, `resources/views/livewire/evento-calendario/index.blade.php` — rediseño
  con la paleta de cada rol (azul del Estudiante, menta de gestión):
  - Panel "Mis cursos" con el color de cada curso y filtro por curso; filtro por tipo de actividad.
  - Actividades coloreadas por curso; lo que se entrega o evalúa en sólido y las clases en estilo secundario;
    orden dentro del día: evaluación, tarea, quiz, eventos propios, clases. Vista "Agenda" por defecto en móvil.
  - Agenda por rol: Estudiante (por entregar en 7 días con aviso de vencidas, quizzes que cierran, próximas clases);
    Profesor (próximas clases, entregas por calificar agrupadas por semana, evaluaciones por registrar);
    Supervisor (cursos en marcha con semana actual, evaluaciones de las próximas 4 semanas, notas pendientes de
    registrar por rubro, comunicados programados). El calendario del Supervisor muestra evaluaciones, inicio y cierre
    de cursos y comunicados programados (antes solo veía sus eventos personales).
  - "Nuevo evento" en modal con selector de fecha para los tres roles (antes el Profesor tenía un formulario fijo).
  - Los cursos archivados no aparecen en el calendario del Estudiante (se consultan en "Mis cursos").
- M `resources/js/app.js` — idioma español de FullCalendar (ya incluido en `@fullcalendar/core`, sin dependencias
  nuevas). `npm run build` ejecutado.
- C `tests/Feature/CalendarioPorRolTest.php`; `RevisionPantallasTest` suma el calendario del Supervisor.
- Verificado con capturas reales (Chrome headless con JavaScript) usando los datos de ejemplo.

### Diseño — Revisión de pantallas de los 3 roles con datos de ejemplo
Revisadas con capturas reales (Chrome headless con JavaScript, 1440 px y 500 px). Correcciones:
- M `Reportes\PanelGestion` + vista — el Profesor: "Cursos a cargo" y los indicadores cuentan solo cursos en marcha;
  los archivados se listan al final con la etiqueta "Archivado"; "1 estudiante" en singular.
- M `PanelAdmin\Metricas` + vista — "Cursos con más atraso" excluye los archivados; textos de "promedio a la fecha".
- M `Admin\Metricas\Index` + vista — el riesgo de abandono evalúa solo cursos en marcha y el rendimiento a la fecha.
- M `ProgresoAcademicoService::resumenCurso` + `reportes/dashboard-profesor` — bajo rendimiento = rendimiento a la fecha
  bajo la mínima o 2+ tareas vencidas sin entregar (el avance < 50 % solo cuenta en cursos cerrados, porque a mitad
  del periodo incluye contenido futuro y marcaba a todos, incluso a quien tenía 20).
- M `ProgresoAcademicoService::resumenDashboardEstudiante` + `estudiante/panel-inicio` — la "Próxima entrega" era una
  fecha ya vencida (18/09 el 27/09); ahora es la siguiente fecha futura. El conteo de pendientes es el total real y
  los cursos archivados no cuentan como activos. "Ver catálogo de cursos" → "Ver mis cursos" (no hay catálogo: ERS 2.4).
- M `boleta-estudiante`, `tareas/detalle` — pesos sin ceros de relleno ("20 %").
- M `Tareas\Detalle` — las entregas por calificar aparecen primero (luego devueltas y calificadas).
- M `cursos/show.blade.php` — portada vacía del Profesor/Supervisor muestra el código del curso.
- M `usuarios/index.blade.php` — "Importar CSV" → "Importar usuarios" (acepta Excel y CSV).
- C `lang/es.json` — paginación en español ("Mostrando 1 a 15 de 30 resultados"); la app ya usaba `locale=es` pero no
  tenía traducciones y la paginación salía en inglés. Es la ubicación estándar de Laravel para traducciones.
- M `database/seeders/RoleAndUserSeeder.php`, `DemoAcademicoSeeder.php` — el profesor demo se llama "Juan Pérez
  Salinas" (antes "Prof. Juan Perez" y las vistas mostraban "Prof. Prof. Juan Perez").
- Pruebas nuevas en `CasosDeUsoTest`: próxima entrega futura y cursos archivados; criterio de bajo rendimiento.
- ARQUITECTURA.md § 11 — estado actualizado a "Ejecutada" (rol Supervisor y sin registro público verificados).

### RF-108: zona horaria y formato de fecha (27/09/2026)

- M `app/Services/ConfiguracionPlataforma.php` — `zonaHoraria()`, `aplicarZonaHoraria()`, `formatoFecha()`,
  `formatoFechaHora()`, `formatoDiaMes()`, `formatoFechaHoraExcel()`, constante `FORMATOS_FECHA`; si la tabla no
  existe devuelve los valores predeterminados.
- M `app/Providers/AppServiceProvider.php` — `ConfiguracionPlataforma` como singleton; aplica la zona horaria en cada
  solicitud y registra las macros de Carbon `formatoFecha()`, `formatoFechaHora()` y `formatoDiaMes()`.
- M `app/Livewire/PanelAdmin/Configuracion.php` + vista — aplica la zona al guardar; texto de alcance y vista previa
  del formato.
- M `app/Console/Commands/{DetectarAtrasosBot,NotificarContenidoProgramado,PublicarComunicadosProgramados}.php`,
  `database/seeders/DatabaseSeeder.php` — aplican la zona horaria al iniciar.
- M `app/Models/LogIntegracionApi.php` — `created_at` asignado por la aplicación.
- M `app/Services/HojasCalculo/LibroXlsx.php` — formato de fecha de las celdas según Configuración.
- M `app/Livewire/EventoCalendario/Index.php` — horas sin desfase para FullCalendar.
- M 53 formatos fijos (`d/m/Y`, `d/m/Y H:i`, `d/m/Y H:i:s`, `d/m`, `d M Y, H:i`) reemplazados en 28 archivos: vistas
  de cursos, tareas, quizzes, asistencia, comunicados, auditoría, integraciones, panel del estudiante, boleta PDF;
  notificaciones; `ConstruyeContextoIA`; `PropuestaBotService`; `ExportadorLibroCalificaciones`;
  `PanelAdmin\Auditoria`; `TieneEstadoPublicacion`.
- C `tests/Feature/ConfiguracionRegionalTest.php` — 4 pruebas.

### Rediseño de "Propuestas del asistente" (`/bot/propuestas`, 27/09/2026)

La lógica de negocio no cambia: el bot solo detecta y propone, y el único camino que escribe en el calendario sigue
siendo `PropuestaBotService::ejecutarPropuestaAprobada()` con una propuesta aprobada por el estudiante
(ARQUITECTURA.md, Módulo 7). Las 7 pruebas de gobernanza de `PropuestaBotTest` siguen pasando sin cambios.

- M `app/Livewire/BotSugerencias/Bandeja.php` + vista — rediseño:
  - Jerarquía por estado: "Por revisar" arriba, con tarjetas destacadas (sombra, ícono del tipo, panel de decisión);
    "Resueltas" abajo, en una lista compacta con ícono de check (aceptada) o X (rechazada) y el resultado en texto.
  - Color por curso: barra lateral y nombre del curso con el color del Calendario.
  - Ícono y etiqueta por tipo de propuesta (`PropuestaBot::TIPOS`).
  - Selector de fecha y hora del sistema (`x-selector-fecha-hora`) en lugar del input nativo.
  - "Ver en calendario" en cada propuesta; "Abrir la tarea" cuando el estudiante puede verla.
  - La aclaración de confianza se muestra como aviso destacado, con el mismo texto.
  - Estado vacío "Estás al día" con explicación y acceso al calendario.
  - Aviso si la fecha propuesta ya pasó (no se cambia la propuesta; el estudiante elige otra).
- Corrección: el campo de fecha aparecía vacío ("dd/mm/aaaa --:--") porque `wire:model` apuntaba a una clave que no
  existía; ahora se precarga con la fecha que propone el bot.
- Validación: una fecha inválida ya no acepta la propuesta (antes podía terminar en error 500) y no toca el calendario.
- Al aceptar se guarda `evento_calendario_id` en `datos_propuesta` para enlazar la propuesta con el recordatorio creado.
- C `app/Services/Calendario/ColoresCursos.php` — única fuente del color por curso y de los cursos del calendario de
  cada rol; la usan el Calendario y la bandeja.
- M `app/Livewire/EventoCalendario/Index.php` + vista — acepta `?fecha=AAAA-MM-DD`, `?evento=tarea-12` y
  `?desde=propuestas`: abre en esa fecha, marca el día, resalta la actividad (se ordena primera en su día para que no
  quede en "+N más"), muestra un aviso con "Volver a propuestas" y desplaza hasta el calendario. Los valores inválidos
  se descartan.
- C `resources/views/components/selector-fecha-hora.blade.php` — Flatpickr en español con la paleta del rol; entrega a
  Livewire el mismo formato que el input nativo (`Y-m-d\TH:i`).
- M `resources/js/app.js` — `window.cargarFlatpickr()`, cargador único que comparten el Calendario y el componente.
- M `resources/css/app.css` — estilos del día enfocado y de la actividad resaltada (respeta `prefers-reduced-motion`).
- M `app/Models/PropuestaBot.php` — `TIPOS`, `TIPO_GENERICO` y `presentacion()`.
- M `database/seeders/PropuestaBotSeeder.php`, `DatabaseSeeder.php` — las propuestas resueltas de ejemplo usan tareas
  reales del estudiante demo (antes `curso_id` 0) y la aceptada crea su recordatorio por el camino normal; el seeder
  corre después de `DemoAcademicoSeeder`. Recargado solo en la base SQLite local.
- Pruebas: +3 en `PropuestaBotTest` (fecha precargada, color igual al del Calendario y enlace; aceptar con fecha
  ajustada crea el recordatorio en esa fecha y lo enlaza; fecha inválida) y +2 en `CalendarioPorRolTest` (parámetros de
  URL válidos e inválidos).

Decisiones:

1. **Color por curso:** se usa el del Calendario, el único con un color distinto por curso. "Mis cursos" usa tres
   tonos azules según el nombre, que no identifican al curso; no se modificó para respetar el estilo del Estudiante.
   Si se quiere la misma identificación allí, basta con usar `ColoresCursos`.
2. **Selector de fecha:** los formularios de tareas y quizzes usan en realidad el input nativo
   (`x-gestion.control type="datetime-local"`). El selector estilizado del sistema es el Flatpickr del Calendario; se
   extrajo como `x-selector-fecha-hora` para reutilizarlo. Los formularios de gestión no se cambiaron en este pedido.
3. **Tipos de propuesta:** hoy el bot genera solo `redistribucion_plan` (atraso detectado → recordatorio, ícono de
   alerta). La bandeja ya distingue tipos por ícono y etiqueta; un tipo sin configuración usa el genérico
   "Sugerencia del asistente" (ícono de bombilla). La bandeja sigue mostrando solo los tipos que sabe ejecutar.
4. **Destino de "Ver en calendario":** pendiente → día del recordatorio propuesto, con la tarea resaltada; aceptada →
   día del recordatorio creado, resaltado; rechazada → día en que venció la tarea.
5. **Curso sin color:** si el curso ya no está en el calendario del estudiante (archivado, oculto o dado de baja), la
   propuesta usa un gris neutro.

### Contenido del curso por semanas (27/09/2026)

El contenido de un curso deja de agruparse en "Unidades" libres y pasa a organizarse en **Semana 1..N** (N = duración
que define el Supervisor, 16 por defecto) más una sección **"Prácticas de campo"** fuera de la numeración semanal,
mostrada antes de las semanas. La paleta, la tipografía, el encabezado del curso, sus botones y sus pestañas no
cambian.

**Modelo.** No se creó una entidad nueva: cada semana es un `Modulo` (ERS RF-34) con `semana` = N y `titulo` = tema de
la semana. Así la completitud (RF-67), el progreso, la visibilidad Borrador/Programado (RF-38/RF-39), los permisos, la
clonación y el vínculo de tareas y quizzes (`modulo_id`) siguen funcionando igual.

- C migración `2026_09_28_001808_add_semanas_y_tipo_recurso_a_contenido` (aditiva): `modulos.tipo_seccion`
  (`semana` | `practicas_campo`), `modulos.semana` (única por curso), `lecciones.tipo_recurso`; rellena los datos
  existentes (ver decisión 1). Aplicada solo en la base SQLite local, con respaldo previo en el scratchpad.
- M `app/Models/Modulo.php` — `TIPO_SEMANA`, `TIPO_PRACTICAS`, `etiqueta()` ("Semana 3" / "Prácticas de campo"),
  `tema()` (quita el prefijo "Unidad N:" de los cursos anteriores), `nombreCompleto()`; al crear sin semana se ubica en
  la siguiente libre y el orden sigue siempre a la semana.
- M `app/Models/Curso.php` — `SEMANAS_PREDETERMINADAS` (16), `totalSemanas()`, `rangoSemana()` (lunes a domingo,
  mismo cálculo que el Calendario y el esquema de evaluación).
- M `app/Models/Leccion.php` — `TIPOS_RECURSO` (lectura, video, presentación, grabación de sesión, foro de preguntas,
  actividad complementaria, recurso externo) con su ícono; `tipoRecurso()`.
- M `app/Livewire/Cursos/Show.php` + vista:
  - Profesor/Supervisor: tarjeta "Contenido por semanas" con Prácticas de campo y todas las semanas (fechas, clases
    de esa semana, estado, conteos). `prepararSeccion()` crea la semana bajo demanda. La edición permite tema, estado,
    condición de completitud y mover el contenido a otra semana libre. "Vaciar" solo procede sin historial académico.
    Se quitan "Agregar módulo" y el reordenamiento con flechas: el orden lo da el número de semana.
  - Estudiante: Prácticas de campo primero y luego las semanas con contenido visible, cada una con fechas, tema,
    "X de Y completados" y la etiqueta "Semana actual" (se abre sola). Al expandir, cada lección muestra su tipo con su
    ícono, y las tareas y quizzes de la semana aparecen con su estado.
- M `app/Livewire/Cursos/Formulario.php` + vista — "Duración (semanas)" obligatoria (16 por defecto); no puede quedar
  por debajo de la última semana con contenido. La ayuda de "N.° de sesiones" aclara que son independientes.
- M `app/Livewire/Lecciones/Index.php` + vista — campo "Tipo de recurso"; la lista muestra el ícono de cada lección.
- M formularios e índices de tareas y quizzes, estadísticas de quiz y contexto del asistente de IA — "Semana 3: Tema" en
  lugar del título del módulo; el selector se llama "Semana".
- M `resources/views/components/icono.blade.php` — íconos `grabacion` y `presentacion` (SVG).
- M `database/seeders/DemoAcademicoSeeder.php` — temas por semana (1, 5, 9, 13), tipos de recurso variados, foro de
  preguntas por semana, grabación de la primera clase y prácticas de campo en EST-220.
- Pruebas: C `tests/Feature/ContenidoPorSemanasTest.php` (6: semanas y prácticas del Profesor, límite de la duración,
  vista del Estudiante con orden, íconos, tareas y borrador oculto, mover sin pisar una semana ocupada, duración
  obligatoria y mínima, migración de Unidades). Se actualizaron `CursosTest`, `CursoShowEstudianteTest`,
  `FlujosPrincipalesTest` y `RevisionPantallasTest` a la nueva interfaz (mismas verificaciones).

Decisiones:

1. **Migración de Unidades existentes.** Cada Unidad pasa a una sola semana, en el orden que tenía. No se reparte una
   Unidad en varias semanas: habría que dividir sus lecciones sin un criterio confiable. La semana elegida es aquella
   en la que empieza su tramo del ciclo: `semana = ⌊(k−1)·N/U⌋ + 1`, con 4 unidades en 16 semanas → 1, 5, 9 y 13.
   Con los datos reales es lo que más sentido tiene: las tareas de esos cursos vencen en las semanas 3, 7, 10 y 13, y
   un mapeo 1 a 1 las habría dejado lejos de su contenido (todo en las semanas 1 a 4). Si el curso no tiene duración o
   tiene más unidades que semanas, se usa 1 a 1. No se pierde nada: lecciones, tareas, quizzes, entregas y títulos se
   conservan. El prefijo "Unidad N:" solo se oculta al mostrar el tema. El Profesor puede mover cualquier contenido a
   otra semana.
2. **Semanas bajo demanda.** Al definir 16 semanas no se crean 16 registros vacíos: el Profesor ve las 16 y cada una se
   crea al prepararla. Se evita contenido vacío publicado y se mantiene la regla RF-25, que exige contenido para
   publicar.
3. **El Estudiante solo ve semanas con contenido visible.** Las semanas vacías, en borrador o programadas no aparecen
   (RF-38). El Profesor y el Supervisor sí ven todas.
4. **Una sola sección de Prácticas de campo por curso**, con lecciones, tareas y quizzes como cualquier semana.
5. **Semanas de contenido y Sesiones de Clase (RF-40) siguen independientes.** Una sesión es un encuentro con fecha y
   asistencia; una semana agrupa material. Solo se relacionan por fecha: la vista de gestión informa cuántas clases
   caen en cada semana, sin clave entre ambas. Cambiar la duración en semanas no altera el número de sesiones, y
   viceversa.
6. **Cursos sin duración definida** se muestran con 16 semanas (o más, si ya tienen contenido después) y el formulario
   propone ese valor al editarlos.
7. **El tipo de recurso de las lecciones existentes** se infiere: "video" si tienen video, "lectura" si no. El
   Profesor puede cambiarlo.

### Prácticas de campo como bloque propio (27/09/2026)

"Prácticas de campo" deja de ser una fila más de la lista de semanas y pasa a ser un bloque independiente, arriba del
contenido semanal, en las vistas de Estudiante, Profesor y Supervisor. No cambian la paleta, la tipografía ni la lógica
de completitud y visibilidad.

- M `app/Livewire/Cursos/Show.php` — las secciones se entregan como `practicas` (bloque aparte o null) y `semanas`
  (1..N). La vista de gestión calcula `visibleParaEstudiantes` con el mismo criterio que la del estudiante.
- M `resources/views/livewire/cursos/show.blade.php`:
  - Estudiante: bloque con ícono de maletín, etiqueta "Fuera de la numeración semanal", sin número de semana ni rango
    de fechas, borde de acento a la izquierda y fondo celeste (paleta del Estudiante). Debajo, el título "Semanas del
    curso".
  - Profesor/Supervisor: el mismo bloque con la paleta de gestión (acento terracota) sobre la tarjeta "Contenido por
    semanas". "Agregar prácticas de campo" pasa a ser una acción en el encabezado de esa tarjeta. El Supervisor solo
    consulta (sin botones).
- C `resources/views/livewire/cursos/partials/elementos-seccion.blade.php` — lista de elementos (lecciones con el
  ícono de su tipo de recurso, tareas y quizzes) que comparten el bloque de prácticas y las semanas.
- M `resources/views/components/icono.blade.php` — ícono `maletin` (SVG); se quitó `ubicacion`, que ya no se usa.
- Pruebas: +1 en `ContenidoPorSemanasTest` (el bloque de prácticas es propio, va antes de las semanas y solo aparece
  para el estudiante con contenido visible; Profesor y Supervisor lo ven, y el Supervisor sin acciones). Se ajustaron
  las verificaciones de semana vacía y `CursoShowEstudianteTest`.

Decisiones:

1. **Cuándo aparece el bloque.** Estudiante: solo si tiene contenido visible (lecciones publicadas, tareas o quizzes
   visibles); si todo está en borrador o programado, no aparece (RF-38). Profesor y Supervisor: aparece en cuanto el
   curso tiene la sección, aunque esté vacía, porque es donde se gestiona; si los estudiantes todavía no lo ven, se
   indica en el mismo bloque. Sin sección de prácticas no hay bloque ni placeholder, solo la acción para agregarla.
2. **Semanas sin contenido para el Estudiante (reemplaza la decisión 3 de "Contenido del curso por semanas").** Ahora
   se muestran las semanas 1..N; una semana sin contenido visible (vacía, en borrador o programada) aparece como
   "Aún no disponible", con número y fechas, sin su tema ni su contenido, para no revelar lo que está en preparación.
3. **Ícono.** Maletín, del mismo set de íconos de línea que el resto de la plataforma; distinto del distintivo
   "SEM N" de las semanas.
