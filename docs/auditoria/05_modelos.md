# 05. Modelos (`app/Models`)

26 modelos más el trait `Concerns/TieneEstadoPublicacion`. Ninguno usa `$guarded`: todos declaran `$fillable`. No hay observers ni `SoftDeletes`. Los eventos de modelo están en `booted()` de 3 modelos (`BitacoraAuditoria`, `LogIntegracionApi` y `Modulo`).

## 1. Modelos con lógica de negocio

### User (`User.php`, 187 líneas)
- Traits: `HasFactory`, `HasRoles` (Spatie), `Notifiable`. Oculta `password` y `remember_token` (atributo `#[Hidden]`, línea 15).
- Fillable: `name`, `email`, `password`, `avatar_url`, `activo`, `primer_login`. Los campos de bloqueo (`bloqueado_hasta`, `intentos_fallidos`) **no** son asignables en masa (líneas 31–44).
- Casts: `password` como `hashed` (RNF-01), booleanos y fechas.
- Atributos por defecto: `activo=true`, `primer_login=true`, `intentos_fallidos=0`.
- Métodos:
  - `estaBloqueada()`.
  - `registrarIntentoFallido()`: reinicia el conteo si un bloqueo anterior ya expiró y bloquea por `config('auth.bloqueo_login.minutos')` al llegar al máximo (líneas 72–83).
  - `limpiarIntentosFallidos()`.
  - `estaMatriculadoEn(Curso)`: matrícula activa con acceso local.
  - `enlaceFoto()`: ruta autorizada con un parámetro `v` de caché.
  - `rolPrincipal()`.
  - `grupoEnCurso(Curso)`.
  - `cursosComoDocente()`: devuelve un Builder.
- Relaciones: `cursosComoProfesor`, `cursosComoProfesorAdicional`, `inscripciones`, `entregas`, `eventosCalendario`, `propuestasBot`, `comunicados`, `notasRubro`, `accionesAuditoria`.
- Problema menor: el docblock "ERS RF-23: cursos donde es profesor adicional" (línea 111) quedó pegado encima de `enlaceFoto()` (línea 112) en lugar de `cursosComoProfesorAdicional()`.
- No implementa `MustVerifyEmail`, así que el middleware `verified` nunca bloquea. Las cuentas se crean con `email_verified_at` lleno (`GestorCuentas.php:48`).

### Curso (`Curso.php`, 323 líneas)
- Constantes: `ESTADOS` (borrador, publicado, despublicado, archivado), `ESTADOS_VISIBLES_ESTUDIANTE` (publicado y archivado), `SEMANAS_PREDETERMINADAS=16`.
- Fillable: nombre, codigo, descripcion, profesor_id, imagen_portada_url, estado, fechas, semanas_duracion, sesiones_clase_count, usa_asistencia.
- Scopes: `dictadosPor(User|int)` (titular o adicional) y `visiblesParaEstudiantes`.
- Métodos:
  - `estaPublicado()`; `estaFinalizado()` (= archivado); `esVisibleParaEstudiantes()`.
  - `esDocente(User|int|null)`; `docentes()`.
  - `enlacePortada()`.
  - `totalSemanas()`: el máximo entre la duración (o 16) y la semana más alta con contenido.
  - `rangoSemana(int)`: lunes y domingo de la semana N.
- Relaciones: profesor, profesoresAdicionales, modulos (ordenados), inscripciones, inscripcionesActivas, tareas, quizzes, grupos, comunicados, esquemaEvaluacion, sesionesClase, mensajes, bancoPreguntasCurso.
- `clonar(nombre, profesorId)` (líneas 250–322), dentro de una transacción:
  - copia el curso (sin código), los módulos, las lecciones con sus recursos, las tareas y los quizzes, todo en borrador;
  - copia el esquema con sus rubros y el banco de preguntas;
  - no copia matrículas, notas, entregas, resultados, asistencia, grupos, mensajes ni comunicados (RF-21, regla 4).
- Riesgos de `clonar()`:
  - **Hecho:** copia las referencias de archivos (`material_url`, `recursos.url`, `imagen_portada_url`, `materiales`), no los archivos. Las dos copias del curso comparten el archivo físico. `Lecciones/Index::eliminarRecurso` (líneas 234–237) lo tiene en cuenta para los recursos, pero no para `material_url` ni la portada, que nunca se borran (no hay archivos huérfanos que borrar, pero tampoco hay limpieza).
  - **Hecho:** copia `fecha_inicio`, `fecha_fin` y `sesiones_clase_count` del ciclo anterior; el flujo 9.4 pide que el Supervisor los ajuste, y la pantalla de clonación redirige a editar el curso (`Show.php:467`).
  - **Hecho:** no copia los profesores adicionales ni las sesiones de clase.

### Modulo (`Modulo.php`, 152 líneas): cada fila es una semana o el bloque de Prácticas de campo
- Constantes: `CONDICIONES` (actividades, lecciones, aprobacion; RF-67), `TIPO_SEMANA`, `TIPO_PRACTICAS`.
- Evento `creating` (líneas 63–79):
  - en Prácticas de campo fuerza `semana=null` y `orden=0`;
  - si no se indica semana, asigna la siguiente libre (`max+1`) y `orden=semana`.
  - Hay una posible carrera si se crean dos a la vez, que el índice único `(curso_id, semana)` rechaza.
- Métodos:
  - `esPracticas()`; `etiqueta()` ("Semana 3" o "Prácticas de campo");
  - `tema()`: quita los prefijos "Unidad N:", "Módulo N:" y "Semana N:";
  - `nombreCompleto()`;
  - `tieneHistorialAcademico()`: tiene entregas o resultados de quiz.
- Relaciones: curso, tareas, quizzes, lecciones, leccionesVisibles. Usa `TieneEstadoPublicacion`.

### Leccion (`Leccion.php`, 127 líneas)
- Constantes: `CONDICIONES` (marcar, abrir_recurso) y `TIPOS_RECURSO` (7 tipos, con etiqueta e ícono).
- Usa su propia columna `estado` y `fecha_publicacion`, no el trait.
- Métodos y scopes:
  - `esVisibleParaEstudiante()`: publicada, o programada con fecha pasada. **No** comprueba la visibilidad del módulo; eso lo hace quien llama (`RecursoLeccionController:29`, `Show::alternarLeccionCompletada`).
  - `scopePublicadas`;
  - `seCompletaAlAbrir()`;
  - `completadaPor(User)`: una consulta por lección (posible N+1 si se usa en un bucle; hoy la vista usa `leccionesCompletadas` precargado).
  - `tipoRecurso()`.
- Relaciones: modulo, recursos, completadas.

### Tarea (`Tarea.php`, 187 líneas)
- `ESTADOS_ESTUDIANTE`: 6 estados. El ERS (RF-43) pide 5; se agrega "devuelto".
- Atributos por defecto: visible, publicado, 1 intento, individual.
- Casts: fechas, `peso_nota decimal:2`, `formatos_permitidos array`.
- Métodos:
  - `entregaDe(User)`; `estaVencida()`; `haIniciado()`; `esGrupal()`;
  - `formatosPermitidos()`: normaliza las extensiones;
  - `estadoPara(?Entrega)` (líneas 149–168): calcula el estado (RF-43);
  - `etiquetaEstado()`;
  - `puedeEliminarse()`: solo si no tiene entregas.
- **Hecho:** `estadoPara` usa `updated_at` (o `created_at`) de la entrega como momento de entrega. Cualquier modificación posterior de la fila que no sea calificar ni devolver (por ejemplo, el cambio de `grupo_id` en una reentrega grupal) cambia el "momento de entrega" y puede pasar la tarea a "fuera de plazo". No hay una columna `entregado_en`.

### Quiz (`Quiz.php`, 314 líneas)
- Formato de pregunta documentado en las líneas 15–22: el JSON **no** tiene ID de pregunta.
- Configuración: `intentosPermitidos()`, `tiempoLimiteMinutos()`, `aleatoriza()`, `muestraRetroalimentacion()`, `estaAbierto()`.
- Resultados: `resultadoDe(User)` (cuenta el mejor intento), `intentosUsados`, `quedanIntentos`.
- Corrección (estáticos): `correctasDe`, `respuestaEsCorrecta`, `puntajeDe`. `calcularPuntaje(array)` usa escala 0–20, ponderada por el puntaje de cada pregunta (líneas 203–224).
- RF-51/RF-52: `estudiantesConIntentos()`; `recalcularResultados()` (líneas 240–257), que recalcula todos los intentos y devuelve los que cambiaron.
- `puedeEliminarse()` y `estadisticasPorPregunta()`: tasa de error y distribución de opciones con el último intento de cada estudiante.

### BitacoraAuditoria (`BitacoraAuditoria.php`, 185 líneas)
- `$timestamps=false`; solo `created_at`, que se llena en `creating`.
- `updating` y `deleting` lanzan `LogicException` (líneas 78–84).
- `TIPOS`: 19 tipos.
- Métodos de creación: `registrarCambioNota(...)` y `registrarAccionAdmin(...)`; guardan `request()->ip()`.
- Auxiliares: `nota()` (texto sin ceros) y `serializar()` (JSON).
- Relaciones: ejecutadoPor, usuarioAfectado, curso.
- Fillable incluye `created_at`: se puede fechar un registro a mano (solo desde código).

### IntegracionExterna (`IntegracionExterna.php`, 105 líneas)
- Oculta `api_key_hash`.
- `generarApiKey()`: clave `LU_` más 40 caracteres aleatorios; guarda el hash bcrypt y el prefijo de 8 caracteres.
- `regenerarApiKey()` (también reactiva), `revocar()`, `verificarApiKey()` (`activo` y `Hash::check`), `registrarUso()`, relación `logs`.
- Hecho: el prefijo de 8 caracteres incluye `LU_`, así que solo 5 son aleatorios. Si dos integraciones compartieran prefijo, `ValidarApiKey` verifica el hash de ambas, así que no es un problema de seguridad.

### Inscripcion (`Inscripcion.php`, 92 líneas): Matrícula del ERS
- Sin timestamps.
- Métodos:
  - `estaActiva()`: `estado = activo` y `activo_local`;
  - `darDeBaja(User)`: baja lógica (RF-30);
  - `desactivarAccesoLocal(User)` (RF-32);
  - `esDeApiExterna()`.
- Relaciones: curso, estudiante, dadoDeBajaPor.

### Comunicado (`Comunicado.php`, 101 líneas)
- Scopes: `activos` (fecha de publicación alcanzada), `institucionales`, `delCurso`, `paraRol`.
- Métodos: `estaPublicado()`; `destinatarios()`, que carga **todos** los usuarios activos de los roles destinatarios en memoria.
- La misma tabla guarda los comunicados institucionales (`curso_id` null) y los anuncios de curso (RF-81).

### EsquemaEvaluacion, RubroEvaluacion, NotaRubro
- `EsquemaEvaluacion`: `pesosValidos()`, `sumaPesos()`, `estaBloqueado()` (campo nunca activado), relaciones curso y rubros (ordenados).
- `RubroEvaluacion`: casts `peso decimal:2`; `notaDeEstudiante(User)` (una consulta por llamada); relaciones esquema y notas.
- `NotaRubro`: cast `nota decimal:1`; relaciones rubro, estudiante, registradoPor.
- `EsquemaEvaluacion::pesosValidos` y `CalculadoraNotas::pesosSumanCien` duplican la misma regla. El código en uso llama a la calculadora; `pesosValidos()` no tiene llamadas en `app/` (código muerto).

### SesionClase (`SesionClase.php`, 62 líneas)
- `MINUTOS_ANTES_DE_UNIRSE=15`.
- `estaEnVivo()`: tiene enlace y la hora actual está entre 15 minutos antes y el final de la clase.
- `porcentajeAsistencia()` (2 consultas; cuenta presente y tardanza).
- Relaciones: curso, asistencias.

### PropuestaBot (`PropuestaBot.php`, 71 líneas)
- `TIPOS`: un solo tipo, `redistribucion_plan`. `TIPO_GENERICO`. `presentacion()`.
- Relaciones: usuario, aprobador.
- Comentarios con artefactos `[cite: 1]` (líneas 24 y 49, también en `ConversacionBot`).

### Concerns/TieneEstadoPublicacion (trait, 82 líneas)
- Usado por `Modulo`, `Tarea` y `Quiz`.
- `esVisibleParaEstudiante()`: publicado, o programado con fecha pasada; en tareas y quizzes también exige `visible` y un módulo visible.
- `scopeVisiblesParaEstudiante` (misma regla en SQL) y `etiquetaEstadoPublicacion()`.
- Diseño (hecho): el estado "borrador" de tareas y quizzes se guarda como `visible=false` con `estado_contenido='publicado'` (`Tareas/Formulario.php:120-121`). Hay dos campos para un mismo concepto.

## 2. Modelos sencillos (solo relaciones y casts)

| Modelo | Tabla | Relaciones | Particularidades |
|---|---|---|---|
| Asistencia | asistencias | sesion, estudiante, registradoPor | `badge()` devuelve la etiqueta y clases Tailwind (presentación dentro del modelo) |
| BancoPregunta | banco_preguntas | curso, creadoPor | `opcionesCorrectas()`; `aPreguntaDeQuiz()` convierte al formato del quiz con puntaje 1 |
| ConversacionBot | conversaciones_bot | usuario, curso | `agregarMensaje()` reescribe todo el JSON en cada mensaje |
| Entrega | entregas | tarea, estudiante, grupo | `enlaceArchivo()`; cast `nota decimal:2` |
| EventoCalendario | eventos_calendario | usuario | cast `fecha datetime` |
| Grupo | grupos | curso, estudiantes | — |
| LeccionCompletada | lecciones_completadas | leccion, estudiante | sin timestamps |
| LogIntegracionApi | logs_integracion_api | integracion | `created_at` en la zona de la plataforma |
| Mensaje | mensajes | remitente, destinatario, curso | `enlaceAdjunto()`, `estaLeido()`, `marcarComoLeido()` |
| RecursoLeccion | recursos_leccion | leccion | `TIPOS` (archivo y enlace); `icono()` |
| ResultadoQuiz | resultados_quiz | quiz, estudiante | cast `puntaje decimal:2` |

## 3. Métodos sin uso (código muerto)

Una búsqueda en `app/`, `resources/` y `database/` no encontró ninguna llamada a estos métodos:
- `EsquemaEvaluacion::pesosValidos()` y `sumaPesos()`;
- `RubroEvaluacion::notaDeEstudiante()`;
- `SesionClase::porcentajeAsistencia()`;
- `BancoPregunta::opcionesCorrectas()`;
- `PropuestaBotService::leerCalendario()`.

`Leccion::completadaPor()` solo se usa en pruebas (`CasosDeUsoTest.php:174,180`).

## 4. Factories (`database/factories`)

8 factories: User (estados `conPrimerLogin`, `inactivo`, `unverified`; el rol se asigna aparte), Curso (`borrador`, `archivado`), Inscripcion (`deApi`, `deBaja`), Entrega (`calificada`), Entrega, Inscripcion, Modulo, Quiz, ResultadoQuiz y Tarea. No hay factories para Leccion, Grupo, NotaRubro, Comunicado ni Mensaje; las pruebas crean esos registros con `create()`, por ejemplo en `PreparaEscenarioAcademico`.
