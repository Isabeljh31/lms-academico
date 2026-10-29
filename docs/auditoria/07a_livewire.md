# 07a. Componentes Livewire (parte 1 de 2)

Alcance: los 41 archivos de `app/Livewire` (39 componentes de clase, la acción `Actions/Logout` y el form object `Forms/LoginForm`). Esta parte cubre las carpetas `Actions`, `Admin`, `Asistencia`, `Auth`, `BotSugerencias`, `Cursos`, `Docente`, `Estudiante`, `EventoCalendario` y `Forms`. La parte 07b cubre `Grupos`, `Lecciones`, `Mensajeria`, `Notificaciones`, `PanelAdmin`, `Quizzes`, `Reportes`, `Tareas`, `Usuarios` y los 7 componentes Volt.

Método: lectura completa de cada clase (volcado con números de línea del 13/10/2026; ningún archivo de `app/` cambió después, verificado con `find -newer`). Las pruebas que cubren cada componente se obtuvieron buscando `use App\Livewire\...` en `tests/` y, para los que no se importan, las rutas que los montan. "Hecho" = verificado en el código o por ejecución. "Opinión" = juicio del auditor.

## 1. Convenciones comunes (hechos)

- Todos los componentes de página usan `#[Layout('layouts.app')]`, salvo `CambiarPasswordPrimerLogin` (`layouts.guest`) y los embebidos (`PanelInicio`, `PanelGestion`, `Widget`, `Campanita`), que no tienen layout.
- La autorización se hace en `mount()` y se repite en cada acción. Hay dos estilos:
  - Policies (`Gate::authorize`/`Gate::allows`) en `Cursos`, `Tareas`, `Quizzes`, `Grupos`, `Lecciones`, `Asistencia`, `Usuarios`.
  - `abort_unless(hasRole('supervisor'))` en `Admin/*` y `PanelAdmin/*`.
- Los ids que el servidor fija (modales, sesión activa) usan `#[Locked]`. Los ids que el usuario elige (selectores) son públicos y se revalidan en el servidor.
- La vista de cada componente es `resources/views/livewire/<carpeta>/<nombre-kebab>.blade.php`; las 40 vistas referenciadas existen.

## 2. Resumen de los 41 archivos

| Carpeta | Componente | Líneas | Pruebas que lo importan | Problemas (§) |
|---|---|---|---|---|
| Actions | Logout | 20 | `AuthenticationTest` (por ruta `logout`) | — |
| Admin | Comunicados\Index | 127 | AdminProfesores, CasosDeUsoPrincipales, CasosDeUso, ValidacionEntradas | 3.1 |
| Admin | Metricas\Index | 187 | AnaliticaRiesgo | 3.2 |
| Admin | Profesores\Index | 221 | AdminProfesores | 3.3 |
| Asistencia | Index | 262 | ClasesEnVivo, CasosDeUso, RolesYFuncionalidad, Idor | 4.1 |
| Asistencia | MiHistorial | 38 | Ninguna por clase; ruta en CasosDeUso:430, RevisionPantallas, AccesoPorRol | — |
| Auth | CambiarPasswordPrimerLogin | 69 | FlujosPrincipales, RolesYFuncionalidad, Autenticacion | — |
| BotSugerencias | Bandeja | 153 | PropuestaBot | 6.1 |
| BotSugerencias | ResumenSemanal | 40 | Ninguna por clase; ruta en RevisionPantallas, AccesoPorRol | 6.2 |
| BotSugerencias | Widget | 95 | BotFaq, PanelAdmin, PulidoEstudiante, AsistenteIa | 6.3 |
| Cursos | BoletaEstudiante | 70 | FlujosPrincipales | 7.1 |
| Cursos | EsquemaEvaluacion | 163 | FlujosPrincipales, Idor | 7.2 |
| Cursos | Formulario | 227 | AdminProfesores, ContenidoPorSemanas, Cursos, FlujosPrincipales | 7.3 |
| Cursos | GestionMatricula | 164 | FlujosPrincipales, Idor | 7.4 |
| Cursos | Index | 65 | Cursos, ValidacionEntradas | — |
| Cursos | LibroCalificaciones | 349 | BlackboardDocenteAlumno, FlujosPrincipales, Exportaciones, Auditoria, Idor | 7.6 |
| Cursos | Show | 601 | 11 archivos (ver § 7.7) | 7.7 |
| Docente | PorCalificar\Index | 143 | BlackboardDocenteAlumno, CasosDeUsoPrincipales, Idor | 8.1 |
| Estudiante | PanelInicio | 97 | PanelInicioEstudiante, PulidoEstudiante | 9.1 |
| EventoCalendario | Index | 468 | CalendarioNotificaciones, CalendarioPorRol, ClasesEnVivo, CasosDeUso, PropuestaBot | 10.1 |
| Forms | LoginForm | 79 | Ninguna por clase; `AuthenticationTest`, `AutenticacionTest` vía Volt `pages.auth.login` | 11.1 |
| Grupos | Index | 191 | CasosDeUsoPrincipales, CasosDeUso, Grupos | 07b |
| Lecciones | Index | 301 | CasosDeUsoPrincipales, CasosDeUso, RecursosLeccion, ArchivosPrivados, Idor | 07b |
| Mensajeria | Bandeja | 191 | CasosDeUsoPrincipales, CasosDeUso, RolesYFuncionalidad, Idor | 07b |
| Notificaciones | Campanita | 35 | Ninguna por clase; `CasosDeUsoPrincipalesTest` lee `notifications()` | 07b |
| PanelAdmin | Auditoria | 157 | Exportaciones, Auditoria | 07b |
| PanelAdmin | Configuracion | 119 | ConfiguracionRegional, FlujosPrincipales, PanelAdmin | 07b |
| PanelAdmin | Integraciones | 131 | ApiIntegracion | 07b |
| PanelAdmin | Metricas | 140 | PanelAdmin | 07b |
| Quizzes | BancoPreguntas | 188 | Ninguna por clase; solo carga de la ruta (RevisionPantallas, AccesoPorRol) | 07b |
| Quizzes | Estadisticas | 42 | Ninguna por clase; ruta en CasosDeUsoPrincipales, Quizzes, RevisionPantallas | 07b |
| Quizzes | Formulario | 412 | CasosDeUsoPrincipales, CasosDeUso, Quizzes | 07b |
| Quizzes | Index | 80 | ListadosEstudiante, Quizzes | 07b |
| Quizzes | Resolver | 151 | CasosDeUsoPrincipales, CasosDeUso, Quizzes | 07b |
| Reportes | Dashboard | 64 | Reportes | 07b |
| Reportes | PanelGestion | 119 | Ninguna por clase; carga de `dashboard` (Example, RolesYFuncionalidad) | 07b |
| Tareas | Detalle | 287 | 9 archivos (ver 07b) | 07b |
| Tareas | Formulario | 155 | CalendarioNotificaciones, CasosDeUsoPrincipales, Tareas | 07b |
| Tareas | Index | 86 | ListadosEstudiante, Tareas | 07b |
| Usuarios | ImportarCSV | 268 | CasosDeUso | 07b |
| Usuarios | Index | 273 | FlujosPrincipales, ValidacionEntradas, Usuarios | 07b |

Componentes sin ninguna prueba de sus acciones (solo se prueba que la página carga, o nada): `MiHistorial`, `ResumenSemanal`, `LoginForm` (cubierto indirectamente), `Campanita`, `BancoPreguntas` (guardar, editar y eliminar sin prueba), `Estadisticas`, `PanelGestion`.

## 3. Carpeta `Admin`

### 3.1 Admin\Comunicados\Index (127 líneas)
- Propósito: comunicados institucionales del Supervisor (RF-87, CU-22). Ruta `admin.comunicados.index`. Vista `livewire/admin/comunicados/index`.
- Propiedades públicas: `titulo`, `contenido`, `prioridad` (informativo o urgente), `destinatario` (profesores, estudiantes o todos), `publicarEn`, `mostrarFormulario`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `hasRole('supervisor')` (línea 38) |
| `crearComunicado` | Crea el comunicado; si ya está publicado notifica a los destinatarios y marca `notificado`; registra en bitácora | título ≤255, contenido ≤5000, prioridad y destinatario en lista, `publicarEn` futura (líneas 45-55) | `hasRole` (43) |
| `toggleActivo` | Activa o desactiva; si queda publicado y no notificado, notifica | id con `whereNull('curso_id')` (89) | `hasRole` (87) |
| `eliminar` | Borra físicamente y registra en bitácora | id institucional (103) | `hasRole` (101) |

- Problemas:
  - Hecho: `toggleActivo` no deja registro en la bitácora (líneas 85-97), a diferencia de crear y eliminar. El ERS (RF-96) no lo exige.
  - Hecho: el borrado es físico (`delete`, línea 104). Las notificaciones ya enviadas siguen apuntando a un comunicado inexistente (no verificado cómo lo muestra la campanita).
  - Hecho: `notificar` usa `Comunicado::destinatarios()`, que carga en memoria a todos los usuarios activos del rol (archivo 05) y envía en línea (síncrono, 11 de 12 notificaciones no usan cola). Con miles de usuarios la petición puede agotar el tiempo (opinión).

### 3.2 Admin\Metricas\Index (187 líneas): analítica de riesgo
- Propósito: riesgo de abandono y engagement (ERS 11.3). Ruta `admin.analitica-riesgo`. Vista `livewire/admin/metricas/index`.
- Propiedades públicas: ninguna. Constantes `ASISTENCIA_MINIMA=50`, `DIAS_ACTIVIDAD_RECIENTE=7`.
- Métodos públicos: `mount` (`hasRole('supervisor')`, línea 44) y `render`. Toda la lógica está en `render` y en dos métodos protegidos.
- Regla (hecho, líneas 63-110): un curso es crítico si la asistencia es < 50 %, el rendimiento a la fecha < nota mínima o hay ≥ 2 tareas vencidas sin entregar; nivel Alto si hay más de un curso crítico. Los cursos archivados se omiten (línea 72).
- Rendimiento: usa `CalculadoraAsistencia::porcentajesDeInscripciones` y `CalculadoraNotas::rendimientosDeInscripciones` (consultas por lote, sin N+1).
- Problemas:
  - Hecho: `tareasVencidasSinEntregar` excluye todas las tareas grupales asignadas a un grupo (`whereNull('grupo_asignado_id')`, línea 166). Un estudiante que no entrega tareas de su grupo no suma atraso.
  - Hecho: carga a **todos** los estudiantes activos con sus matrículas y todas las entregas de las tareas vencidas (líneas 51-54, 170-173) sin paginar. Escala lineal con la institución (opinión: aceptable para el tamaño previsto; no verificado con volumen real).
  - Hecho: la "última actividad" de entregas usa `max(updated_at)` (línea 138): una calificación del profesor cuenta como actividad del estudiante.

### 3.3 Admin\Profesores\Index (221 líneas)
- Propósito: directorio de profesores, alta y asignación de cursos (RF-10, RF-23, RF-24). Ruta `admin.profesores.index`.
- Propiedades públicas: `busqueda`, `mostrarModalCrear`, `nuevoNombre`, `nuevoEmail`, `mostrarModalAsignar`, `profesorSeleccionadoId` (`#[Locked]`), `cursoAAsignarId`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `crearProfesor` | Crea la cuenta con `GestorCuentas` (rol profesor) | nombre, correo único | `hasRole` |
| `abrirModalAsignar` | Fija el profesor (debe tener rol profesor) | `findOrFail` con rol | `hasRole` |
| `asignarCurso` | Cambia el titular del curso; si era adicional lo quita de adicionales; bitácora | curso y usuario existen; curso no archivado (101) | `hasRole` |
| `desasignarCurso` | Retira al titular; el primer adicional pasa a titular o el curso queda a nombre del Supervisor | curso no archivado (139) | `hasRole` |

- Problemas:
  - Hecho: la contraseña temporal se muestra en un mensaje *flash* de sesión (línea 71). Con `SESSION_DRIVER=database` queda en texto plano en la tabla `sessions` hasta la siguiente petición (ver archivo 13).
  - Hecho: `render` carga `cursosComoProfesor.tareas.entregas` completo y hace una consulta de conteo de estudiantes por profesor (líneas 180, 193-196): N+1 acotado a 8 profesores por página, pero trae todas las entregas de esos cursos a memoria.
  - Hecho: las métricas solo consideran cursos donde el usuario es titular (`cursosComoProfesor`), no los adicionales (RF-23).
  - Hecho: el caso "curso a nombre del Supervisor" deja el curso sin nadie que pueda gestionarlo (archivo 06, § 6). Está avisado en pantalla (línea 170).

## 4. Carpeta `Asistencia`

### 4.1 Asistencia\Index (262 líneas)
- Propósito: sesiones de clase, pase de lista y resumen (RF-70, RF-71, RF-73) y enlace de videollamada (ERS 11.2). Ruta `asistencia.index`.
- Propiedades públicas: `vista`, `sesionActivaId` (`#[Locked]`), `marcas`, `mostrarFormSesion`, `sesionTitulo`, `sesionFecha`, `sesionDuracion`, `sesionDescripcion`, `sesionEnlace`, `enlaceSesionId`, `enlaceEdicion`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `supervisar` (62) |
| `alternarUsoAsistencia` | Activa o desactiva `usa_asistencia` (RF-73) | — | `gestionar` y no archivado (77, 86) |
| `editarEnlace` / `guardarEnlace` | Cambia el enlace de una sesión | `url:https`, ≤500 | `autorizarEdicion` |
| `crearSesion` | Crea la sesión con número correlativo | título, fecha, duración 15-480, enlace `https` | `autorizarEdicion` |
| `eliminarSesion` | Borra si no tiene asistencias | — | `autorizarEdicion` |
| `abrirPaseLista` | Carga las marcas de los matriculados activos | — | `supervisar` (172) |
| `marcarTodos` | Marca a todos con un estado | estado en lista (186) | ninguna propia (solo cambia el estado local) |
| `guardarAsistencia` | `updateOrCreate` por estudiante, en transacción, ignorando ids no matriculados | `marcas.*` en lista | `autorizarEdicion` |

- Problemas:
  - Hecho: `guardarAsistencia` no deja registro en la bitácora (líneas 196-223). Cambiar un "ausente" ya registrado no deja rastro. El ERS no lo exige (RF-95 y RF-96 no incluyen asistencia), pero la asistencia alimenta la analítica de riesgo (opinión: conviene auditarla).
  - Hecho: no se compara la cantidad de sesiones creadas con `cursos.sesiones_clase_count` (dato del ERS, sección 6). Es solo informativo.
  - Hecho: `alternarUsoAsistencia` tampoco se audita, aunque el Supervisor también puede cambiar ese dato desde `Cursos\Formulario` (que sí se audita).

### 4.2 Asistencia\MiHistorial (38 líneas)
- Propósito: historial propio de asistencia del estudiante (RF-72). Ruta `cursos.mi-asistencia` con `role:estudiante`.
- Autorización: `hasRole('estudiante')` y `CursoPolicy::ver` (líneas 22-23). Solo consulta datos de `auth()->user()`.
- Pruebas: `CasosDeUsoTest.php:430` (carga de la ruta) y `RevisionPantallasTest`. Ninguna verifica el porcentaje mostrado.
- Problemas: ninguno encontrado.

## 5. Carpeta `Auth`

### 5.1 Auth\CambiarPasswordPrimerLogin (69 líneas)
- Propósito: cambio obligatorio de contraseña en el primer ingreso (RF-05, regla 2). Ruta `primer-login`.
- Propiedades: `password`, `password_confirmation`.
- `mount`: si `primer_login` es falso, redirige al panel.
- `guardar`: exige `primer_login` (403 si no); valida mínimo 8 con letras y números, confirmada; rechaza la misma contraseña temporal (`Hash::check`); guarda con `forceFill`; registra en bitácora (`cambio_password_inicial`).
- Pruebas: `FlujosPrincipalesTest`, `RolesYFuncionalidadTest`, `AutenticacionTest`.
- Problemas:
  - Hecho: no regenera el id de sesión tras el cambio de contraseña ni invalida otras sesiones abiertas con la contraseña temporal (líneas 48-61). Riesgo bajo (opinión).
  - Hecho: la política de contraseña (8, letras y números) es más débil que la de `Password::defaults()` si se configurara; no hay `uncompromised()`. El ERS no fija una política (ver archivo 13).

## 6. Carpeta `BotSugerencias`

### 6.1 BotSugerencias\Bandeja (153 líneas)
- Propósito: bandeja *human-in-the-loop* de propuestas del asistente (RF-92). Ruta `bot.propuestas` con `role:estudiante`.
- Propiedades: `fechasAjustadas` (fecha del recordatorio por propuesta).

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | Precarga la fecha propuesta de cada pendiente | — | ninguna en `mount`; la ruta exige `role:estudiante` |
| `aceptar` | Guarda la fecha ajustada, marca aprobado y llama a `PropuestaBotService::ejecutarPropuestaAprobada`, que crea el evento | fecha `date` | consulta con `usuario_id = auth()->id()` y `PropuestaBotPolicy::responder` |
| `rechazar` | Marca rechazado | — | igual |

- Pruebas: `PropuestaBotTest`.
- Problemas:
  - Hecho: `aceptar` guarda el estado `aprobado` (línea 63) y después crea el evento (línea 65) sin transacción. Si la creación falla, la propuesta queda aprobada sin evento y no se puede volver a responder (la policy exige estado pendiente).
  - Hecho: la fecha ajustada acepta fechas pasadas (`nullable|date`, línea 49).

### 6.2 BotSugerencias\ResumenSemanal (40 líneas)
- Propósito: el profesor ve las propuestas de la última semana de los estudiantes de sus cursos. Ruta `bot.resumen` con `role:profesor`.
- No tiene `mount` ni autorización propia: depende solo del middleware de la ruta (hecho, archivo completo). Los datos se limitan a `cursosComoDocente()` del usuario.
- Pruebas: solo carga y control de acceso (`RevisionPantallasTest`, `AccesoPorRolTest`: 403 para estudiante y supervisor).
- Problemas: ninguno funcional. Hecho: el título de la página no se fija (`render` sin `->title()`, líneas 34-38), a diferencia del resto de pantallas.

### 6.3 BotSugerencias\Widget (95 líneas)
- Propósito: chat del asistente de IA (RF-92 a RF-94). Se incluye en `layouts/app.blade.php:161` para **todos** los roles (archivo 06).
- Propiedades: `abierto`, `mensaje`, `cursoId` (`#[Locked]`, lo fija el servidor), `enviando`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | Toma el curso de la ruta si el usuario puede verlo | — | `CursoPolicy::ver` (32) |
| `alternar`, `abrirDesdeAccesoRapido` | Abren o cierran el panel | — | — |
| `enviar` | Guarda el mensaje, llama al proveedor de IA (síncrono, timeout 20 s) y guarda la respuesta | `required|max:1000` | bot activo en configuración (68); curso revalidado con `ver` (63) |

- Pruebas: `BotFaqTest`, `PanelAdminTest`, `PulidoEstudianteTest`, `Seguridad/AsistenteIaTest` (contexto sin datos de otros estudiantes y curso no modificable).
- Problemas:
  - **H-15 (Medio). Hecho:** no hay límite de solicitudes por usuario al asistente (búsqueda de `throttle` y `RateLimiter` en `app/` y `routes/`: solo existe `api-integracion`). Cualquier usuario autenticado puede enviar mensajes sin límite; cada uno es una llamada HTTP de hasta 20 s que ocupa un worker de `php artisan serve` y consume cuota del proveedor (Groq o Anthropic).
  - Hecho: `getConversacionProperty` usa `firstOrCreate` (línea 53), así que abrir el panel crea filas vacías en `conversaciones_bot`.
  - Hecho: el historial (`mensajes` JSON) crece sin límite; solo se envían al proveedor los últimos 10 (`ConstruyeContextoIA::recortarHistorial`).

## 7. Carpeta `Cursos`

### 7.1 Cursos\BoletaEstudiante (70 líneas)
- Propósito: boleta propia por rubros (RF-59, RF-64). Ruta `cursos.boleta`.
- `mount`: exige rol estudiante y matrícula activa (líneas 26-27). No acepta id de estudiante: solo muestra la propia.
- `render`: notas por rubro, aporte, nota final, condición y nota mínima con `CalculadoraNotas`.
- Pruebas: `FlujosPrincipalesTest`.
- Problemas: hecho, no aplica `CursoPolicy::ver`: un estudiante matriculado en un curso en **borrador** o **despublicado** puede abrir la boleta de ese curso (solo comprueba la matrícula). Expone nombres de rubros y notas, no contenido. Riesgo bajo.

### 7.2 Cursos\EsquemaEvaluacion (163 líneas)
- Propósito: rubros con nombre, semana y peso; suma 100 % (RF-54 a RF-56); bloqueado en curso archivado (RF-62). Ruta `cursos.esquema` con `role:supervisor`.
- Propiedades: `rubros` (arreglo de id, nombre, semana, peso).

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | Crea el esquema si no existe y carga los rubros | — | `administrar` (34) |
| `agregarRubro` | Agrega una fila local | — | ninguna (solo estado local) |
| `quitarRubro` | Quita la fila si el rubro no tiene notas | — | `administrar` y editable |
| `guardar` | Valida, comprueba suma 100 % en el backend, impide borrar rubros con notas, guarda en transacción y registra antes/después en la bitácora | 1-20 rubros, nombres distintos, semana ≤ duración, peso 0,01-100 (88-99) | `administrar` y editable (85-86) |

- Pruebas: `FlujosPrincipalesTest`, `IdorTest`.
- Problemas:
  - Hecho: se registra en la bitácora aunque no haya cambios (líneas 141-148).
  - Hecho: `getEditableProperty` también consulta `esquemaEvaluacion->estaBloqueado()`, pero `bloqueado` nunca se pone en true (archivo 04b). Código muerto.
  - Hecho: H-03 aplica aquí: si el curso se reabre, el esquema vuelve a ser editable.

### 7.3 Cursos\Formulario (227 líneas)
- Propósito: crear y editar cursos (RF-18, RF-19, RF-22 a RF-25, RF-73). Rutas `cursos.crear` y `cursos.editar` con `role:supervisor`.
- Propiedades: `curso`, `nombre`, `codigo`, `descripcion`, `profesor_id`, `profesoresAdicionales`, `portada` (archivo), `estado`, `fecha_inicio`, `fecha_fin`, `semanas_duracion`, `sesiones_clase_count`, `usa_asistencia`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | Carga el curso o prepara uno nuevo | — | `administrar` o `crear` |
| `guardar` | Si el curso está archivado, va a `guardarSoloEstado`. Si no: valida, comprueba rol profesor del titular y de los adicionales, impide publicar sin contenido (RF-25), sube la portada, guarda, sincroniza adicionales y registra antes/después | 97-119: código `alpha_dash` único, fechas, semanas ≥ última semana con contenido, ≤ 104, portada imagen ≤ 10 MB | `administrar` o `crear` (88) |
| `guardarSoloEstado` (protegido) | Cambia el estado de un curso archivado a cualquier otro estado distinto de archivado | estado en lista | heredada de `guardar` |

- Pruebas: `AdminProfesoresTest`, `ContenidoPorSemanasTest`, `CursosTest`, `FlujosPrincipalesTest`.
- Problemas:
  - **H-03 (Alto). Hecho confirmado por prueba temporal** (`test_h_supervisor_reabre_curso_archivado`): `guardarSoloEstado` (líneas 190-213) permite pasar un curso archivado a publicado, borrador o despublicado. El propio mensaje lo llama "reabrir". Esto anula la regla 8 y RNF-14 (periodo finalizado inmutable).
  - Hecho: la validación de rol del titular se salta si el id coincide con el `profesor_id` actual (líneas 121-122). Sirve para el caso "curso a nombre del Supervisor", pero permite guardar el curso con un titular que ya no es profesor (por ejemplo, tras un cambio de rol, H-12).
  - Hecho: al reemplazar la portada no se borra el archivo anterior (líneas 144-148).
  - Hecho: no hay reglas de transición de estado: un curso publicado con estudiantes puede volver a borrador sin aviso.

### 7.4 Cursos\GestionMatricula (164 líneas)
- Propósito: matrícula manual y bajas (RF-26 a RF-33). Ruta `cursos.matriculas` con `role:supervisor|profesor`.
- Propiedades: `busquedaEstudiante`, `estudianteSeleccionadoId` (público), `mostrarModalBaja`, `inscripcionBajaId` (`#[Locked]`), `motivoBaja`, `filtroEstado`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `supervisar` (48) |
| `puedeDarDeBajaA` (público, auxiliar de la vista) | Baja posible si puede dar de baja, la matrícula está activa y, si es de API, el usuario puede `administrar` | — | — |
| `seleccionarEstudiante` | Fija el estudiante (debe tener rol estudiante) | — | `puedeMatricular` (82) |
| `matricularEstudiante` | Llama a `GestorMatriculas::matricularManual` | id existe (94) | `puedeMatricular` (91) |
| `confirmarBaja` / `ejecutarBaja` | Abre el modal y llama a `GestorMatriculas::darDeBaja` con motivo opcional | motivo ≤500 | `puedeDarDeBajaA` (108, 120) |

- Pruebas: `FlujosPrincipalesTest` (baja por profesor en curso propio y ajeno), `IdorTest`.
- Problemas: hecho, `estudianteSeleccionadoId` no es `#[Locked]` y `matricularEstudiante` solo valida que el usuario exista; la comprobación de rol estudiante y cuenta activa la hace el servicio (`GestorMatriculas::validarCursoYEstudiante`, `GestorMatriculas.php:177-190`). Correcto, pero la defensa depende del servicio, no del componente.

### 7.5 Cursos\Index (65 líneas)
- Propósito: listado según el rol (Supervisor todos; Profesor los suyos; Estudiante los publicados o archivados con matrícula activa). Ruta `cursos.index`.
- Propiedades: `busqueda`, `filtroEstado`. Solo `render` y `updating`.
- Pruebas: `CursosTest`, `ValidacionEntradasTest`.
- Problemas: ninguno. La búsqueda usa `like` con parámetros enlazados.

### 7.6 Cursos\LibroCalificaciones (349 líneas)
- Propósito: libro por rubros (RF-57 a RF-65), notas de práctica aparte (RF-48, RF-60) y exportación a Excel (RF-63). Ruta `cursos.libro-calificaciones` con `role:profesor|supervisor`.
- Propiedades: `vistaActiva`, `mostrarModalNota`, `rubroIdModal` y `estudianteIdModal` (`#[Locked]`), `notaInput`, `motivoInput`, `mostrarModalTarea`, `tareaIdModal` y `estudianteTareaIdModal` (`#[Locked]`), `notaTareaInput`, `feedbackTareaInput`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `supervisar` (67) |
| `abrirModalNota` | Carga la nota de un rubro | rubro del curso, estudiante matriculado | `autorizarCalificacion`: `calificar`, no archivado, esquema 100 % |
| `guardarNotaRubro` | `updateOrCreate`; si cambia una nota existente, bitácora con motivo; notifica `NotaRubroRegistrada` | 0-20, un decimal; motivo obligatorio si edita (111-119) | `autorizarCalificacion` |
| `abrirEdicionNota` | Carga la nota de una tarea | tarea del curso | `puedeCalificar` |
| `guardarNotaTarea` | Crea o actualiza la entrega con nota y estado calificado; bitácora si cambia | 0-20 (177) | `puedeCalificar` |
| `exportarExcel` | Descarga `.xlsx` con resumen, rubros y práctica | — | `supervisar` (224) |

- Pruebas: `BlackboardDocenteAlumnoTest`, `FlujosPrincipalesTest`, `HojasCalculo/ExportacionesTest`, `Seguridad/AuditoriaTest`, `IdorTest`.
- Problemas:
  - **H-16 (Bajo). Hecho:** `guardarNotaTarea` (líneas 170-213) no notifica al estudiante. Las otras dos vías de calificar tareas sí envían `TareaCalificada` (`Tareas/Detalle.php:216`, `Docente/PorCalificar/Index.php:97`). RF-84 pide notificar "nueva calificación".
  - Hecho: en `guardarNotaTarea` el motivo de la bitácora es fijo ("Edición desde el libro de calificaciones.", línea 206); el profesor no puede indicarlo. RF-95 dice "motivo del ajuste (si aplica)": cumple a medias.
  - Hecho: en tareas grupales califica solo al estudiante elegido, mientras que las otras dos vías califican a todo el grupo. Tres implementaciones con reglas distintas (archivo 03, § 1).
  - Hecho: la escala de decimales difiere: rubros con un decimal (`decimal:0,1`), tareas con dos (`round(..., 2)`).
  - Hecho: `estudianteMatriculado` exige `estado = activo` pero no `activo_local` (líneas 262-267): se puede calificar a un estudiante de API con acceso local desactivado. Opinión: es razonable (sigue matriculado en el sistema externo).

### 7.7 Cursos\Show (601 líneas)
- Propósito: detalle del curso por rol: contenido por semanas y Prácticas de campo (RF-34, RF-37, RF-38), anuncios (RF-81), progreso (RF-66, RF-67), clase en vivo (ERS 11.2) y clonación (RF-20, RF-21). Ruta `cursos.show`. Vista `livewire/cursos/show` y el parcial `cursos/partials/elementos-seccion`.
- Propiedades: `curso`, `moduloMaterialId`, `material`, `mostrarModalModulo`, `moduloEditarId` (`#[Locked]`), `moduloTitulo`, `moduloEstado`, `moduloPublicarEn`, `moduloCondicion`, `moduloSemana`, `mostrarModalClonar`, `clonNombre`, `clonCodigo`, `clonProfesorId`, `nuevoAnuncioTitulo`, `nuevoAnuncioTexto`, `nuevoAnuncioPrioridad`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `ver` (80) |
| `publicarAnuncio` | Crea un comunicado del curso y notifica a los matriculados activos | título, texto, prioridad (124-131) | `gestionar` |
| `eliminarAnuncio` | Borra un anuncio del curso | id del curso | `gestionar` |
| `alternarLeccionCompletada` | Marca o desmarca una lección publicada de un módulo visible (no las de "abrir recurso") | lección del curso | `estaInscrito` (277) |
| `inscribirme` | Siempre 403 (no hay autoinscripción) | — | `inscribirse` = false |
| `prepararSeccion` | Crea la semana N o Prácticas de campo si no existe y abre su edición | semana 1..total (318) | `gestionar` y no archivado |
| `abrirEditarModulo` / `guardarModulo` | Edita tema, estado, programación, condición de completitud y semana | estado, fecha si programado, semana única libre (350-362) | `gestionar` y no archivado |
| `eliminarModulo` | "Vacía" la semana si no tiene entregas ni resultados | — | `gestionar` y no archivado |
| `subirMaterial` | Agrega un material a `modulos.materiales` | `SubidorArchivos::reglas()` (10 MB) | `gestionar` y no archivado |
| `abrirClonar` / `clonarCurso` | Clona el curso como borrador y redirige a editarlo | nombre, código único, profesor existe (443-450) | `administrar` |

- Pruebas: `BlackboardDocenteAlumnoTest`, `ClasesEnVivoTest`, `ContenidoPorSemanasTest`, `CursoShowEstudianteTest`, `CursosTest`, `CasosDeUsoPrincipalesTest`, `CasosDeUsoTest`, `FlujosPrincipalesTest`, `PulidoEstudianteTest`, `RecursosLeccionTest`, `RolesYFuncionalidadTest`.
- Contenido por semanas (hecho): el profesor ve las semanas 1..`totalSemanas()` con o sin contenido y el bloque de Prácticas de campo si existe; el estudiante ve todas las semanas, pero una semana sin nada visible aparece como "Aún no disponible" sin revelar su tema (líneas 536-567). Las sesiones de clase se asocian a la semana solo por fecha (línea 583).
- Problemas:
  - **H-14 (Medio). Hecho:** `clonarCurso` valida `clonProfesorId` solo con `exists:users,id` (línea 446) y `Curso::clonar` no comprueba el rol (`Curso.php:250-256`). El Supervisor puede dejar como titular del curso clonado a un estudiante u otro supervisor. `Cursos\Formulario` sí exige rol profesor (líneas 121-126).
  - Hecho: `clonarCurso` actualiza el código fuera de la transacción de `clonar` (líneas 452-456); si el código choca por una carrera, el curso queda clonado sin código. Riesgo bajo.
  - Hecho: `publicarAnuncio` no comprueba que el curso no esté archivado (líneas 120-148): el profesor puede publicar anuncios y notificar en un curso finalizado. `eliminarAnuncio` tampoco.
  - Hecho: `eliminarAnuncio` no deja registro en la bitácora.
  - Hecho: la visibilidad de Prácticas de campo para el profesor se calcula con 3 consultas `exists()` adicionales en cada render (líneas 514-518).
  - Hecho: `render` llama a `resumenTareasEstudiante` y `resumenQuizzesEstudiante`, y cada tarea pasa por `Gate::allows('ver')` (línea 598); `resumenQuizzesEstudiante` llama a `resultadoDe()` por quiz (línea 220), con una consulta por quiz (N+1 acotado al número de quizzes del curso).

## 8. Carpeta `Docente`

### 8.1 Docente\PorCalificar\Index (143 líneas)
- Propósito: bandeja de entregas pendientes de todos los cursos del profesor (RF-49, RF-88). Ruta `docente.por-calificar` con `role:profesor`.
- Propiedades: `cursoFiltroId`, `busqueda`, `mostrarModalCalificar`, `entregaSeleccionadaId` (`#[Locked]`), `nota`, `feedback`.

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | — | — | `hasAnyRole(profesor, supervisor)` (41) |
| `abrirCalificar` | Abre el modal | entrega de un curso dictado y no archivado | `EntregaPolicy::calificar` |
| `guardarCalificacion` | Califica la entrega o todo el grupo; bitácora si cambia; notifica `TareaCalificada` | 0-20 | `EntregaPolicy::calificar` |

- Pruebas: `BlackboardDocenteAlumnoTest`, `CasosDeUsoPrincipalesTest`, `IdorTest`.
- Problemas:
  - Hecho: `mount` admite al Supervisor, pero la consulta solo trae cursos que el usuario dicta (`dictadosPor`, línea 114), así que el Supervisor vería la bandeja vacía. La ruta exige `role:profesor`, por lo que no llega a pasar.
  - Hecho: "con atraso" se calcula con `updated_at` de la entrega (línea 131), que cambia con cualquier modificación (H-10).
  - Hecho: el docblock (líneas 17-20) dice "cursos cuyo titular es el usuario", pero el código incluye profesores adicionales. Comentario desactualizado.

## 9. Carpeta `Estudiante`

### 9.1 Estudiante\PanelInicio (97 líneas)
- Propósito: panel de inicio del estudiante (progreso, próximas entregas y mini calendario). Embebido en `dashboard.blade.php`.
- Métodos públicos: `mount` (`hasRole('estudiante')`) y `render`, que usa `ProgresoAcademicoService::resumenDashboardEstudiante` y `eventosDelMes()`.
- Pruebas: `PanelInicioEstudianteTest`, `PulidoEstudianteTest`.
- Problemas:
  - **H-01 (Alto). Hecho confirmado por 2 pruebas temporales** (`test_h_panel_inicio_muestra_titulo_de_tarea_programada` y `test_h_panel_inicio_muestra_tareas_de_curso_dado_de_baja`). En `eventosDelMes` (líneas 47-58):
    - toma los cursos de **todas** las matrículas, incluidas las dadas de baja (`inscripciones()->pluck('curso_id')`, línea 47);
    - filtra las tareas solo con `visible = true` (línea 50), sin `visiblesParaEstudiante()`: muestra tareas **programadas** antes de su publicación, tareas de módulos en borrador y tareas de cursos en borrador o despublicados;
    - no aplica `TareaPolicy::ver`: muestra tareas grupales asignadas a otro grupo.
    - Impacto: viola RF-38 (solo se ve lo publicado) y la baja lógica (RF-30). El título de la tarea y del curso aparecen en el mini calendario y en "Próximos eventos".
  - Hecho (relacionado con H-02): `resumenDashboardEstudiante` tampoco filtra con `TareaPolicy::ver` (`ProgresoAcademicoService.php:241-268`), así que la lista de pendientes y el contador incluyen tareas grupales de otros grupos que el estudiante no puede abrir.

## 10. Carpeta `EventoCalendario`

### 10.1 EventoCalendario\Index (468 líneas)
- Propósito: calendario por rol con eventos propios y actividades de los cursos (RF-78, RF-79). Ruta `evento-calendario.index`. Usa FullCalendar en la vista.
- Propiedades: `fechaInicial`, `eventoResaltado` y `origen` (`#[Url]`, saneados en `mount` con expresiones regulares), `nuevoTitulo`, `nuevaFecha`, `nuevoTipo`.
- Métodos públicos y propiedades computadas:

| Método | Qué hace | Validación | Autorización |
|---|---|---|---|
| `mount` | Descarta parámetros de URL con formato inválido | regex y `checkdate` (69-72) | — |
| `crear` | Crea un evento propio y lo envía al navegador | título ≤255, fecha, tipo en lista | propio (`auth()->user()->eventosCalendario()`) |
| `eliminar` | Borra un evento propio | — | `where('usuario_id', auth()->id())` |
| `cursos`, `coloresCursos`, `eventos`, `agenda` (`#[Computed]`) | Arman los eventos y el panel lateral por rol | — | Estudiante: tareas filtradas con `TareaPolicy::ver` (235) y contenido `visiblesParaEstudiante` |

- Pruebas: `CalendarioNotificacionesTest`, `CalendarioPorRolTest`, `ClasesEnVivoTest`, `CasosDeUsoTest`, `PropuestaBotTest`.
- Problemas:
  - Hecho: para el Supervisor, `notasPendientes` hace 2 consultas por curso y una más por rubro (líneas 449-456): N+1 sobre cursos × rubros.
  - Hecho: las sesiones se muestran solo si el curso usa asistencia (`where('usa_asistencia', true)`, línea 256). Una clase en vivo de un curso sin control de asistencia no aparece en el calendario. Opinión: el enlace de reunión (ERS 11.2) no depende de la asistencia; es una omisión.
  - Hecho: el cálculo de la semana del rubro (`semanaDelCurso`, línea 305) usa `startOfWeek` de `fecha_inicio`; es el mismo criterio que `Curso::rangoSemana`. Coherente.

## 11. Carpetas `Actions` y `Forms`

### 11.1 Forms\LoginForm (79 líneas) y Actions\Logout (20 líneas)
- `LoginForm::authenticate` (usado por el Volt `pages.auth.login`):
  - valida correo y contraseña;
  - si la cuenta está bloqueada, rechaza antes de comprobar la contraseña;
  - si la contraseña falla, suma un intento (`User::registrarIntentoFallido`), escribe un `Log::warning` con correo e IP (RNF-05) y, al llegar a 3, bloquea 15 minutos (RF-06);
  - informa "cuenta desactivada" solo después de validar la contraseña (no revela qué cuentas existen);
  - inicia sesión, limpia los intentos y regenera la sesión.
- `Logout`: cierra la sesión, la invalida y regenera el token CSRF.
- Pruebas: `Auth/AuthenticationTest`, `Seguridad/AutenticacionTest` (bloqueo, desbloqueo, cuenta desactivada).
- Problemas:
  - Hecho: el bloqueo es por cuenta, no por IP; no hay `RateLimiter` en el login (la plantilla de Breeze con `ensureIsNotRateLimited` fue reemplazada). Un atacante puede bloquear cualquier cuenta conocida con 3 intentos cada 15 minutos (denegación de servicio dirigida) y probar contraseñas contra muchas cuentas sin límite por IP (*password spraying*). Ver archivo 13.
  - Hecho: el log guarda el correo en claro y la IP. Detrás de Railway la IP sería la del proxy (H-08).
  - Hecho: si el correo no existe, la respuesta es la misma que con contraseña incorrecta (no hay enumeración por mensaje). Por tiempo de respuesta sí podría haber diferencia (no verificado).

## 12. Hallazgos nuevos de esta parte

| ID | Severidad | Componente | Resumen |
|---|---|---|---|
| H-14 | Medio | `Cursos\Show::clonarCurso` | El titular del curso clonado puede ser cualquier usuario, sin comprobar el rol profesor |
| H-15 | Medio | `BotSugerencias\Widget::enviar` | Sin límite de solicitudes al asistente de IA por usuario |
| H-16 | Bajo | `Cursos\LibroCalificaciones::guardarNotaTarea` | No notifica la calificación (RF-84) y usa un motivo fijo en la bitácora |

Hallazgos previos confirmados aquí: H-01 (§ 9.1), H-03 (§ 7.3), H-10 (§ 8.1). H-13 se describe en 07b (`Quizzes\Resolver`).
