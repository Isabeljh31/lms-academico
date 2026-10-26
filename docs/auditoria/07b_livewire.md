# 07b. Componentes Livewire (parte 2 de 2)

Continúa 07a. Cubre `Grupos`, `Lecciones`, `Mensajeria`, `Notificaciones`, `PanelAdmin`, `Quizzes`, `Reportes`, `Tareas`, `Usuarios` y los 7 componentes Volt. Mismo método y mismas convenciones (07a, § 1).

## 1. Carpeta `Grupos`

### 1.1 Grupos\Index (191 líneas)
- Propósito: crear, renombrar y eliminar grupos y mover estudiantes (RF-74, RF-75). Ruta `cursos.grupos.index` con `role:profesor|supervisor`.
- Propiedades: `curso`, `nuevoGrupoNombre`, `tamanoGrupoAuto`, `seleccion` (estudiante elegido por grupo), `grupoRenombrarId` (`#[Locked]`), `nombreRenombrar`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `supervisar` (39) |
| `crearGrupo` | Crea un grupo | nombre único en el curso | `autorizar`: `GrupoPolicy::crear` y no archivado |
| `empezarRenombrar` / `renombrar` | Renombra | nombre único | `autorizar` |
| `eliminarGrupo` | Borra el grupo | grupo del curso | `autorizar` y `gestionar` |
| `asignar` | Quita al estudiante de otros grupos del curso y lo agrega a este, en transacción (un grupo por curso, RF-75) | estudiante con matrícula activa (114) | `autorizar` y `gestionar` |
| `quitar` | Saca al estudiante del grupo | — | `autorizar` y `gestionar` |
| `generarAutomatico` | Reparte en grupos nuevos a los matriculados sin grupo | tamaño 2-20 | `autorizar` |

- Pruebas: `CasosDeUsoPrincipalesTest`, `CasosDeUsoTest`, `GruposTest`.
- Problemas:
  - Hecho: mover a un estudiante de grupo no toca sus entregas grupales anteriores (`grupo_id` queda con el grupo viejo). Junto con H-04, si el estudiante entrega en el grupo nuevo, sobrescribe las entregas de los integrantes del grupo nuevo.
  - Hecho: `eliminarGrupo` no comprueba si hay tareas asignadas a ese grupo ni entregas con ese `grupo_id`. Ambas FK son `nullOnDelete` (`2026_09_17_224626_add_grupo_id_to_entregas_table.php:21-22`; `2026_09_26_054529_add_campos_contenido_y_evaluacion_ers.php:33-34`). Consecuencias (por análisis, no probado): una tarea grupal asignada a ese grupo pasa a ser grupal **para todo el curso** (`grupo_asignado_id = null`), y las entregas del grupo quedan sueltas, de modo que recalificar una ya no alcanza a los demás integrantes.
  - Hecho: `generarAutomatico` nombra los grupos "Grupo N" con N = cantidad actual + 1; si ya existe un grupo con ese nombre (por ejemplo, tras borrar uno), se crea un nombre duplicado: no hay restricción única en la base (`grupos` no tiene índice único `(curso_id, nombre)`, archivo 04b § 4).

## 2. Carpeta `Lecciones`

### 2.1 Lecciones\Index (301 líneas)
- Propósito: CRUD de lecciones de una semana o de Prácticas de campo (RF-35 a RF-38), recursos adicionales (RF-36, entidad Recurso) y aviso al publicar (RF-84). Ruta `lecciones.index` con `role:profesor`.
- Propiedades: `modulo`, `mostrarFormulario`, `leccionEditId` (`#[Locked]`), `titulo`, `descripcion`, `video_url`, `material_url`, `archivo`, `archivoActual`, `recursoTipo`, `recursoNombre`, `recursoUrl`, `recursoArchivo`, `estado`, `fecha_publicacion`, `condicion_completitud`, `tipo_recurso`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `gestionar` del curso (72) |
| `abrirNueva`, `editar` | Preparan el formulario | lección del módulo | `autorizarEdicion`: `gestionar` y curso no archivado |
| `guardar` | Crea o actualiza; sube el archivo privado; notifica `NuevoContenidoPublicado` si pasa a visible y el módulo es visible | URLs `http(s)`, archivo ≤ 10 MB de la lista blanca, fecha programada futura, condición y tipo en lista (119-135) | `autorizarEdicion` |
| `agregarRecurso` | Agrega un recurso (archivo privado o enlace) a la lección en edición | tipo, nombre, URL o archivo | `autorizarEdicion` |
| `eliminarRecurso` | Borra el recurso y el archivo si ninguna otra fila lo usa | recurso de la lección | `autorizarEdicion` |
| `eliminar` | Borra la lección | lección del módulo | `autorizarEdicion` |
| `moverArriba`, `moverAbajo` | Reordenan | — | `autorizarEdicion` |

- Pruebas: `CasosDeUsoPrincipalesTest`, `CasosDeUsoTest`, `RecursosLeccionTest`, `Seguridad/ArchivosPrivadosTest`, `IdorTest`.
- Problemas:
  - Hecho: `eliminar` borra la lección sin borrar su archivo (`material_url`) ni los de sus recursos (líneas 242-248). Quedan archivos huérfanos en Cloudinary o en `storage/app/private`.
  - Hecho: al reemplazar el archivo de una lección (líneas 170-173), el archivo anterior no se borra.
  - Hecho: `mover` actualiza el orden de todas las lecciones del módulo con un `update` por fila (líneas 277-279), sin transacción.
  - Hecho: no hay aviso a los estudiantes cuando cambia un recurso de una lección ya publicada (RF-84 solo exige "nuevo contenido").

## 3. Carpeta `Mensajeria`

### 3.1 Mensajeria\Bandeja (191 líneas)
- Propósito: mensajes privados dentro de cursos comunes (RF-80), aviso a todo el curso (RF-81), adjuntos (RF-82) y notificación (RF-83). Ruta `mensajeria.bandeja` con `role:estudiante|profesor`.
- Propiedades: `vista`, `mensajeAbiertoId`, `destinatarioId`, `cursoContextoId`, `asunto`, `cuerpo`, `adjunto`, `enviarATodos`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `hasAnyRole(estudiante, profesor)` |
| `abrirMensaje` | Abre un mensaje propio y lo marca leído si es el destinatario | — | consulta limitada a remitente o destinatario (59-60) |
| `enviar` | Crea un mensaje por destinatario con el mismo adjunto y notifica `NuevoMensaje` | curso, destinatario, asunto ≤255, cuerpo ≤10000, adjunto ≤ 10 MB (71-81) | curso entre `misCursos()`; destinatario entre `contactosDe()`; "a todos" solo profesor docente (88) |
| `responder` | Prepara la respuesta | mensaje propio | igual que `abrirMensaje` |

- Pruebas: `CasosDeUsoPrincipalesTest`, `CasosDeUsoTest`, `RolesYFuncionalidadTest`, `IdorTest`.
- Problemas:
  - Hecho: `mensajeAbiertoId` es público y no `#[Locked]`, pero cada lectura se filtra por remitente o destinatario (líneas 183-185): no hay IDOR.
  - Hecho: el profesor de un curso puede escribir a sus estudiantes aunque el curso esté archivado (`misCursos` del profesor no filtra por estado, línea 143). Opinión: aceptable.
  - Hecho: el envío "a todos" crea N filas y N notificaciones síncronas en la misma petición (líneas 97-108).

## 4. Carpeta `Notificaciones`

### 4.1 Notificaciones\Campanita (35 líneas)
- Propósito: campana de notificaciones del layout (RF-84, RF-85).
- Métodos: `alternar`, `marcarLeida(string $id)` (solo notificaciones propias: `auth()->user()->notifications()->whereKey($id)`), `marcarTodasLeidas`, `render` (últimas 8 y conteo de no leídas).
- Pruebas: ninguna de la clase. `CasosDeUsoPrincipalesTest` comprueba que las notificaciones existen en la base.
- Problemas: hecho, se renderiza en cada página para cada usuario con 2 consultas (línea 31-32). Sin problema funcional.

## 5. Carpeta `PanelAdmin`

### 5.1 PanelAdmin\Auditoria (157 líneas)
- Propósito: bitácora de solo lectura con filtros (RF-97 a RF-99) y exportación a Excel. Ruta `admin.auditoria`.
- Propiedades: `filtroTipo`, `filtroCursoId`, `filtroDesde`, `filtroHasta`, `filtroUsuario`.
- Métodos: `mount` y `exportarExcel` con `hasRole('supervisor')`; `limpiarFiltros`; `updated` valida las fechas. La consulta ignora valores inválidos (tipo fuera de la lista, curso no numérico, fechas no parseables) y limita la búsqueda de usuario a 100 caracteres (líneas 70-88). Exporta como máximo 5000 filas.
- Pruebas: `HojasCalculo/ExportacionesTest`, `Seguridad/AuditoriaTest`.
- Problemas:
  - Hecho: la exportación con el límite de 5000 no avisa si hay más registros (el resumen muestra "Registros exportados" pero no el total).
  - Hecho: la exportación de la bitácora no queda registrada en la propia bitácora. El ERS no lo pide.

### 5.2 PanelAdmin\Configuracion (119 líneas)
- Propósito: nota mínima (RF-107), nombre de la institución, zona horaria y formato de fecha (RF-108) y activar el asistente. Ruta `admin.configuracion`.
- Propiedades: `botActivo`, `notaMinima`, `nombreInstitucion`, `zonaHoraria`, `formatoFecha`.
- Métodos: `guardar` (nota 0-20 con un decimal, zona en `DateTimeZone::listIdentifiers()`, formato en lista; guarda solo lo que cambió y lo registra en la bitácora) y `alternarBot` (bitácora). Ambos exigen `hasRole('supervisor')`.
- Pruebas: `ConfiguracionRegionalTest`, `FlujosPrincipalesTest`, `PanelAdminTest`.
- Problemas:
  - Hecho: cambiar la nota mínima cambia la condición (aprobado o desaprobado) de **todos** los cursos, incluidos los archivados, porque la nota final se recalcula en cada lectura (RNF-12) y no se guarda la nota mínima vigente al cerrar el curso. Esto contradice la inmutabilidad del periodo finalizado (regla 8, RNF-14). Ver H-20.
  - Hecho: cambiar la zona horaria no convierte las fechas ya guardadas (se guardan sin zona). Todas las fechas existentes se reinterpretan en la zona nueva. El formulario no lo advierte (opinión: riesgo operativo si se cambia con datos).
  - Hecho: la vista ofrece solo las zonas de América más UTC (línea 116), pero la validación acepta cualquier zona.

### 5.3 PanelAdmin\Integraciones (131 líneas)
- Propósito: API Keys por institución (RF-101, RNF-04) y log de solicitudes (RF-106). Ruta `admin.integraciones`.
- Propiedades: `mostrarModalCrear`, `nombre`, `descripcion`, `apiKeyVisible`, `apiKeyNombre`, `filtroLog`.
- Métodos: `crear`, `regenerar(int)`, `revocar(int)` (los tres con bitácora y `hasRole`), `ocultarKey`.
- Pruebas: `Seguridad/ApiIntegracionTest`.
- Problemas:
  - Hecho: la clave en texto plano queda en la propiedad pública `apiKeyVisible` (línea 32), así que viaja en el *snapshot* de Livewire en cada petición hasta que el Supervisor pulsa "ocultar". Se diseñó para mostrarla una vez; opinión: aceptable, pero conviene borrarla tras el primer render.
  - Hecho: `regenerar` y `revocar` no piden confirmación en el servidor (la confirmación, si existe, es solo de la vista).

### 5.4 PanelAdmin\Metricas (140 líneas)
- Propósito: dashboard institucional (RF-90, RF-91). Ruta `admin.metricas`.
- Propiedades: `periodo` (7, 30, 90 o 365; cualquier otro valor vuelve a 30, línea 44).
- Métodos: `mount` (`hasRole`), `updatedPeriodo`, `render`.
- Pruebas: `PanelAdminTest`.
- Problemas:
  - Hecho: `rendimientoPorCurso` ejecuta `inscripcionesActivas()->pluck()` y `resumenDelCurso()` por cada curso (líneas 87-88): 2 consultas por curso (N+1 sobre la cantidad de cursos).
  - Hecho: `cursosConMasAtraso` carga todas las tareas vencidas visibles de la institución con sus entregas (líneas 53-57).
  - Hecho: la "tasa de abandono" cuenta las matrículas con `activo_local = false` como abandono (línea 107), aunque son bloqueos locales de matrículas de API, no bajas.

## 6. Carpeta `Quizzes`

### 6.1 Quizzes\BancoPreguntas (188 líneas)
- Propósito: banco de preguntas por tema (RF-47). Ruta `cursos.banco-preguntas` con `role:profesor`.
- Propiedades: `curso`, `filtroTema`, `busqueda`, `mostrarModal`, `preguntaId` (`#[Locked]`), `tema`, `pregunta`, `tipo`, `opciones`, `correctas`, `explicacion`, `dificultad`.
- Métodos: `nueva`, `editar`, `agregarOpcion`, `quitarOpcion`, `guardar` (valida tipo, 2-8 opciones, índices de correctas dentro del rango, una sola correcta salvo opción múltiple), `eliminar`. Todos con `gestionar` del curso.
- Pruebas: solo carga de la ruta (`RevisionPantallasTest`, `AccesoPorRolTest`). **Ninguna prueba de guardar, editar ni eliminar.**
- Problemas: hecho, se puede editar el banco de un curso archivado (no hay comprobación de `estaFinalizado`). No afecta a quizzes ya creados porque la pregunta se copia (archivo 04b § 3).

### 6.2 Quizzes\Estadisticas (42 líneas)
- Propósito: tasa de error por pregunta. Ruta `cursos.quizzes.estadisticas`.
- `mount` con `QuizPolicy::supervisar`; `render` con `Quiz::estadisticasPorPregunta()` (último intento de cada estudiante) y promedio de **todos** los intentos.
- Pruebas: `CasosDeUsoPrincipalesTest:98` y `QuizzesTest` (acceso y cálculo del modelo).
- Problemas: hecho, el promedio general usa todos los intentos (línea 38) mientras que las estadísticas por pregunta usan el último y el libro de calificaciones el mejor. Tres criterios distintos para el mismo quiz.

### 6.3 Quizzes\Formulario (412 líneas)
- Propósito: crear y editar quizzes (RF-44, RF-45, RF-47) y corrección con recálculo (RF-50 a RF-53). Rutas `cursos.quizzes.crear` y `cursos.quizzes.editar`.
- Propiedades: `curso`, `quiz`, `tema`, `modulo_id`, `preguntas`, `tiempo_limite_min`, `intentos`, `aleatorizar`, `mostrar_retroalimentacion`, `fecha_inicio`, `fecha_limite`, `estado_contenido`, `publicar_en`, `temaBanco`, `seleccionBanco`, `mostrarModalRecalculo`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | Carga o crea con una pregunta | — | `gestionar` o `crear`; curso no archivado |
| `agregarPregunta`, `eliminarPregunta`, `agregarOpcion`, `eliminarOpcion` | Edición local | `abort_if(estructuraBloqueada)` si ya hay intentos | — |
| `updatedPreguntas` | Normaliza al cambiar el tipo | — | — |
| `agregarDesdeBanco` | Copia preguntas del banco | — | `gestionar`; estructura no bloqueada |
| `guardar` | Valida; si hay intentos y cambia la corrección, abre el modal de recálculo; si no, guarda | 1-100 preguntas, 2-8 opciones, puntaje 0,5-100, tiempo 1-600, intentos 1-10, fechas (300-338) | `autorizar` |
| `confirmarGuardado(bool)` | Guarda y, si se pide, recalcula todos los intentos, audita cada cambio y notifica `NotaQuizModificada` (RF-53) | igual | `autorizar` |

- Pruebas: `CasosDeUsoPrincipalesTest`, `CasosDeUsoTest`, `QuizzesTest` (recálculo, auditoría y notificación).
- Problemas:
  - **H-19 (Bajo). Hecho por lectura, no verificado en ejecución:** el bloqueo de estructura (no agregar ni quitar preguntas u opciones cuando ya hay intentos, porque las respuestas se ligan por posición, H-09) solo se aplica en los métodos auxiliares. `guardar` y `confirmarGuardado` no comparan la cantidad de preguntas y opciones con las guardadas (líneas 184-206, 292-339), y `preguntas` es una propiedad pública que el navegador puede reemplazar completa (`updatedPreguntas` con clave null, línea 151). Solo puede hacerlo el profesor dueño del quiz.
  - Hecho: los cambios de configuración (intentos, tiempo, fechas) de un quiz con intentos no se auditan.
  - Hecho: `fecha_limite` debe ser posterior a `fecha_inicio`, pero ni `publicar_en` ni `fecha_limite` se validan contra la fecha actual.

### 6.4 Quizzes\Index (80 líneas)
- Propósito: listado de quizzes del curso por rol; publicar u ocultar (RF-37) y eliminar si no tiene resultados. Ruta `cursos.quizzes.index`.
- Métodos: `alternarVisibilidad` (`gestionar`, no archivado, notifica `NuevoQuizPublicado` al publicarse por primera vez) y `eliminar` (`QuizPolicy::eliminar`: sin resultados).
- Pruebas: `ListadosEstudianteTest`, `QuizzesTest`.
- Problemas: hecho, ni ocultar ni eliminar se auditan.

### 6.5 Quizzes\Resolver (151 líneas)
- Propósito: rendir el quiz (RF-44 a RF-46). Ruta `cursos.quizzes.show`. El profesor ve una vista previa sin enviar.
- Propiedades: `quiz`, `respuestas`, `orden` (`#[Locked]`), `iniciadoEn` (`#[Locked]`), `intentando` (`#[Locked]`). Constante `MARGEN_SEGUNDOS=30`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | Si no hay resultado y puede responder, inicia el intento | — | `QuizPolicy::ver` (43) |
| `iniciarIntento` | Prepara respuestas, baraja el orden si corresponde y fija `iniciadoEn = now()` | — | `responder` |
| `enviar` | Rechaza si pasó el tiempo límite más 30 s; valida cada respuesta; crea `ResultadoQuiz` con el intento siguiente y el puntaje | respuesta por pregunta dentro del rango de opciones (113-130) | `responder` y `intentando` |

- Pruebas: `CasosDeUsoPrincipalesTest`, `CasosDeUsoTest`, `QuizzesTest` (tiempo vencido, intentos agotados, aleatorización).
- Problemas:
  - **H-13 (Medio). Hecho por lectura:** el inicio del intento solo vive en el estado del componente (`iniciadoEn`, línea 36); no se guarda en la base. Recargar la página crea un componente nuevo y `mount` vuelve a llamar a `iniciarIntento` (línea 49), con un `iniciadoEn` nuevo. El tiempo límite (RF-45) mide el tiempo desde la última carga, no desde que el estudiante vio las preguntas por primera vez. El estudiante puede leer el quiz, recargar y responder con el reloj reiniciado.
  - Hecho: `intento = intentosUsados + 1` (línea 134) sin bloqueo: dos envíos simultáneos pueden pasar la policy (`quedanIntentos`) y crear un intento de más. Riesgo bajo.
  - Hecho: si se agota el tiempo, las respuestas se descartan por completo (línea 107) en lugar de guardarse como intento con lo respondido. Es una decisión de diseño, no un defecto (opinión).

## 7. Carpeta `Reportes`

### 7.1 Reportes\Dashboard (64 líneas)
- Propósito: reportes por rol: el estudiante ve su progreso por curso (RF-59, RF-60, RF-68); profesor y Supervisor ven el resumen por curso (RF-69, RF-89). Ruta `reportes.dashboard`.
- Solo `render`. Usa `ProgresoAcademicoService::progresoEstudiante`, `resumenRubros`, `avance` y `resumenCurso`.
- Pruebas: `ReportesTest`.
- Problemas:
  - Hecho: no tiene `mount` ni control propio: la ruta solo exige `auth` y `verified`; los datos se filtran por rol en `render`. Correcto, pero sin defensa en profundidad.
  - Hecho: para profesor y Supervisor, `resumenCurso` llama a `avance()` por cada estudiante (4 consultas por estudiante, `ProgresoAcademicoService.php:188`): N+1 sobre estudiantes × cursos. Para el Supervisor recorre **todos** los cursos.
  - Hecho: el avance mostrado hereda H-02.

### 7.2 Reportes\PanelGestion (119 líneas)
- Propósito: panel de inicio de profesor (RF-88) y Supervisor (RF-90). Embebido en `dashboard`.
- `mount` con `hasAnyRole(profesor, supervisor)`; `render` con `datosProfesor` o `datosSupervisor`.
- Pruebas: solo la carga de `dashboard` (`ExampleTest`, `RolesYFuncionalidadTest`). Ninguna prueba de los indicadores.
- Problemas:
  - Hecho: `datosProfesor` hace 2 consultas por curso para el promedio (líneas 48-54).
  - Hecho: "tasa de finalización" del Supervisor = porcentaje de aprobados en cursos archivados (líneas 80-96). El ERS (RF-90) no define el término; la definición está documentada en el docblock.
  - Hecho: `quizzesActivos` carga todos los quizzes visibles y filtra en PHP (líneas 65-69).

## 8. Carpeta `Tareas`

### 8.1 Tareas\Detalle (287 líneas)
- Propósito: entrega del estudiante (RF-42, RF-43) y revisión del profesor: calificar o devolver (RF-49, RF-95). Ruta `cursos.tareas.show` con `scopeBindings`.
- Propiedades: `tarea`, `archivo`, `comentario`, `respuestaTexto`, `enlace`, `notas` (por entrega), `feedbacks` (por entrega).

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `TareaPolicy::ver` (52) |
| `entregar` | Sube el archivo, y crea o actualiza la entrega; si es grupal, una fila por cada integrante actual del grupo | archivo de los formatos de la tarea ≤ 10 MB, texto ≤10000, enlace `http(s)`, al menos uno de los tres (130-144) | `EntregaPolicy::crear`; intentos o devuelta; pertenecer a un grupo si es grupal |
| `calificar(int)` | Califica la entrega o todo el grupo; bitácora si cambia; notifica `TareaCalificada` | 0-20 | `EntregaPolicy::calificar` |
| `devolver(int)` | Pasa a "devuelto" con comentario y notifica `EntregaDevuelta` | comentario obligatorio | `EntregaPolicy::calificar` |

- Pruebas: `BlackboardDocenteAlumnoTest`, `CalendarioNotificacionesTest`, `CasosDeUsoPrincipalesTest`, `CasosDeUsoTest`, `GruposTest`, `Seguridad/ArchivosPrivadosTest`, `IdorTest`, `SubidaArchivosTest`, `TareasTest`.
- Problemas:
  - **H-04 (Alto). Hecho confirmado por prueba temporal** (`test_h_entrega_grupal_de_integrante_nuevo_borra_nota_de_los_demas`). En `entregar` (líneas 150-170):
    - la comprobación de intentos y de "ya calificada" se hace solo con la entrega del estudiante que envía (`miEntrega`, líneas 114-122);
    - luego `updateOrCreate` escribe en la fila de **cada** integrante `estado = entregado`, `nota = null`, `intentos_usados` del que envía + 1 y su archivo;
    - resultado: un estudiante que entra al grupo después de la calificación (o cualquier integrante con intentos disponibles mientras otro ya fue calificado) borra las notas de todo el grupo. La pérdida de nota no queda en la bitácora (RF-95).
  - Hecho: el cambio de `grupo_id` y de los datos de la entrega actualiza `updated_at` de los demás integrantes, que es el "momento de entrega" usado por `Tarea::estadoPara` (H-10).
  - Hecho: `devolver` no deja registro en la bitácora y no limpia la nota. Si la entrega estaba calificada, sigue con nota y estado "devuelto".
  - Hecho: en `render`, la lista "sin entregar" incluye a todos los matriculados activos, también a quienes no pertenecen al grupo asignado de una tarea grupal (líneas 276-279).
  - Hecho: la regla de "puede reentregar" difiere entre vistas: `Detalle` permite reentregar una devuelta sin límite de intentos (línea 97), lo que es coherente con RF-49.

### 8.2 Tareas\Formulario (155 líneas)
- Propósito: crear y editar tareas (RF-37, RF-40, RF-41, RF-76) y avisos de nueva tarea o cambio de fecha (RF-84). Rutas `cursos.tareas.crear` y `cursos.tareas.editar`.
- Propiedades: `curso`, `tarea`, `titulo`, `descripcion`, `instrucciones`, `modulo_id`, `fecha_inicio`, `fecha_entrega`, `peso_nota`, `intentos_permitidos`, `formatos_permitidos`, `tipo_entrega`, `grupo_asignado_id`, `estado_contenido`, `publicar_en`.
- Métodos: `mount` (`gestionar` o `crear`; curso no archivado) y `guardar` (valida y guarda; notifica `NuevaTareaAsignada` al pasar a visible o `CambioFechaTarea` si cambia la fecha de una visible).
- Validación (líneas 93-113): semana del curso, fecha límite ≥ inicio, peso 0-100, intentos 1-10, formatos dentro de la lista blanca, grupo del curso, estado en lista.
- Pruebas: `CalendarioNotificacionesTest`, `CasosDeUsoPrincipalesTest`, `TareasTest`.
- Problemas:
  - Hecho: el estado "borrador" se guarda como `visible=false` con `estado_contenido='publicado'` (líneas 120-121): dos campos para un concepto (archivo 05).
  - Hecho: `publicar_en` acepta fechas pasadas; en `Lecciones\Index` se exige fecha futura (`after:now`). Criterio distinto.
  - Hecho: `peso_nota` se guarda y se usa en `progresoEstudiante` como peso del promedio de práctica, pero el ERS (RF-48) dice que las tareas no ponderan la nota final; el campo puede confundir (opinión).
  - Hecho: la edición de una tarea no se audita.

### 8.3 Tareas\Index (86 líneas)
- Propósito: listado de tareas; publicar u ocultar y eliminar. Ruta `cursos.tareas.index`.
- Métodos: `alternarVisibilidad` (`gestionar`, no archivado, notifica al publicarse) y `eliminar` (`TareaPolicy::eliminar`: sin entregas).
- El estudiante solo ve tareas `visiblesParaEstudiante` filtradas además con `TareaPolicy::ver` (líneas 78-81).
- Pruebas: `ListadosEstudianteTest`, `TareasTest`.
- Problemas: ninguno funcional.

## 9. Carpeta `Usuarios`

### 9.1 Usuarios\ImportarCSV (268 líneas)
- Propósito: importación masiva desde `.xlsx` o `.csv` (RF-11 a RF-13). Ruta `usuarios.importar` con `role:supervisor`.
- Propiedades: `archivo`, `procesado`, `creados`, `omitidos`, `errores`, `preview`, `mostrarPreview`. Constante `MAX_FILAS=1000`.
- Métodos: `updatedArchivo` y `previsualizar` (primeras 10 filas), `importar` (valida cada fila, omite correos existentes, crea con `GestorCuentas`, captura errores por fila), `reiniciar`. Todos con `UsuarioPolicy::verListado`.
- Validación del archivo: `csv`, `txt` o `xlsx`, ≤ 2 MB (línea 158). Por fila: nombre y correo no vacíos, correo válido, rol en `ROLES_CREABLES` (estudiante o profesor), sin duplicados dentro del archivo.
- Pruebas: `CasosDeUsoTest`.
- Problemas:
  - Hecho: las contraseñas temporales de todas las cuentas creadas quedan en la propiedad pública `creados` (línea 137) y por lo tanto en el *snapshot* de Livewire del navegador del Supervisor hasta que reinicia. Es la forma de mostrar el reporte (RF-13); opinión: aceptable, pero no deberían persistir tras salir de la pantalla.
  - Hecho: la importación de 1000 filas se ejecuta en una sola petición, con una consulta `exists` y una creación con correo por fila (líneas 101-143). El correo va en cola (`BienvenidaMail` es `ShouldQueue`), pero las 1000 inserciones y auditorías son síncronas.
  - Hecho: el límite de 1000 filas se revisa solo en `importar`; `previsualizar` lee el archivo completo.

### 9.2 Usuarios\Index (273 líneas)
- Propósito: gestión de usuarios (RF-10, RF-14 a RF-17, RF-96). Ruta `admin.usuarios.index`.
- Propiedades: filtros (`busqueda`, `filtroRol`, `filtroEstado`, `filtroCursoId`), creación (`nuevoNombres`, `nuevoApellidos`, `nuevoEmail`, `nuevoRol`), edición (`usuarioEditarId` `#[Locked]`, `editarNombre`, `editarEmail`) y desactivación (`usuarioADesactivarId` `#[Locked]`).

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `crearUsuario` | Crea con `GestorCuentas` y muestra la contraseña temporal por *flash* | nombres, apellidos, correo único, rol creable | `verListado` |
| `actualizarRol(int, string)` | `syncRoles([$rol])` y bitácora | rol en `Rol::cases()` (los 3) | `UsuarioPolicy::actualizarRol` (no sobre sí mismo) |
| `abrirEditar`, `guardarEdicion` | Edita nombre y correo; bitácora antes/después | correo único | `verListado` |
| `confirmarDesactivar`, `desactivarCuenta` | Desactiva (no sobre sí mismo); bitácora | — | `verListado` |
| `activarCuenta` | Reactiva; bitácora | — | `verListado` |

- Pruebas: `FlujosPrincipalesTest`, `ValidacionEntradasTest`, `UsuariosTest`.
- Problemas:
  - **H-12 (Alto, ya registrado en 06).** `actualizarRol` acepta `supervisor` (línea 127). Además (hecho): no revisa las consecuencias del cambio: un profesor que pasa a estudiante sigue como `profesor_id` de sus cursos (nadie puede gestionarlos, `gestionar` exige el rol), y un estudiante que pasa a profesor conserva sus matrículas activas.
  - Hecho: `desactivarCuenta` no cierra las sesiones abiertas del usuario; `CuentaActiva` lo expulsa en su siguiente petición web (archivo 06). Efecto equivalente.
  - Hecho: el Supervisor puede editar el correo y desactivar a otros supervisores; solo se impide sobre sí mismo.

## 10. Componentes Volt (7, en `resources/views/livewire`)

| Componente | Qué hace | Hallazgos |
|---|---|---|
| `pages/auth/login` (71 líneas) | Usa `LoginForm` (07a § 11.1) y regenera la sesión | Mensaje de error en inglés (ver abajo) |
| `pages/auth/forgot-password` (61) | `Password::sendResetLink` | Revela si el correo existe (ver abajo) |
| `pages/auth/reset-password` (111) | `Password::reset`; además limpia `intentos_fallidos`, `bloqueado_hasta` y `primer_login` (RF-06, RF-08, RF-05) | Política `Password::defaults()` |
| `pages/auth/verify-email` (58) | Reenvío de verificación | Inalcanzable en la práctica: `User` no implementa `MustVerifyEmail` (archivo 05) |
| `pages/auth/confirm-password` (62) | Confirmación de contraseña de Breeze | Ninguna ruta propia usa `password.confirm` (búsqueda en `routes/`) |
| `profile/update-password-form` (79) | Cambia la contraseña con la actual | Política `Password::defaults()`; no regenera la sesión |
| `profile/update-profile-information-form` (155) | Nombre, correo y foto de perfil (`image`, ≤ 4 MB, sube a privado con `SubidorArchivos`) | El usuario cambia su propio correo sin confirmación; `email_verified_at` pasa a null sin efecto |

Hallazgos de los Volt:
- **H-17 (Bajo). Hecho verificado en ejecución** (`php artisan tinker`, solo lectura, con `setLocale('es')`): no hay traducciones de `auth.*` ni `passwords.*` (solo existe `lang/es.json`, 9 claves). Con `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=en` (`.env.example:8-9`), el login fallido muestra "These credentials do not match our records." y la recuperación muestra "We can't find a user with that email address." cuando el correo no existe. El segundo mensaje permite saber qué correos tienen cuenta (enumeración), lo que el `LoginForm` intenta evitar a propósito.
- **H-18 (Bajo). Hecho:** hay dos políticas de contraseña: primer ingreso `Password::min(8)->letters()->numbers()` (`CambiarPasswordPrimerLogin.php:36`) y `Password::defaults()` en restablecimiento y perfil, que sin configuración propia (no se llama a `Password::defaults(...)` en `app/`) solo exige 8 caracteres. Un usuario puede bajar la fortaleza de su contraseña después del primer ingreso.
- Hecho: la foto de perfil usa `max:4096` (4 MB) mientras el resto del sistema usa 10 MB (`config/archivos.php`). El límite real del sistema sigue siendo 10 MB; este campo es más restrictivo.

## 11. Problemas transversales de los componentes (resumen)

- Calificar tareas tiene 3 implementaciones con reglas distintas (grupo, notificación, motivo, decimales): `Tareas\Detalle::calificar`, `Docente\PorCalificar\Index::guardarCalificacion`, `Cursos\LibroCalificaciones::guardarNotaTarea` (H-16 y archivo 14).
- Faltan registros de bitácora en acciones que cambian datos académicos o visibles: devolver entrega, guardar asistencia, ocultar o eliminar tareas y quizzes, editar tareas y configuración de quizzes, eliminar anuncios. El ERS solo exige notas (RF-95) y acciones administrativas del Supervisor (RF-96), así que no es un incumplimiento, pero limita la trazabilidad (opinión).
- Archivos que no se borran al reemplazar o eliminar: portada del curso, material de la lección, recursos de una lección eliminada.
- Ningún componente usa paginación en listas que crecen con la institución en Supervisor (`Admin\Metricas\Index`, `PanelAdmin\Metricas`, `Reportes\Dashboard`): ver archivo 14.

## 12. Hallazgos nuevos de esta parte

| ID | Severidad | Componente | Resumen |
|---|---|---|---|
| H-13 | Medio | `Quizzes\Resolver` | El tiempo límite se reinicia al recargar la página (inicio del intento no persistido) |
| H-17 | Bajo | Volt `forgot-password` y `login` | Mensajes de autenticación en inglés; la recuperación revela si un correo existe |
| H-18 | Bajo | Volt `reset-password`, `update-password-form` | Dos políticas de contraseña; después del primer ingreso basta con 8 caracteres |
| H-19 | Bajo | `Quizzes\Formulario` | El bloqueo de estructura de un quiz con intentos se puede evitar reemplazando `preguntas` |
| H-20 | Medio | `PanelAdmin\Configuracion` | Cambiar la nota mínima cambia la condición de cursos archivados |

Hallazgos previos confirmados aquí: H-04 (§ 8.1), H-09 (§ 6.3), H-10 (§ 8.1), H-12 (§ 9.2).
