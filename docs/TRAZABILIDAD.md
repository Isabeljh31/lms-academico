# Matriz de trazabilidad — LearnUp (ERS v2.0)

Fuente: `ERS_LearnUp_v2.docx` (versión 2.0, setiembre 2026), cruzada con el código de la rama
`feature/rediseno-estudiante-y-modulos`.

Estados: **Completo** · **Parcial** · **Falta** · **Roto** (existía código que fallaba) · **No aplica**.

- **Inicial**: resultado del análisis de la Fase 1, antes de modificar código.
- **Final**: estado al cierre del trabajo, con las pruebas que lo respaldan.

Numeración: en el ERS v2 los IDs son correlativos (RF-01 a RF-108, RNF-01 a RNF-21) y no hay duplicados. Ver
`docs/AUDITORIA.md` § 2.

Carpetas de pruebas: `U` = `tests/Unit`, `S` = `tests/Feature/Seguridad`, `F` = `tests/Feature/Flujos`,
`P` = `tests/Feature/RevisionPantallasTest.php`.

## 1. Requisitos funcionales

### 3.1 Autenticación y cuentas (Módulo 01)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-01 | Login con correo y contraseña | Completo | Completo | `Forms\LoginForm` · `Auth\AuthenticationTest` | |
| RF-02 | Recuperar contraseña | Completo | Completo | Volt `forgot-password` · `S/AutenticacionTest` | El envío real depende del SMTP configurado. |
| RF-03 | Contraseña temporal | Parcial | Completo | `Services\Usuarios\GestorCuentas` (todas las vías) · `UsuariosTest`, `F/FlujosPrincipales` | |
| RF-04 | Correo de bienvenida | Parcial | Completo | `GestorCuentas` + `BienvenidaMail` · `S/ApiIntegracion`, `F/FlujosPrincipales` | Depende del SMTP. |
| RF-05 | Cambio obligatorio en primer ingreso | Parcial | Completo | `Middleware\PrimerLogin`, `CambiarPasswordPrimerLogin` · `S/AutenticacionTest` | |
| RF-06 | Bloqueo 15 min tras 3 intentos | Parcial | Completo | `User::registrarIntentoFallido`, `config/auth.php` (configurable) · `S/AutenticacionTest` | |
| RF-07 | Cambiar contraseña en el perfil | Completo | Completo | `profile/update-password-form` · `Auth\PasswordUpdateTest` | |
| RF-08 | 2FA | No aplica | No aplica | — | Trabajo futuro. |
| RF-09 | Login con Google | No aplica | No aplica | — | Trabajo futuro. |

### 3.2 Gestión de usuarios (Módulo 02)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-10 | Alta individual | Parcial | Completo | `Usuarios\Index` (nombres, apellidos, correo, rol) · `UsuariosTest` | |
| RF-11 | Importación masiva | Roto | Completo | `Usuarios\ImportarCSV` + `HojasCalculo\LectorXlsx` · `F/CasosDeUsoTest` | Excel `.xlsx` y CSV (`,` o `;`), sin dependencias nuevas. |
| RF-12 | Validar fila por fila | Parcial | Completo | `ImportarCSV::importar` · `F/CasosDeUsoTest` | Incluye correos duplicados dentro del archivo. |
| RF-13 | Reporte de resultado | Parcial | Completo | `importar-csv.blade.php` | |
| RF-14 | Editar cuenta | Roto | Completo | `Usuarios\Index::guardarEdicion` · `P` | |
| RF-15 | Activar/desactivar | Parcial | Completo | `Usuarios\Index`, `Middleware\CuentaActiva` · `S/AutenticacionTest`, `F/FlujosPrincipales` | |
| RF-16 | Listado con filtros | Parcial | Completo | Filtros por nombre, correo, rol, estado y curso | |
| RF-17 | Sin borrado físico con historial | Roto | Completo | Se retiró el borrado de cuenta del perfil · `ProfileTest` | |

### 3.3 Cursos y clonación (Módulo 03)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-18 | Supervisor crea curso | Parcial | Completo | `CursoPolicy::crear`, `Cursos\Formulario` · `CursosTest` | |
| RF-19 | Profesor, fechas, sesiones, semanas | Completo | Completo | `Cursos\Formulario` | |
| RF-20 | Clonar curso | Parcial | Completo | `Curso::clonar` (módulos, lecciones, tareas, quizzes, esquema) · `F/FlujosPrincipales` | |
| RF-21 | Clonar sin estudiantes/notas/entregas | Completo | Completo | `Curso::clonar` · `F/FlujosPrincipales` | |
| RF-22 | Editar, publicar, despublicar, archivar | Parcial | Completo | `Cursos\Formulario` (curso archivado solo puede reabrirse) | |
| RF-23 | Uno o más profesores | Parcial | Completo | `curso_profesor`, `Curso::esDocente()`, `Cursos\Formulario` · `F/FlujosPrincipalesTest` | Titular + profesores adicionales con los mismos permisos sobre el curso. |
| RF-24 | Cambiar profesor | Completo | Completo | `Admin\Profesores\Index::asignarCurso` (auditado) · `AdminProfesoresTest` | |
| RF-25 | Publicar solo con módulos | Falta | Completo | `Cursos\Formulario::guardar` · `CursosTest` | |

### 3.4 Matrícula (Módulo 04)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-26 | Matrícula manual | Parcial | Completo | `Services\Matricula\GestorMatriculas`, `Cursos\GestionMatricula` · `F/FlujosPrincipales` | Sin autoinscripción. |
| RF-27 | Endpoint de integración | Completo | Completo | `Api\MatriculaController` · `S/ApiIntegracionTest` | |
| RF-28 | Origen de la matrícula | Completo | Completo | `inscripciones.origen` | |
| RF-29 | Baja de estudiante | Parcial | Completo | Supervisor (todos) y Profesor (sus cursos, matrículas manuales) · `F/FlujosPrincipales` | Matriz § 5. |
| RF-30 | Conservar historial | Completo | Completo | Baja lógica · `F/FlujosPrincipales` | |
| RF-31 | Confirmación de baja | Completo | Completo | Modal con motivo (auditado) | |
| RF-32 | API: solo desactivar acceso local | Parcial | Completo | `GestorMatriculas` · `S/ApiIntegracionTest` | |
| RF-33 | Notificación in-app + correo | Parcial | Completo | `MatriculadoEnCurso` (database + mail) · `F/FlujosPrincipales`, `S/ApiIntegracionTest` | |

### 3.5 Contenido (Módulo 05)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-34 | CRUD y orden de módulos | Parcial | Completo | `Cursos\Show` (crear, editar, ordenar, eliminar sin historial) · `F/CasosDeUsoTest` | |
| RF-35 | Lecciones | Roto | Completo | Migración `lecciones`, `Lecciones\Index` · `F/CasosDeUsoTest` | |
| RF-36 | Recursos en varios formatos | Parcial | Completo | Subida con lista blanca (10 MB) · `S/SubidaArchivosTest` | |
| RF-37 | Borrador / Publicado / Programado | Parcial | Completo | `Concerns\TieneEstadoPublicacion` (módulo, tarea, quiz) y `Leccion` · `F/CasosDeUsoTest` | |
| RF-38 | Borrador/Programado invisibles | Parcial | Completo | Scopes de visibilidad y policies · `F/CasosDeUsoTest`, `S/AsistenteIaTest` | |
| RF-39 | Cantidad de sesiones | Completo | Completo | `cursos.sesiones_clase_count` | |

### 3.6 Tareas y quizzes (Módulos 06 y 07)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-40 | Tarea completa | Parcial | Completo | `Tareas\Formulario` (instrucciones, inicio, límite, puntaje, intentos, tipo) | |
| RF-41 | Formatos permitidos | Falta | Completo | `tareas.formatos_permitidos` · `S/SubidaArchivosTest` | |
| RF-42 | Archivo, texto o enlace | Parcial | Completo | `Tareas\Detalle::entregar` · `F/CasosDeUsoTest` | |
| RF-43 | Estado calculado | Parcial | Completo | `Tarea::estadoPara` · `U/Models/TareaTest` | Fuera de plazo = entregó tarde; No entregado = venció sin entregar. |
| RF-44 | Opción única, múltiple, V/F | Parcial | Completo | `Quiz` · `U/Models/QuizTest` | |
| RF-45 | Configuración del quiz | Falta | Completo | Intentos, tiempo (verificado en servidor), aleatorio, retroalimentación, fechas · `F/CasosDeUsoTest` | |
| RF-46 | Resultado automático | Completo | Completo | `Quiz::calcularPuntaje` · `U/Models/QuizTest` | |
| RF-47 | Banco de preguntas | Roto | Completo | Migración, `Quizzes\BancoPreguntas`, importación al quiz | |
| RF-48 | Independientes de la nota final | Parcial | Completo | `CalculadoraNotas` · `F/FlujosPrincipales` | |
| RF-49 | Revisar, comentar, calificar, devolver | Parcial | Completo | `Tareas\Detalle`, `Docente\PorCalificar` · `F/CasosDeUsoTest` | |
| RF-50 | Editar pregunta con intentos | Parcial | Completo | `Quizzes\Formulario` | No permite agregar o quitar preguntas cuando hay intentos. |
| RF-51 | Advertir afectados | Falta | Completo | Modal con cantidad de estudiantes · `F/CasosDeUsoTest` | |
| RF-52 | Recalcular | Falta | Completo | `Quiz::recalcularResultados` (auditado) · `F/CasosDeUsoTest` | |
| RF-53 | Notificar cambio de nota | Falta | Completo | `NotaQuizModificada` · `F/CasosDeUsoTest` | |

### 3.7 Evaluación y calificaciones (Módulo 08)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-54 | Esquema por el Supervisor | Completo | Completo | `Cursos\EsquemaEvaluacion` | |
| RF-55 | Rubro: nombre, semana, peso | Completo | Completo | | |
| RF-56 | Pesos = 100 % | Parcial | Completo | `CalculadoraNotas::pesosSumanCien` (backend) · `U/...CalculadoraNotasTest`, `F/FlujosPrincipales` | |
| RF-57 | Nota 0–20 por rubro | Parcial | Completo | `LibroCalificaciones` (solo titular, sin IDOR) · `S/IdorTest` | |
| RF-58 | Nota final ponderada | Completo | Completo | `CalculadoraNotas::notaFinal` · `U/...CalculadoraNotasTest` | |
| RF-59 | Desglose para el estudiante | Completo | Completo | `Cursos\BoletaEstudiante` · `F/FlujosPrincipales` | |
| RF-60 | Práctica aparte | Parcial | Completo | Pestaña "Tareas y quizzes (práctica)" | |
| RF-61 | Clonar esquema | Completo | Completo | `Curso::clonar` · `F/FlujosPrincipales` | |
| RF-62 | Esquema bloqueado si finalizado | Parcial | Completo | `EsquemaEvaluacion::editable` · `F/FlujosPrincipales` | |
| RF-63 | Listado del profesor | Completo | Completo | Libro + exportación a Excel con formato · `HojasCalculo/ExportacionesTest` | |
| RF-64 | Nota final en rojo | Parcial | Completo | Nota mínima configurable · `F/FlujosPrincipales` | |
| RF-65 | Editar nota con auditoría | Parcial | Completo | Motivo obligatorio al ajustar · `S/AuditoriaTest` | |

### 3.8 Progreso (Módulo 09)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-66 | % de avance | Parcial | Completo | `ProgresoAcademicoService::avance` (lecciones, tareas, quizzes) · `F/CasosDeUsoTest` | |
| RF-67 | Condiciones de completitud | Falta | Completo | `Leccion::CONDICIONES`, `Modulo::CONDICIONES`, `estadoModulos()`, `RecursoLeccionController` · `F/CasosDeUsoTest` | Lección: marcar o abrir recurso. Módulo: actividades, lecciones o aprobación. |
| RF-68 | Progreso al estudiante | Completo | Completo | Panel y Mi progreso | |
| RF-69 | Progreso promedio al profesor | Parcial | Completo | `resumenCurso::progreso_promedio` | |

### 3.9 Asistencia (Módulo 10)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-70 | Registrar asistencia | Roto | Completo | Migraciones, `Asistencia\Index` · `F/CasosDeUsoTest` | |
| RF-71 | % acumulado | Roto | Completo | `CalculadoraAsistencia` · `U/...CalculadoraAsistenciaTest` | |
| RF-72 | Historial del estudiante | Falta | Completo | `Asistencia\MiHistorial` · `F/CasosDeUsoTest` | |
| RF-73 | Asistencia opcional | Parcial | Completo | `cursos.usa_asistencia`, `Asistencia\Index::alternarUsoAsistencia` · `F/CasosDeUsoTest` | La decide el Profesor del curso. |

### 3.10 Grupos (Módulo 11)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-74 | Crear, renombrar, eliminar | Parcial | Completo | `Grupos\Index` · `F/CasosDeUsoTest` | |
| RF-75 | Agregar y mover | Completo | Completo | Solo matriculados · `F/CasosDeUsoTest` | |
| RF-76 | Tarea grupal a un grupo | Parcial | Completo | `tareas.grupo_asignado_id` | |
| RF-77 | Trabajos por grupo | Parcial | Completo | Entregas agrupadas en `Tareas\Detalle` | |

### 3.11 Calendario (Módulo 12)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-78 | Tareas, quizzes, evaluaciones, sesiones | Parcial | Completo | `EventoCalendario\Index` · `F/CasosDeUsoTest`, `CalendarioPorRolTest` | Color por curso, filtros por curso y tipo. |
| RF-79 | Calendario por usuario | Parcial | Completo | Agenda por rol · `CalendarioPorRolTest` | Supervisor: panorama institucional. |

### 3.12 Mensajería (Módulo 13)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-80 | Mensajes privados | Roto | Completo | Migración, `Mensajeria\Bandeja` (contactos del curso) · `S/IdorTest`, `F/CasosDeUsoTest` | |
| RF-81 | Avisos del profesor | Parcial | Completo | Anuncios del curso y aviso masivo del titular | |
| RF-82 | Bandeja con adjuntos | Roto | Completo | Adjuntos con lista blanca | |
| RF-83 | Notificar mensaje nuevo | Roto | Completo | `NuevoMensaje` · `F/CasosDeUsoTest` | |

### 3.13 Notificaciones y comunicados (Módulo 14)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-84 | Notificaciones por eventos | Parcial | Completo | Tarea, quiz, calificación (tarea, rubro, quiz), mensaje, contenido (también el programado: `contenido:notificar-programado`), cambio de fecha (tarea y quiz), comunicado, matrícula · `F/CasosDeUsoTest` | Requiere el scheduler. |
| RF-85 | Contador de no leídas | Completo | Completo | `Notificaciones\Campanita` | |
| RF-86 | Marcar como leídas | Completo | Completo | | |
| RF-87 | Comunicados con fecha y hora | Parcial | Completo | `Admin\Comunicados\Index`, `comunicados:publicar` · `F/CasosDeUsoTest` | Requiere el scheduler (`schedule:run`). |

### 3.14 Reportes y dashboards (Módulo 15)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-88 | Dashboard del profesor | Parcial | Completo | `Reportes\PanelGestion` · `P` | |
| RF-89 | Reportes por curso | Parcial | Completo | `Reportes\Dashboard` + `resumenCurso` · `ReportesTest`, `P` | |
| RF-90 | Dashboard institucional | Parcial | Completo | `Reportes\PanelGestion` (Supervisor) · `P` | Tasa de finalización = aprobados en cursos archivados. |
| RF-91 | Reportes administrativos | Parcial | Completo | `PanelAdmin\Metricas` · `PanelAdminTest`, `P` | Tasa de abandono = bajas / matrículas. |

### 3.15 Asistente de IA (Módulo 16)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-92 | Plan de estudio | Parcial | Completo | Contexto con tareas pendientes, quizzes abiertos, sesiones, evaluaciones y eventos propios · `S/AsistenteIaTest` | Requiere `GROQ_API_KEY` o `ANTHROPIC_API_KEY`; sin clave responde en modo simulado. |
| RF-93 | Explicar, practicar, resumir | Parcial | Completo | `ConstruyeContextoIA` | Igual que RF-92. |
| RF-94 | Solo datos propios | Parcial | Completo | `cursoId` bloqueado, contexto filtrado, reglas anti-inyección · `S/AsistenteIaTest` | |

### 3.16 Auditoría (Módulo 17)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-95 | Ediciones de nota | Parcial | Completo | Rubro, tarea y quiz · `S/AuditoriaTest`, `F/CasosDeUsoTest` | |
| RF-96 | Acciones administrativas | Parcial | Completo | Cuentas, matrículas, bajas, esquema, cursos, profesor, comunicados, configuración, API | |
| RF-97 | Solo Supervisor | Completo | Completo | · `S/AuditoriaTest`, `S/AccesoPorRolTest` | |
| RF-98 | Filtros | Completo | Completo | Fechas, usuario, tipo, curso + CSV · `S/AuditoriaTest` | |
| RF-99 | Inmutable | Parcial | Completo | Modelo + trigger PostgreSQL · `S/AuditoriaTest` | |

### 3.17 Integración externa (Módulo 18)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-100 | Endpoint REST | Completo | Completo | `POST /api/v1/matriculas` · `S/ApiIntegracionTest` | |
| RF-101 | API Key por institución | Completo | Completo | Hash + prefijo | |
| RF-102 | Rechazar y registrar | Completo | Completo | 401 + log | |
| RF-103 | Payload mínimo | Completo | Completo | Curso por id o código; JSON obligatorio (415) | |
| RF-104 | Crear cuenta si no existe | Completo | Completo | Con correo de bienvenida | |
| RF-105 | Fuente de verdad externa | Parcial | Completo | Respeta el bloqueo local; rechaza cuentas no estudiante | |
| RF-106 | Log de solicitudes | Completo | Completo | Con el código final de cada respuesta | |

### 3.18 Configuración general (Módulo 19)

| ID | Requisito | Inicial | Final | Implementación / pruebas | Observación |
|---|---|---|---|---|---|
| RF-107 | Nota mínima editable | Falta | Completo | `PanelAdmin\Configuracion` + `CalculadoraNotas` · `F/FlujosPrincipales` | |
| RF-108 | Parámetros generales | Falta | Completo | `ConfiguracionPlataforma::aplicarZonaHoraria`, macros `formatoFecha`/`formatoFechaHora`/`formatoDiaMes` en `AppServiceProvider` · `ConfiguracionRegionalTest` | Zona horaria aplicada en web, comandos programados y seeders; formato en vistas, notificaciones, IA, calendario y Excel. |

## 2. Requisitos no funcionales

| ID | Requisito | Inicial | Final | Observación |
|---|---|---|---|---|
| RNF-01 | Hash seguro | Completo | Completo | bcrypt · `S/AutenticacionTest` |
| RNF-02 | HTTPS | Parcial | Completo | `URL::forceScheme('https')` en producción; el certificado depende del hosting. |
| RNF-03 | Acceso por rol | Parcial | Completo | Rol por ruta + policies en cada acción · `S/AccesoPorRolTest`, `S/IdorTest` |
| RNF-04 | API Keys revocables | Falta | Completo | `PanelAdmin\Integraciones` · `S/ApiIntegracionTest` |
| RNF-05 | Log de fallos y acciones sensibles | Parcial | Completo | Log de login fallido + bitácora |
| RNF-06 | Buckets privados, enlaces temporales | Falta | Parcial | Se mantiene Cloudinary (URLs públicas no adivinables) por decisión del cliente. Recomendado migrar a Supabase Storage. |
| RNF-07 | Errores claros | Parcial | Completo | Validación visible en todos los formularios; subidas sin error 500 |
| RNF-08 | Estados vacíos | Parcial | Completo | `x-gestion.vacio` en todas las listas de gestión |
| RNF-09 | Confirmación destructiva | Parcial | Completo | `wire:confirm` o modal en eliminar, bajas, desactivar, revocar, clonar |
| RNF-10 | Usable sin capacitación | Parcial | Parcial | Textos de ayuda en formularios; sin prueba con usuarios reales. |
| RNF-11 | Carga ≤ 3 s | Parcial | Parcial | Se redujeron consultas N+1 en métricas; no se midió en producción. |
| RNF-12 | Nota final inmediata | Completo | Completo | Cálculo en lectura |
| RNF-13 | Disponibilidad 99 % | No aplica | No aplica | Infraestructura |
| RNF-14 | Sin cambios retroactivos | Parcial | Completo | Curso archivado bloquea contenido, notas y esquema · `F/FlujosPrincipales` |
| RNF-15 | Nuevas instituciones sin cambios | Completo | Completo | |
| RNF-16 | Múltiples cursos | Completo | Completo | |
| RNF-17 | Código por módulos | Completo | Completo | Servicios por módulo en `app/Services/<Modulo>` |
| RNF-18 | Reglas críticas en backend | Parcial | Completo | Pesos, matrícula, visibilidad, finalizado, intentos y tiempo en el servidor |
| RNF-19 | Navegadores modernos | Completo | Completo | |
| RNF-20 | Responsivo | Parcial | Completo | Tablas con desplazamiento horizontal; revisado a 500 px |
| RNF-21 | Payload JSON | Completo | Completo | 415 si no es JSON |

## 3. Casos de uso

| CU | Descripción | Prueba |
|---|---|---|
| CU-01 | Iniciar sesión y cambiar contraseña inicial | `S/AutenticacionTest`, `F/FlujosPrincipales` |
| CU-02 | Cursos matriculados y progreso | `F/FlujosPrincipales`, `P` |
| CU-03 | Consumir contenido | `F/CasosDeUsoTest` |
| CU-04 | Entregar tarea | `F/CasosDeUsoTest`, `S/SubidaArchivosTest` |
| CU-05 | Rendir quiz | `F/CasosDeUsoTest`, `QuizzesTest` |
| CU-06 | Desglose de calificaciones | `F/FlujosPrincipales` |
| CU-07 | Consultar asistencia | `F/CasosDeUsoTest` |
| CU-08 | Mensajes | `F/CasosDeUsoTest`, `S/IdorTest` |
| CU-09 | Calendario | `F/CasosDeUsoTest` |
| CU-10 | Asistente de IA | `S/AsistenteIaTest`, `BotFaqTest` |
| CU-11 | Módulos y lecciones | `F/CasosDeUsoTest`, `CursosTest` |
| CU-12 | Crear tareas y quizzes | `TareasTest`, `QuizzesTest` |
| CU-13 | Calificar y registrar notas | `F/FlujosPrincipales`, `F/CasosDeUsoTest` |
| CU-14 | Corregir pregunta y recalcular | `F/CasosDeUsoTest` |
| CU-15 | Reportes del curso | `ReportesTest`, `P` |
| CU-16 | Grupos | `F/CasosDeUsoTest`, `GruposTest` |
| CU-17 | Asistencia | `F/CasosDeUsoTest` |
| CU-18 | Alta y baja de cuentas (individual y masiva) | `F/FlujosPrincipales`, `F/CasosDeUsoTest` |
| CU-19 | Matricular / dar de baja | `F/FlujosPrincipales` |
| CU-20 | Crear curso y esquema | `CursosTest`, `F/FlujosPrincipales` |
| CU-21 | Clonar curso | `F/FlujosPrincipales` |
| CU-22 | Comunicados | `F/CasosDeUsoTest`, `AdminProfesoresTest` |
| CU-23 | Dashboard institucional | `PanelAdminTest`, `P` |
| CU-24 | Bitácora de auditoría | `S/AuditoriaTest` |
| CU-25 | Nota mínima e integración | `F/FlujosPrincipales`, `S/ApiIntegracionTest` |

## 4. Auditoría de procesos y flujos (segundo pedido, 26/09/2026)

El pedido numera los RF como una versión anterior del ERS (con RF-52 y RF-61 duplicados). La columna **ERS v2** da la
equivalencia con el documento oficial, que es el que se usa en el resto de esta matriz.

- **Antes**: estado al empezar este pedido.
- **Después**: estado al terminar.
- **Evidencia**: archivos principales y pruebas (`S/` = `tests/Feature/Seguridad`, `F/` = `tests/Feature/Flujos`).

| Pedido | ERS v2 | Requisito | Antes | Después | Evidencia |
|---|---|---|---|---|---|
| RF-01 | RF-01 | Login por correo, 3 roles | Cumple | Cumple | `LoginForm` · `Auth/AuthenticationTest` |
| RF-02 | RF-02 | Recuperación de contraseña | Cumple | Cumple | Volt `forgot-password` / `reset-password` · `S/AutenticacionTest` |
| RF-03 | RF-03 | Contraseña temporal | Cumple | Cumple | `GestorCuentas` · `F/FlujosPrincipalesTest` |
| RF-04 | RF-04 | Correo de bienvenida con temporal y enlace | Cumple | Cumple | `BienvenidaMail` |
| RF-05 | RF-05 | Cambio obligatorio en primer login | Cumple | Cumple | `PrimerLogin` · `S/AutenticacionTest` |
| RF-06 | RF-06 | 3 intentos y exigir recuperación | Parcial | Cumple | La recuperación desbloquea · `S/AutenticacionTest::test_recuperar_la_contrasena_desbloquea_la_cuenta` |
| RF-07 | RF-07 | Cambio libre de contraseña | Cumple | Cumple | `PasswordUpdateTest` |
| RF-08 | RF-06 | Bloqueo temporal | Cumple | Cumple | 15 min configurable · `S/AutenticacionTest` |
| RF-09/10 | RF-08/09 | 2FA, Google | No aplica | No aplica | Futuro (no implementar) |
| RF-11 | RF-10 | Crear cuentas individuales | Cumple | Cumple | `Usuarios\Index` |
| RF-12 | RF-11 | Importación CSV/Excel | Parcial | Cumple | `LectorXlsx` · `F/CasosDeUsoTest::test_la_importacion_acepta_un_libro_de_excel` |
| RF-13 | RF-12 | Validación fila por fila | Cumple | Cumple | `F/CasosDeUsoTest` |
| RF-14 | RF-13 | Reporte de importación | Cumple | Cumple | `importar-csv.blade.php` |
| RF-15 | RF-14 | Editar cuenta | Cumple | Cumple | `Usuarios\Index` |
| RF-16 | RF-15 | Activar/desactivar | Cumple | Cumple | `F/FlujosPrincipalesTest` |
| RF-17 | RF-16 | Listado con filtros (incluye curso) | Cumple | Cumple | Filtro por curso incluye profesores adicionales |
| RF-18 | RF-17 | Sin borrado físico | Cumple | Cumple | `ProfileTest` |
| RF-19 | RF-18 | Curso como plantilla | Cumple | Cumple | Decisión: Curso = Oferta; la clonación es la plantilla (ver AUDITORIA § 12.5) |
| RF-20 | RF-19 | Oferta: profesor, fechas, sesiones y semanas independientes | Cumple | Cumple | `Cursos\Formulario` |
| RF-21 | RF-20 | Clonar con contenido y esquema | Cumple | Cumple | + banco de preguntas · `F/FlujosPrincipalesTest::test_flujo_de_clonacion_de_curso` |
| RF-22 | RF-21 | Clonar sin estudiantes, notas ni entregas | Cumple | Cumple | ídem |
| RF-23 | RF-22 | Editar/publicar/despublicar/archivar | Cumple | Cumple | `Cursos\Formulario` |
| RF-24 | RF-23 | Uno o más profesores | Parcial | Cumple | `curso_profesor` · `F/FlujosPrincipalesTest::test_un_profesor_adicional_gestiona_y_califica_el_curso` |
| RF-25 | RF-24 | Cambiar profesor | Cumple | Cumple | `Admin\Profesores\Index` (sucesión del titular) · `AdminProfesoresTest` |
| RF-26 | RF-25 | Al menos un módulo para publicar | Cumple | Cumple | `CursosTest` |
| RF-27 | RF-26 | Matrícula manual (Supervisor) | Cumple | Cumple | `F/FlujosPrincipalesTest::test_el_profesor_no_puede_matricular` |
| RF-28 | RF-27 | Endpoint de integración | Cumple | Cumple | `S/ApiIntegracionTest` |
| RF-29 | RF-28 | Origen manual/API | Cumple | Cumple | `inscripciones.origen` |
| RF-30 | RF-29 | Dar de baja (Supervisor + Profesor propio) | Parcial | Cumple | `F/FlujosPrincipalesTest::test_el_profesor_da_de_baja_en_su_propio_curso_con_historial_y_auditoria` |
| RF-31 | RF-30 | La baja conserva historial | Cumple | Cumple | Baja lógica · ídem |
| RF-32 | RF-31 | Confirmación de baja | Cumple | Cumple | Modal con motivo |
| RF-33 | RF-32 | API: solo desactivar acceso local | Cumple | Cumple | `F/FlujosPrincipalesTest::test_el_profesor_no_desactiva_una_matricula_que_llego_por_api` |
| RF-34 | RF-33 | Notificación in-app + correo | Cumple | Cumple | `MatriculadoEnCurso` |
| RF-35 | RF-34 | CRUD y orden de módulos | Cumple | Cumple | Solo el Profesor del curso (matriz § 5) |
| RF-36 | RF-35 | Lecciones | Cumple | Cumple | `Lecciones\Index` |
| RF-37 | RF-36 | Recursos multiformato | Cumple | Cumple | `config/archivos.php` |
| RF-38 | RF-37 | Borrador/Publicado/Programado (Examen = Quiz) | Cumple | Cumple | Decisión en AUDITORIA § 12.5 |
| RF-39 | RF-38 | Borrador/Programado invisibles | Cumple | Cumple | + aviso al publicarse lo programado · `F/CasosDeUsoTest` |
| RF-40 | RF-39 | Sesiones independientes | Cumple | Cumple | `cursos.sesiones_clase_count` |
| RF-41 | RF-40 | Tareas completas | Cumple | Cumple | `Tareas\Formulario` |
| RF-42 | RF-41 | Formatos permitidos | Cumple | Cumple | `S/SubidaArchivosTest` |
| RF-43 | RF-42 | Archivo, texto o enlace | Cumple | Cumple | `F/CasosDeUsoTest` |
| RF-44 | RF-43 | Estado automático | Cumple | Cumple | `U/Models/TareaTest` |
| RF-45 | RF-44 | Tipos de pregunta | Cumple | Cumple | `U/Models/QuizTest` |
| RF-46 | RF-45 | Configuración del quiz | Cumple | Cumple | `F/CasosDeUsoTest` |
| RF-47 | RF-46 | Resultado automático | Cumple | Cumple | `Quiz::calcularPuntaje` |
| RF-48 | RF-47 | Banco por tema, reutilizable | Cumple | Cumple | + se copia al clonar |
| RF-49 | RF-48 | Notas de práctica independientes | Cumple | Cumple | `CalculadoraNotas` |
| RF-50 | RF-49 | Revisar, comentar, calificar, devolver | Cumple | Cumple | Entregas por calificar primero |
| (sin ID) | RF-50 a RF-53 | Corregir pregunta, advertir, recalcular, notificar | Cumple | Cumple | `F/CasosDeUsoTest::test_corregir_una_pregunta_y_recalcular_actualiza_notas_audita_y_notifica` |
| RF-51 | RF-54 | Esquema por el Supervisor | Cumple | Cumple | `Cursos\EsquemaEvaluacion` |
| RF-52 (1.ª) | RF-55 | Rubro: nombre, semana, peso | Cumple | Cumple | |
| RF-52 (2.ª) | RF-107 | Nota mínima editable | Cumple | Cumple | `PanelAdmin\Configuracion` |
| (sin ID) | RF-108 | Zona horaria y formato de fecha | Parcial | Cumple | Aplicados en toda la plataforma · `ConfiguracionRegionalTest` (4 pruebas) |
| RF-53 | RF-56 | Pesos = 100 % | Cumple | Cumple | `U/.../CalculadoraNotasTest` |
| RF-54 | RF-57 | Nota 0–20 por rubro (Profesor) | Cumple | Cumple | Solo el Profesor del curso |
| RF-55 | RF-58 | Nota final ponderada | Cumple | Cumple | + condición "En curso" mientras falten rubros · `CalculadoraNotasTest` |
| RF-56 | RF-59 | Desglose al estudiante | Cumple | Cumple | `BoletaEstudiante` |
| RF-57 | RF-60 | Práctica separada | Cumple | Cumple | Hoja "Práctica" del Excel |
| RF-58 | RF-61 | Esquema clonado editable | Cumple | Cumple | |
| RF-59 | RF-62 | Esquema bloqueado si finalizado | Cumple | Cumple | |
| RF-60 | RF-63 | Listado del profesor | Cumple | Cumple | + Excel con formato |
| RF-61 (1.ª) | RF-64 | Rojo bajo la mínima | Cumple | Cumple | Solo con esquema completo (antes marcaba a todos a mitad de periodo) |
| (sin ID) | RF-65 | Ajuste auditado | Cumple | Cumple | `S/AuditoriaTest` |
| RF-61 (2.ª) | RF-66 | % de avance | Cumple | Cumple | `ProgresoAcademicoService::avance` |
| RF-62 | RF-67 | Condiciones de completitud | Parcial | Cumple | `F/CasosDeUsoTest` (4 pruebas) |
| RF-63 | RF-68 | Progreso del estudiante | Cumple | Cumple | Panel corregido (próxima entrega futura) |
| RF-64 | RF-69 | Progreso promedio | Cumple | Cumple | |
| RF-65 | RF-70 | Registrar asistencia | Cumple | Cumple | Solo el Profesor; el Supervisor consulta |
| RF-66 | RF-71 | % acumulado | Cumple | Cumple | `U/.../CalculadoraAsistenciaTest` |
| RF-67 | RF-72 | Historial del estudiante | Cumple | Cumple | `Asistencia\MiHistorial` |
| RF-68 | RF-73 | Omitible si el Profesor no lo requiere | Parcial | Cumple | `F/CasosDeUsoTest::test_el_profesor_omite_y_reactiva_la_asistencia_y_el_supervisor_solo_consulta` |
| RF-69 a RF-72 | RF-74 a RF-77 | Grupos | Cumple | Cumple | `GruposTest`, `F/CasosDeUsoTest` |
| RF-73 | RF-78 | Calendario del curso | Cumple | Cumple | Rediseño · `CalendarioPorRolTest` |
| RF-74 | RF-79 | Vista consolidada por usuario | Cumple | Cumple | Agenda por rol · `CalendarioPorRolTest` |
| RF-75 a RF-78 | RF-80 a RF-83 | Mensajería | Cumple | Cumple | Incluye profesores adicionales |
| RF-79 | RF-84 | Notificaciones por evento | Parcial | Cumple | Contenido programado y cambio de fecha de quiz · `F/CasosDeUsoTest` |
| RF-80 a RF-82 | RF-85 a RF-87 | Contador, leídas, comunicados | Cumple | Cumple | |
| RF-83 | RF-88 | Dashboard del Profesor | Cumple | Cumple | Solo cursos en marcha |
| RF-84 | RF-89 | Reportes por curso | Cumple | Cumple | Criterio de bajo rendimiento corregido · `F/CasosDeUsoTest` |
| RF-85 | RF-90 | Dashboard del Supervisor | Cumple | Cumple | |
| RF-86 | RF-91 | Reportes administrativos | Cumple | Cumple | Atraso solo de cursos en marcha |
| RF-87 | RF-92 | Plan de estudio con calendario | Parcial | Cumple | `S/AsistenteIaTest::test_el_contexto_incluye_el_calendario_propio_del_estudiante` |
| RF-88 | RF-93 | Explicar, practicar, resumir | Cumple | Cumple | |
| RF-89 | RF-94 | Solo datos del curso propio | Cumple | Cumple | `S/AsistenteIaTest` |
| RF-97 + sin ID | RF-95 a RF-99 | Auditoría | Cumple | Cumple | + exportación a Excel · `S/AuditoriaTest`, `HojasCalculo/ExportacionesTest` |
| RF-90 a RF-96 | RF-100 a RF-106 | Integración API | Cumple | Cumple | `S/ApiIntegracionTest` |

## 5. Extensiones fuera del ERS

Funciones agregadas a pedido que no corresponden a ningún RF del ERS v2. No cambian el estado de los RF anteriores.

| Extensión | Origen | Implementación / pruebas | Observación |
|---|---|---|---|
| Debates del curso | Pedido del 26/10/2026, inspirado en la pestaña Debates de Blackboard UPN | `Debates\Index`, `Debates\Show`, `DebatePolicy`, `NuevoDebatePublicado`; tablas `debates` y `debate_comentarios` · `DebatesTest` | Profesor del curso: crea, edita, cierra/reabre, elimina y modera. Estudiante matriculado: comenta y responde (un nivel). Supervisor: solo lectura. Avisa al publicar (patrón RF-84). |
| Contenido de la semana en secciones fijas | Pedido del 26/10/2026 (mejora de RF-35, RF-36 y RF-67; inspirado en Blackboard UPN) | `Cursos\Show` + `Cursos\Concerns\GestionaContenidoSemanal`, `Leccion::SECCIONES`, `Leccion::seccionDe`, `Leccion::tipoRecurso` · `LeccionesFormularioTest` | Se gestiona en la misma pantalla del curso: acordeón por semana con Mensaje de inicio (uno por semana), Recursos de aprendizaje, Actividades complementarias y Grabación de la clase. Las lecciones antiguas se leen en su sección equivalente; "Foro de preguntas" se conserva, pero ya no se ofrece. |
| Apartado "Actividades" del Profesor | Pedido del 27/10/2026 (agrupa RF-39 a RF-47 sin cambiarlos) | `Cursos\Actividades` (ruta `cursos.actividades`), `livewire/cursos/actividades.blade.php` · `ActividadesTest` | Un botón "Actividades" en el curso reemplaza a Tareas, Quizzes y Banco de preguntas. Pestaña Actividades: lista unificada de tareas y quizzes con filtro, búsqueda, paginación y menú "Crear actividad" (reutiliza los formularios existentes). Pestaña Banco de preguntas. Las rutas antiguas siguen activas para el Estudiante y como compatibilidad. |
| Carga masiva del banco de preguntas | Pedido del 27/10/2026 (extensión de RF-47; patrón de RF-11 a RF-13) | `Services\Evaluacion\ImportadorBancoPreguntas`, `Quizzes\BancoPreguntas`; columna `banco_preguntas.puntaje` (nullable) · `BancoPreguntasImportacionTest` | Plantilla .xlsx con instrucciones; .xlsx o .csv (máx. 2 MB y 500 filas); vista previa; validación fila por fila con reporte descargable; duplicados (mismo tema y enunciado) omitidos. El banco además muestra "usada en N quizzes" (el quiz guarda una copia), filtros por tema y tipo, y eliminación en lote. |
