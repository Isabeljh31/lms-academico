# 08. Servicios y lógica de negocio (`app/Services`)

17 archivos: 15 clases, la interfaz `Bot/Contracts/ProveedorIA` y el trait `Bot/Concerns/ConstruyeContextoIA`. Se leyeron completos (volcado del 13/10/2026; sin cambios después). "Llamado por" se obtuvo con búsquedas literales del nombre del método en `app/`, `resources/` y `routes/`; "Pruebas" con la misma búsqueda en `tests/`. Una prueba listada usa el método directa o indirectamente; "—" significa que ninguna prueba nombra el método (puede estar cubierto a través de un componente).

Pruebas unitarias existentes (4 archivos, más `ExampleTest`): `Unit/Services/Evaluacion/CalculadoraNotasTest` (11 casos), `Unit/Services/Asistencia/CalculadoraAsistenciaTest` (4), `Unit/Models/QuizTest` (6) y `Unit/Models/TareaTest` (7). El resto de los servicios solo se prueba a través de pruebas Feature.

## 1. ConfiguracionPlataforma (`Services/ConfiguracionPlataforma.php`, 169 líneas)

Configuración clave/valor en `configuraciones_plataforma` (RF-107, RF-108). Registrada como *singleton* (`AppServiceProvider:48`). Memoriza los valores en `$valores` durante la vida del objeto.

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `botActivo()` | → bool | Lee `bot_activo` | `Widget`, `PanelAdmin\Configuracion` | PanelAdminTest |
| `establecerBotActivo(bool)` | → void | Guarda `bot_activo` | `PanelAdmin\Configuracion::alternarBot` | — |
| `notaMinimaAprobatoria()` | → float | RF-107; por defecto 10,5 | `CalculadoraNotas`, `Tareas\Detalle`, `Quizzes\Estadisticas`, `Configuracion` | CalculadoraNotasTest |
| `zonaHoraria()` | → string | Zona válida o `America/Lima` | interno | ConfiguracionRegionalTest |
| `aplicarZonaHoraria()` | → void | Cambia `config('app.timezone')` y `date_default_timezone_set` | `AppServiceProvider:89-91` (solo peticiones web y pruebas), los 3 comandos programados, `Configuracion::guardar` | ConfiguracionRegionalTest |
| `formatoFecha()`, `formatoFechaHora(bool)`, `formatoDiaMes()`, `formatoFechaHoraExcel()` | → string | RF-108: `d/m/Y`, `d-m-Y` o `Y-m-d` | Macros de Carbon (`AppServiceProvider:93-106`), vistas, notificaciones, `LibroXlsx` | ConfiguracionRegionalTest |
| `obtener(string)`, `todos()` | → mixed / array | Valor guardado, o el del archivo JSON anterior, o el predeterminado | `Configuracion`, `ExportadorLibroCalificaciones` | indirectas |
| `guardar(array)` | → void | `updateOrInsert` por clave y limpia la memoria | `Configuracion` | indirectas |

Problemas:
- **H-21 (Medio). Hecho por lectura:** la zona horaria de la institución solo se aplica en peticiones web, pruebas y comandos programados (`AppServiceProvider.php:89`: `! runningInConsole() || runningUnitTests()`; los comandos la aplican al iniciar). El worker de colas (`queue:work`, `railway.json:8`) es un proceso de consola y no la aplica: trabaja en `UTC` (`config/app.php:68`). La notificación encolada `MatriculadoEnCurso` guarda su fila en `notifications` con `created_at` en UTC, mientras el resto de fechas se guardan en hora de Lima (5 horas de diferencia). No verificado en ejecución.
- Hecho: las fechas se guardan sin zona (`timestamp` sin `tz`) en la hora de la institución. Cambiar la zona horaria reinterpreta todos los datos existentes (07b § 5.2).
- Hecho: en el *singleton* los valores quedan memorizados. En un proceso largo (el worker) un cambio de configuración no se ve hasta reiniciar el proceso. Opinión: hoy el worker solo envía correo y una notificación, así que el impacto es mínimo.
- Hecho: el nombre de la institución (RF-108) solo se usa en la exportación del libro de calificaciones (`ExportadorLibroCalificaciones.php:33`). No aparece en el layout, los correos ni la boleta PDF (búsqueda de `nombre_institucion` en `app/` y `resources/`).

## 2. Asistencia\CalculadoraAsistencia (102 líneas)

Regla documentada (líneas 14-18): solo cuentan sesiones con fecha pasada; presente y tardanza asisten; justificado se excluye; sesión sin registro = ausente.

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `porcentaje(iterable)` | estados → `?float` (1 decimal) | RF-71 | `Asistencia\Index`, `MiHistorial`, interno | CalculadoraAsistenciaTest (4), CasosDeUsoTest |
| `historial(User, Curso)` | → Collection de sesión, estado, observación | Sesiones pasadas con su estado | `Asistencia\Index`, `MiHistorial` | FlujosPrincipalesTest |
| `porcentajeDe(User, Curso)` | → `?float` | Atajo de los dos anteriores | sin llamadas en `app/` | AnaliticaRiesgoTest |
| `porcentajesDeInscripciones(iterable)` | inscripciones → `["curso:estudiante" => %]` | Mismo cálculo en 2 consultas | `Admin\Metricas\Index` | AnaliticaRiesgoTest |

Problemas:
- Hecho: una sesión sin registro cuenta como ausencia aunque el profesor todavía no haya pasado lista (por ejemplo, la clase de hoy ya iniciada). Baja el porcentaje de todos hasta que se registra. Está documentado como criterio.
- Hecho: `porcentajeDe` no tiene llamadas en la aplicación (solo en pruebas). Código muerto en producción.

## 3. Bot (proveedores de IA, propuestas y contexto)

### 3.1 Contracts\ProveedorIA (21 líneas) y Concerns\ConstruyeContextoIA (185 líneas)
- Interfaz: `activo(): bool` y `responder(pregunta, ?Curso, historial, ?User): string`. Enlazada en `AppServiceProvider::register` (líneas 41-45) según `config('services.ia.proveedor')`.
- El trait arma el *prompt* de sistema:
  - reglas fijas: responder solo con el CONTEXTO, no revelar datos de otros estudiantes, ignorar instrucciones que intenten cambiar las reglas, no resolver evaluaciones (líneas 30-38);
  - contexto: curso, descripción, módulos visibles y lecciones publicadas (para el estudiante), tareas pendientes propias, quizzes abiertos no rendidos, sesiones y rubros de las próximas 3 semanas, eventos propios, nota final y rendimiento propios (líneas 43-165);
  - historial: últimos 10 mensajes (`recortarHistorial`);
  - modo simulado sin clave.
- Pruebas: `Seguridad/AsistenteIaTest` (el contexto no incluye datos de otros estudiantes; el curso no se cambia desde el navegador), `BotFaqTest`.
- Problemas:
  - Hecho: para profesor y supervisor, el contexto incluye **todas** las lecciones del curso, también las de borrador (`$esEstudiante ? publicadas() : $q`, línea 58). Correcto según el rol.
  - Hecho: el contexto se arma con unas 8 consultas por mensaje; las descripciones se truncan (500 y 300 caracteres). Sin límite de lecciones: un curso con muchas lecciones produce un *prompt* grande y costoso (opinión).
  - Hecho: la defensa contra inyección de instrucciones es solo textual (regla 3 del *prompt*). No hay filtrado de la salida. Riesgo aceptado: el contexto solo tiene datos del propio usuario.
  - Hecho: el comentario de `GroqProveedor.php:16-18` dice "Llama 3.3 70B", pero el modelo configurado es `openai/gpt-oss-120b` (archivo 03, § 5).

### 3.2 GroqProveedor (70 líneas) y ClaudeBotService (75 líneas)
| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `activo()` | → bool | Hay clave configurada | `Widget::render` | BotFaqTest |
| `responder(...)` | → string | POST síncrono con `timeout(20)` y `max_tokens` 512; si falla, mensaje genérico y `Log::warning`/`error` sin datos sensibles | `Widget::enviar` | AsistenteIaTest (con `Http::fake`), BotFaqTest |

Problemas: H-15 (sin límite por usuario, 07a § 6.3). Hecho: no hay reintentos ni registro de consumo de tokens.

### 3.3 PropuestaBotService (129 líneas)
Regla de gobernanza (docblock, líneas 14-22): el bot nunca escribe en `eventos_calendario`; solo `ejecutarPropuestaAprobada` lo hace, y exige una propuesta aprobada.

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `leerProgresoEstudiante(User, Curso)` | → total, vencidas sin entrega, promedio | Tareas visibles para el estudiante | interno | indirectas |
| `leerCalendario(User)` | → eventos | — | **sin llamadas** (código muerto, archivo 05 § 3) | — |
| `sugerirRedistribucionPlan(User, Curso)` | → `?PropuestaBot` | Crea una propuesta pendiente por la primera tarea vencida sin entrega; no duplica | interno | PropuestaBotTest |
| `detectarAtrasos()` | → int | Recorre cursos publicados y matrículas activas | comando `bot:detectar-atrasos` (diario 06:00) | PropuestaBotTest |
| `ejecutarPropuestaAprobada(PropuestaBot)` | → EventoCalendario | 409 si no está aprobada | `BotSugerencias\Bandeja::aceptar` | PropuestaBotTest |

Problemas:
- Hecho: `leerProgresoEstudiante` no aplica `TareaPolicy::ver` (línea 32): puede proponer un recordatorio por una tarea grupal asignada a **otro** grupo, que el estudiante no puede abrir. Misma causa que H-02.
- Hecho: `detectarAtrasos` hace 2 consultas por matrícula activa (tareas y búsqueda de duplicados) más la creación: N+1 sobre todas las matrículas de cursos publicados, una vez al día. La búsqueda de duplicados carga todas las propuestas pendientes del estudiante y filtra en PHP (líneas 68-72).
- Hecho: la fecha propuesta es "mañana 18:00" con `toIso8601String()` (incluye la zona); `Bandeja::fechaPropuesta` corta los primeros 16 caracteres y descarta la zona. Depende de que el comando corra con la zona de la institución (lo hace, `DetectarAtrasosBot` llama a `aplicarZonaHoraria`).

## 4. Calendario\ColoresCursos (61 líneas)

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `consultaCursosDe(User)` | → Builder | Supervisor: publicados; profesor: dictados no archivados; estudiante: matrícula activa, visibles y no archivados | `EventoCalendario\Index` | CalendarioPorRolTest (indirecta) |
| `asignar(Collection)` | → `[curso_id => color]` | Color por posición en la lista ordenada (8 colores, cíclico) | `EventoCalendario\Index`, interno | indirectas |
| `paraUsuario(User)` | → `[curso_id => color]` | Igual con la consulta anterior | `BotSugerencias\Bandeja` | PropuestaBotTest (indirecta) |

Problemas: hecho, el color depende del orden alfabético: si se agrega o se archiva un curso, cambian los colores de los demás. Es cosmético.

## 5. Evaluacion\CalculadoraNotas (266 líneas)

Reglas (docblock, líneas 13-27): pesos suman 100 % con tolerancia 0,01 (RF-56); nota final = Σ nota × peso / 100 en escala 0-20, con 1 decimal (RF-58); tareas y quizzes nunca entran (RF-48, regla 5); aprobado si nota ≥ mínima (RF-64); mientras falten rubros la condición es "en curso".

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `pesosSumanCien(iterable)` | → bool | \|suma − 100\| < 0,01 | `EsquemaEvaluacion`, `LibroCalificaciones`, `BoletaEstudiante` | CalculadoraNotasTest, DatosDeEjemploTest |
| `sumarPesos(iterable)` | → float (2 decimales) | — | `EsquemaEvaluacion`, `LibroCalificaciones`, `Exportador` | indirectas |
| `notaFinal(rubros, notas)` | → `?float` | Rubros sin nota aportan 0; null si no hay ninguna | `BoletaEstudiante`, `LibroCalificaciones`, `ProgresoAcademicoService` | CalculadoraNotasTest |
| `estaCompleta(rubros, notas)` | → bool | Todos con nota | interno | CalculadoraNotasTest |
| `rendimiento(rubros, notas)` | → `?float` | Promedio ponderado solo de rubros calificados | `LibroCalificaciones`, `ProgresoAcademicoService` | CalculadoraNotasTest |
| `condicion(rubros, notas)` | → `sin_notas`, `en_curso`, `aprobado`, `desaprobado` | — | idem | CalculadoraNotasTest |
| `resumenDelCurso(Curso, ids)` | → por estudiante: final, rendimiento, condición | 1 consulta | `PanelAdmin\Metricas`, `PanelGestion`, `ProgresoAcademicoService` | indirectas |
| `tasaAprobacion(iterable)` | → `?int` % | Solo esquemas completos | `LibroCalificaciones`, `Metricas`, `PanelGestion`, `ProgresoAcademicoService` | CalculadoraNotasTest |
| `notaMinima()`, `aprueba(?float)` | → float / bool | RF-64 | muchos | CalculadoraNotasTest |
| `notaFinalDe(User, Curso)`, `rendimientoDe(User, Curso)` | → `?float` | 1 consulta | `ConstruyeContextoIA`, `ProgresoAcademicoService` | FlujosPrincipalesTest, AnaliticaRiesgoTest |
| `rendimientosDeInscripciones(iterable)` | → `["curso:estudiante" => rendimiento]` | 1 consulta | `Admin\Metricas\Index` | AnaliticaRiesgoTest |
| `notasFinalesDelCurso(Curso, ids)` | → `[id => nota]` | — | **sin llamadas** (código muerto) | — |

Problemas:
- Hecho: la nota final se redondea a 1 decimal (línea 79) y se compara con la mínima (10,5). Una suma real de 10,46 se redondea a 10,5 y aprueba. Es una decisión válida si se documenta (opinión); el ERS no fija la regla de redondeo.
- Hecho: el rendimiento a la fecha y la nota final usan el mismo nombre visual en algunos paneles (por ejemplo, `ProgresoAcademicoService::resumenCurso` devuelve `'nota_final' => rendimiento`, línea 204). Riesgo de confusión en reportes.
- Hecho: H-20 (la nota mínima vigente no se congela al archivar el curso).
- Hecho: `EsquemaEvaluacion::pesosValidos` duplica `pesosSumanCien` (archivo 05).

## 6. Evaluacion\ExportadorLibroCalificaciones (179 líneas)

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `libro(Curso, filas, notasPractica)` | → `LibroXlsx` con hojas Resumen, Notas por rubro y Práctica | RF-63; rojo bajo la mínima (RF-64); práctica aparte (RF-60) | `LibroCalificaciones::exportarExcel` | HojasCalculo/ExportacionesTest |

Problemas: hecho, la hoja Práctica toma todas las tareas y quizzes del curso, incluidas las de borrador (líneas 143-144). Opinión: es un documento del docente, aceptable.

## 7. HojasCalculo (3 clases sin dependencias externas)

| Clase | Métodos públicos | Regla y seguridad | Llamado por | Pruebas |
|---|---|---|---|---|
| `HojaXlsx` (352 líneas) | `titulo`, `seccion`, `encabezados`, `fila`, `filaTotal`, `pares`, `espacio`, `celda` (estático), `totalColumnas`, `xml`, `referencia` (estático), `fechaExcel` (estático), `escapar` (estático) | Texto siempre como `inlineStr` (nunca fórmula: no hay inyección de fórmulas CSV/Excel, línea 300); quita caracteres de control y escapa XML (346-351) | `LibroXlsx`, `ExportadorLibroCalificaciones`, `PanelAdmin\Auditoria` | LibroXlsxTest, ExportacionesTest |
| `LibroXlsx` (225 líneas) | `hoja`, `descargar`, `contenido` | Genera el ZIP en un temporal y lo borra; nombres de hoja válidos y únicos | `Auditoria`, `Exportador` | LibroXlsxTest |
| `LectorXlsx` (215 líneas) | `filas(string)` | Lee la primera hoja; rechaza `<!DOCTYPE` (XXE) y usa `LIBXML_NONET`; límite de 20 MB por XML interno (ZIP bomb) | `Usuarios\ImportarCSV` | LibroXlsxTest |

Problemas:
- Hecho: el límite anti ZIP bomb usa el tamaño declarado en el directorio del ZIP (`statName`, línea 190). Un archivo manipulado puede declarar un tamaño menor que el real. El archivo subido está limitado a 2 MB (`ImportarCSV`), lo que acota el riesgo. Opinión: bajo.
- Hecho: el libro completo se arma en memoria (cadena) antes de enviarlo (`contenido()`), incluida la exportación de 5000 filas de bitácora.

## 8. Matricula\GestorMatriculas (201 líneas)

Reglas compartidas por la matrícula manual y la API (docblock, líneas 15-26).

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `matricularManual(Curso, User, User)` | → Inscripcion | Curso no archivado; rol estudiante; cuenta activa; reactiva bajas manuales; en matrículas de API solo reactiva el acceso local y exige que el sistema externo la tenga activa; notifica (RF-33) y audita (RF-96) | `Cursos\GestionMatricula` | FlujosPrincipalesTest, IdorTest (vía componente) |
| `sincronizarDesdeApi(Curso, User, string, ?string)` | → `?Inscripcion` | RF-27, RF-104, RF-105: crea o reactiva; respeta el bloqueo local; una manual previa pasa a `origen=api`; baja = `estado inactivo`; audita sin ejecutor | `Api\MatriculaController` | Seguridad/ApiIntegracionTest |
| `darDeBaja(Inscripcion, User, ?string)` | → void | RF-29 a RF-32: baja lógica o desactivación local si es de API; curso no archivado; audita con motivo | `Cursos\GestionMatricula` | FlujosPrincipalesTest |

Problemas:
- Hecho: ninguno de los métodos usa transacción. Si la notificación o la bitácora fallan después de crear la inscripción, la matrícula queda sin registro de auditoría (la notificación sí está protegida con `try/catch`, líneas 194-199; la bitácora no).
- Hecho: una baja informada por la API sobre una matrícula **manual** la desactiva (líneas 131-141), aunque la matrícula no vino de la API. Opinión: coherente con "el sistema externo es la fuente de verdad", pero el ERS (RF-105) solo habla de matrículas creadas por API.
- Hecho: la baja por API no comprueba si la matrícula ya estaba inactiva: registra una nueva entrada de bitácora en cada llamada repetida.

## 9. Multimedia\SubidorArchivos (233 líneas)

RNF-06: todo archivo se guarda privado y la base guarda una referencia `privado://`. Límite único de 10 MB (`config/archivos.php`, `ARCHIVOS_MAX_KB=10240`).

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `activo()` | → bool | Hay `CLOUDINARY_URL` | interno | — |
| `reglas(?array, bool)` (estático) | → reglas de validación | `file`, `max:10240`, `extensions:` de la lista blanca | `Cursos\Formulario`, `Cursos\Show`, `Lecciones\Index`, `Mensajeria\Bandeja`, `Tareas\Detalle` | SubidaArchivosTest |
| `mensajes(string)` (estático) | → mensajes en español | — | los mismos | SubidaArchivosTest |
| `extensionesPermitidas(?array)` (estático) | → array | Intersección con `documentos` | `Tareas\Formulario`, vista de entrega | SubidaArchivosTest |
| `subir(UploadedFile, carpeta, campo, ?array)` | → `privado://...` | Revalida extensión y tamaño; Cloudinary `type=private` o disco `local` con nombre UUID; cualquier error → `ValidationException` | 5 componentes y el Volt de perfil | SubidaArchivosTest, ArchivosPrivadosTest |
| `esPrivado(?string)` (estático) | → bool | Prefijo `privado://` | `PrivatizarArchivos`, `Lecciones\Index` | ArchivosPrivadosTest |
| `urlTemporal(string, int)` | → URL firmada de 10 min (o la misma URL si es pública) | Cloudinary `private_download_url` o `Storage::temporaryUrl` | `ArchivoController`, `RecursoLeccionController` | ArchivosPrivadosTest |
| `eliminar(?string)` | → void | Borra en Cloudinary o local; también URLs públicas anteriores | `Lecciones\Index::eliminarRecurso`, Volt de perfil | RecursosLeccionTest |

Problemas:
- Hecho: la validación es por **extensión** declarada por el cliente (`getClientOriginalExtension`, línea 103; regla `extensions`), no por tipo real (MIME o contenido). Un archivo con extensión `.pdf` y contenido HTML se acepta. Como se sirve desde Cloudinary o desde una URL firmada del disco local, el riesgo depende de las cabeceras con que se sirva (ver archivo 13).
- Hecho: la lista blanca `documentos` incluye `mp4` y `mp3` (`config/archivos.php`). El ERS RF-36 no los menciona (archivo 12).
- Hecho: `subirACloudinary` usa `resource_type=auto`; los PDF en Cloudinary pueden requerir habilitar la entrega de PDF en la cuenta (no verificable sin cuenta). **Ninguna prueba sube a Cloudinary real**; solo se prueba el disco local (archivo 11).
- Hecho: la URL temporal del disco local depende de `serve=true` en `config/filesystems.php:33-39` y de la firma de `APP_KEY`; si `APP_KEY` rota, los enlaces activos dejan de valer (esperado).

## 10. Usuarios\GestorCuentas (92 líneas)

Único punto de creación de usuarios (docblock, líneas 15-23).

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `crear(nombre, email, rol, ?User, origen)` | → usuario, contraseña temporal, correo enviado | RF-03 contraseña temporal; RF-04 correo de bienvenida; RF-05 `primer_login=true`; `email_verified_at=now()`; audita (RF-96) | `Usuarios\Index`, `ImportarCSV`, `Admin\Profesores\Index`, `Api\MatriculaController` | UsuariosTest, FlujosPrincipalesTest, ApiIntegracionTest |
| `generarPasswordTemporal()` | → string | `Str::password(12, symbols: false)` | interno | indirectas |

Problemas:
- Hecho: `crear` acepta cualquier valor de `Rol` (incluido `supervisor`): la restricción a `ROLES_CREABLES` la hacen los componentes que llaman (`Usuarios\Index`, `ImportarCSV`); `Admin\Profesores` fija `profesor`, y la API fija `estudiante` (no verificado aquí; ver archivo 09).
- Hecho: el usuario se guarda y se le asigna el rol en dos pasos sin transacción (líneas 49-50): si `assignRole` falla, queda una cuenta sin rol.
- Hecho: `BienvenidaMail` va en cola y cifrada (archivo 09). `Mail::send` de un *mailable* `ShouldQueue` encola, así que `correo_enviado = true` significa "encolado", no "entregado". El Supervisor puede creer que el correo llegó.

## 11. Reportes\ProgresoAcademicoService (356 líneas)

| Método | Entrada → salida | Regla | Llamado por | Pruebas |
|---|---|---|---|---|
| `progresoEstudiante(User, Curso)` | → tareas, quizzes, promedios de práctica | Promedio de tareas ponderado por `peso_nota`; quizzes por mejor intento | `Reportes\Dashboard`, `BoletaPdfController` | ReportesTest |
| `avance(User, Curso)` | → int 0-100 | RF-66: (entregas + quizzes rendidos + lecciones completadas) / visibles | `Reportes\Dashboard`, interno | CasosDeUsoPrincipalesTest, CasosDeUsoTest |
| `estadoModulos(User, Curso)` | → por módulo: completado, hechos, total, condición | RF-67 según `condicion_completitud` | `Cursos\Show` | CasosDeUsoTest |
| `resumenCurso(Curso)` | → inscritos, promedios, tasa de entrega, avance promedio, aprobación, bajo rendimiento | RF-69, RF-89 | `Reportes\Dashboard` | CasosDeUsoTest |
| `resumenDashboardEstudiante(User)` | → progreso general, cursos, próximas entregas | Cursos visibles y no archivados | `Estudiante\PanelInicio` | CasosDeUsoTest |
| `notaFinalPorRubros(User, Curso)` | → `?float` | — | **sin llamadas** (código muerto) | — |
| `resumenRubros(User, Curso)` | → filas por rubro, final, rendimiento, condición | RF-59 | `Reportes\Dashboard` | indirecta (ReportesTest) |

Problemas:
- **H-02 (Alto). Hecho confirmado por prueba temporal** (`test_h_avance_no_llega_a_100_con_tarea_de_otro_grupo`: el estudiante entregó todo lo que puede ver y el avance da 50 %). `avance()` cuenta todas las tareas `visiblesParaEstudiante` del curso (línea 90), sin `TareaPolicy::ver`; las tareas grupales asignadas a otro grupo entran en el total y nunca se pueden completar. `estadoModulos` sí filtra (línea 130). El mismo problema afecta a `resumenDashboardEstudiante` (líneas 241-268: pendientes y contador), a `PropuestaBotService::leerProgresoEstudiante` y al reporte del profesor (`resumenCurso`: avance promedio y bajo rendimiento por avance en cursos finalizados).
- Hecho: `resumenCurso` calcula `avance()` por estudiante (4 consultas cada uno, línea 188) y considera "tareas vencidas sin entregar" también a tareas grupales de otros grupos (líneas 193-196). N+1 sobre estudiantes.
- Hecho: `resumenCurso` devuelve el rendimiento bajo la clave `nota_final` del bajo rendimiento (línea 204).
- Hecho: el avance (RF-66) cuenta una tarea como completada con cualquier entrega, incluso "devuelta" para corrección.

## 12. Resumen de la capa de servicios

| Aspecto | Hecho | Evidencia |
|---|---|---|
| Inyección | Por constructor (`CalculadoraNotas`, `Exportador`, `ProgresoAcademicoService`) o por método de Livewire; `app()` en vistas y macros | archivos citados |
| Transacciones | Solo `Curso::clonar`, `EsquemaEvaluacion::guardar`, `Asistencia::guardarAsistencia`, `Tareas\Detalle::entregar`, `Quizzes\Formulario::persistir`, `Grupos::asignar` y la API. Ningún servicio abre transacciones propias. | búsqueda de `DB::transaction` |
| Métodos sin uso en la aplicación | `CalculadoraNotas::notasFinalesDelCurso`, `ProgresoAcademicoService::notaFinalPorRubros`, `PropuestaBotService::leerCalendario`, `CalculadoraAsistencia::porcentajeDe` (solo pruebas) | búsquedas de § 1 a § 11 |
| Lógica de negocio fuera de servicios | Calificación (3 copias), riesgo de abandono, entrega grupal y clonación | archivo 03 § 1 |
| Servicios sin prueba unitaria | Todos menos `CalculadoraNotas` y `CalculadoraAsistencia` | `tests/Unit` |

## 13. Hallazgos nuevos de este archivo

| ID | Severidad | Resumen |
|---|---|---|
| H-21 | Medio | El worker de colas no aplica la zona horaria de la institución: lo encolado se fecha en UTC |

Hallazgos previos detallados aquí: H-02 (§ 11), H-15 (§ 3.2), H-20 (§ 5).
