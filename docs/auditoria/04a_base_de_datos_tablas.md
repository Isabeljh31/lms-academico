# 04a. Base de datos: tablas

Estado final de cada tabla, reconstruido desde las 46 migraciones de `database/migrations/`. Tipos de Laravel: `id` es bigint autoincremental; `foreignId` es bigint con clave foránea. "Nulo: Sí" significa nullable. Las columnas agregadas por una migración posterior a la que creó la tabla indican esa migración entre paréntesis (se citan por la parte final del nombre).

Abreviaturas de las migraciones que agregan columnas:
- `ext` = `2026_09_25_170007_add_campos_extendidos_tareas_quizzes`
- `ers` = `2026_09_26_054529_add_campos_contenido_y_evaluacion_ers`
- `aviso` = `2026_09_26_235911_add_aviso_programado_a_contenido`
- `compl` = `2026_09_27_000557_add_condicion_completitud_a_modulos_y_lecciones`
- `sem` = `2026_09_28_001808_add_semanas_y_tipo_recurso_a_contenido`

## 1. Usuarios y acceso

### users
Creada en `0001_01_01_000000_create_users_table`. Modelo: `User`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | PK |
| name | string | No | Nombre completo (no hay columnas separadas de nombres y apellidos) |
| email | string | No | Único |
| email_verified_at | timestamp | Sí | `GestorCuentas` la llena al crear la cuenta |
| password | string | No | Hash bcrypt (cast `hashed`) |
| remember_token | string(100) | Sí | |
| avatar_url | text | Sí | `add_avatar_url_to_users`; guarda una referencia `privado://` o una URL antigua |
| activo | boolean | No | Por defecto true (`add_auth_fields`, RF-15) |
| primer_login | boolean | No | Por defecto true (RF-05) |
| bloqueado_hasta | timestamp | Sí | RF-06 |
| intentos_fallidos | unsignedTinyInteger | No | Por defecto 0 |
| created_at, updated_at | timestamp | Sí | |

`password_reset_tokens` (email PK, token, created_at) y `sessions` (id PK, user_id con índice, ip_address, user_agent, payload, last_activity con índice) son tablas estándar de Laravel en la misma migración.

### roles, permissions, model_has_roles, model_has_permissions, role_has_permissions
Creadas en `2026_09_06_045335_create_permission_tables` (spatie/laravel-permission). `roles` y `permissions` tienen únicos `(name, guard_name)`. Las pivotes tienen PK compuesta y FK con `cascadeOnDelete`. Solo se usan roles (`supervisor`, `profesor`, `estudiante`). No hay permisos granulares registrados: `RoleAndUserSeeder` crea solo los roles.

## 2. Cursos y contenido

### cursos
Creada en `2026_09_06_042018_create_cursos_table`. Modelo: `Curso`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | PK |
| nombre | string | No | |
| codigo | string(50) | Sí | Único (`ers`). Lo usa la API para identificar el curso. |
| descripcion | text | Sí | |
| profesor_id | foreignId → users | No | `onDelete restrict`. Profesor titular. |
| imagen_portada_url | text | Sí | Referencia privada |
| estado | string(20) | No | borrador, publicado, despublicado o archivado (`add_estado_fechas`) |
| fecha_inicio, fecha_fin | date | Sí | |
| semanas_duracion | unsignedSmallInteger | Sí | Número de semanas del contenido |
| sesiones_clase_count | unsignedSmallInteger | Sí | RF-39, independiente de las semanas |
| usa_asistencia | boolean | No | Por defecto true (`ers`, RF-73) |
| created_at, updated_at | timestamp | Sí | |

### curso_profesor
Creada en `2026_09_26_234343`. Pivote de profesores adicionales: id, curso_id (FK, cascade), profesor_id (FK a users, cascade), timestamps, único `(curso_id, profesor_id)`. Relación `Curso::profesoresAdicionales`.

### modulos (cada fila es una semana o el bloque Prácticas de campo)
Creada en `2026_09_06_042031_create_modulos_table`. Modelo: `Modulo`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | PK |
| curso_id | foreignId → cursos | No | cascade |
| titulo | string | No | Tema de la semana |
| orden | integer | No | Igual a la semana; 0 para Prácticas de campo |
| materiales | json | Sí | Lista `{nombre, url}` (`add_materiales`) |
| estado_contenido | string(20) | No | Por defecto "publicado" (`ers`) |
| publicar_en | timestamp | Sí | `ers` |
| aviso_programado_en | timestamp | Sí | `aviso` |
| condicion_completitud | string(30) | No | Por defecto "actividades" (`compl`) |
| tipo_seccion | string(20) | No | "semana" o "practicas_campo" (`sem`) |
| semana | unsignedSmallInteger | Sí | Null en Prácticas de campo (`sem`) |
| created_at, updated_at | timestamp | Sí | |

Índice único `(curso_id, semana)` (`sem`). En PostgreSQL y SQLite, los NULL no chocan en un índice único, así que el bloque de Prácticas de campo no lo viola. La aplicación garantiza un único bloque de Prácticas por curso con `firstOrCreate` (`Cursos/Show.php:321`), no la base de datos.

### lecciones
Creada en `2026_09_26_054524`. Modelo: `Leccion`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | |
| modulo_id | foreignId → modulos | No | cascade |
| titulo | string | No | |
| descripcion | text | Sí | |
| video_url | string(500) | Sí | |
| material_url | text | Sí | URL o referencia privada |
| material_nombre | string | Sí | |
| estado | string(20) | No | Por defecto "borrador". Distinto de `estado_contenido`, que usan las otras tablas. |
| fecha_publicacion | timestamp | Sí | |
| orden | unsignedInteger | No | |
| aviso_programado_en | timestamp | Sí | `aviso` |
| condicion_completitud | string(30) | No | "marcar" o "abrir_recurso" (`compl`) |
| tipo_recurso | string(30) | No | Por defecto "lectura" (`sem`) |
| created_at, updated_at | timestamp | Sí | |

### recursos_leccion
Creada en `2026_10_05_192827`, sin commitear. Modelo: `RecursoLeccion`. Columnas: id, leccion_id (FK, cascade), tipo string(20) ("archivo" o "enlace"), nombre string, url text, orden unsignedSmallInteger, timestamps.

### lecciones_completadas
Creada en `2026_09_26_054530`. Columnas: id, leccion_id (FK, cascade), estudiante_id (FK a users, cascade), completada_en timestamp (por defecto ahora). Único `(leccion_id, estudiante_id)`. Sin timestamps de Laravel.

## 3. Actividades

### tareas
Creada en `2026_09_06_042037`. Modelo: `Tarea`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | |
| curso_id | foreignId → cursos | No | cascade |
| modulo_id | foreignId → modulos | No | cascade |
| titulo | string | No | |
| descripcion | text | Sí | |
| instrucciones | text | Sí | `ext` |
| fecha_inicio | timestamp | Sí | `ext` |
| fecha_entrega | timestamp | No | Fecha límite |
| peso_nota | decimal(5,2) | No | Por defecto 1.00; "puntaje" de RF-40 |
| visible | boolean | No | Por defecto true |
| intentos_permitidos | unsignedTinyInteger | No | Por defecto 1 (`ext`) |
| formatos_permitidos | jsonb | Sí | RF-41 (`ext`) |
| estado_contenido | string(20) | No | Por defecto "publicado" (`ext`) |
| publicar_en | timestamp | Sí | `ext` |
| tipo_entrega | string(20) | No | "individual" o "grupal" |
| grupo_asignado_id | foreignId → grupos | Sí | `nullOnDelete` (`ers`) |
| aviso_programado_en | timestamp | Sí | `aviso` |
| created_at, updated_at | timestamp | Sí | |

### entregas
Creada en `2026_09_06_042043`. Modelo: `Entrega`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | |
| tarea_id | foreignId → tareas | No | cascade |
| estudiante_id | foreignId → users | No | cascade |
| grupo_id | foreignId → grupos | Sí | `nullOnDelete` |
| archivo_url | text | Sí | Era NOT NULL; `ers` la cambió a nullable con `change()` |
| respuesta_texto | text | Sí | `ers` |
| enlace | string(500) | Sí | `ers` |
| comentario | text | Sí | |
| estado | string(20) | No | Por defecto "entregado"; también "devuelto" y "calificado" |
| intentos_usados | unsignedTinyInteger | No | Por defecto 1 (`ers`) |
| nota | decimal(4,2) | Sí | 0–20 |
| feedback | text | Sí | |
| created_at, updated_at | timestamp | Sí | `updated_at` se usa como "momento de entrega" (ver archivo 05) |

No hay índice único `(tarea_id, estudiante_id)`. La unicidad depende de `updateOrCreate` en `Tareas/Detalle.php:165`.

### quizzes
Creada en `2026_09_06_042049`. Modelo: `Quiz`. Columnas:
- id; curso_id y modulo_id (FK, cascade); tema string;
- preguntas jsonb (NOT NULL);
- configuracion jsonb nullable (`ext`);
- fecha_inicio y fecha_limite timestamps (`ext`);
- visible boolean (por defecto true);
- estado_contenido string(20), publicar_en timestamp (`ext`);
- aviso_programado_en (`aviso`); timestamps.

### resultados_quiz
Creada en `2026_09_06_042055`. Modelo: `ResultadoQuiz`. Columnas:
- id; quiz_id (FK, cascade); estudiante_id (FK a users, cascade);
- intento unsignedTinyInteger (por defecto 1, `ers`);
- respuestas jsonb; puntaje decimal(4,2) (escala 0–20);
- tiempo_usado_seg unsignedInteger nullable (`ers`); timestamps.

### banco_preguntas
Creada en `2026_09_26_054527`. Modelo: `BancoPregunta`. Columnas:
- id; curso_id (FK, cascade); tema string(150); pregunta text;
- tipo string(30); opciones json; respuestas_correctas json;
- explicacion text nullable; dificultad string(20) nullable;
- creado_por (FK a users, `nullOnDelete`); timestamps.
- Índice `(curso_id, tema)`.

## 4. Matrícula, grupos y asistencia

### inscripciones (Matrícula del ERS)
Creada en `2026_09_06_042024`. Modelo: `Inscripcion`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | |
| curso_id | foreignId → cursos | No | cascade |
| estudiante_id | foreignId → users | No | cascade |
| fecha_inscripcion | timestamp | No | Por defecto ahora |
| origen | string(20) | No | "manual" o "api" (`add_matricula_fields`) |
| estado | string(20) | No | "activo" o "inactivo" |
| activo_local | boolean | No | Por defecto true (RF-32) |
| fecha_baja | timestamp | Sí | |
| dado_de_baja_por | unsignedBigInteger → users | Sí | `nullOnDelete` |

Único `(curso_id, estudiante_id)`. Sin timestamps de Laravel.

### grupos y grupo_estudiante
- `grupos` (`2026_09_17_224623`): id, curso_id (FK, cascade), nombre, timestamps.
- `grupo_estudiante` (`2026_09_17_224624`): id, grupo_id (FK, cascade), estudiante_id (FK, cascade), timestamps, único `(grupo_id, estudiante_id)`.
- La regla "un estudiante pertenece a un solo grupo por curso" no la impone la base de datos; la impone `Grupos/Index::asignar` (líneas 116–123).

### sesiones_clase y asistencias
Creadas en `2026_09_26_054525`.
- `sesiones_clase`:
  - id; curso_id (FK, cascade); titulo; fecha datetime;
  - duracion_minutos (por defecto 90); descripcion nullable; numero_sesion;
  - enlace_reunion string(500) nullable (`2026_10_05_194327`, sin commitear); timestamps.
- `asistencias`: id; sesion_id (FK, cascade); estudiante_id (FK, cascade); estado string(20) (por defecto "ausente"); observacion string(500) nullable; registrado_por (FK, `nullOnDelete`); timestamps; único `(sesion_id, estudiante_id)`.

## 5. Evaluación

### esquemas_evaluacion, rubros_evaluacion, notas_rubro
Creadas en `2026_09_25_170005`.
- `esquemas_evaluacion`: id, curso_id (FK, cascade, **único**: un esquema por curso), bloqueado boolean (por defecto false), timestamps.
- `rubros_evaluacion`: id, esquema_id (FK, cascade), nombre string(100), semana unsignedTinyInteger nullable, peso decimal(5,2), orden unsignedTinyInteger, timestamps.
- `notas_rubro`: id, rubro_id (FK, cascade), estudiante_id (FK, cascade), nota decimal(4,1) nullable, registrado_por (FK, `nullOnDelete`), único `(rubro_id, estudiante_id)`, timestamps.

`rubros_evaluacion.semana` es unsignedTinyInteger (máximo 255). La validación permite hasta `semanas_duracion` o 52, y la duración admite hasta 104 (`Cursos/Formulario.php:109`). No hay conflicto práctico, porque 104 es menor que 255.

## 6. Comunicación

| Tabla | Migración | Columnas |
|---|---|---|
| mensajes | `2026_09_26_054526` | id; remitente_id y destinatario_id (FK, cascade); curso_id (FK, `nullOnDelete`); asunto; cuerpo text; archivo_url text nullable; leido_at nullable; timestamps; índice `(destinatario_id, leido_at)` |
| comunicados | `2026_09_18_143000` y siguientes | id; user_id (FK, cascade); curso_id (FK, cascade, nullable: null = institucional); titulo; contenido text; prioridad string(20); destinatario string(20); activo boolean; publicar_en nullable (`ers`); notificado boolean (`ers`); timestamps |
| notifications | `2026_09_16_030403` | uuid PK; type; notifiable_type y notifiable_id (índice morph); data text; read_at nullable; timestamps |
| eventos_calendario | `2026_09_06_042101` | id; usuario_id (FK, cascade); titulo; fecha timestamp; tipo string(30); timestamps |

## 7. Asistente de IA

| Tabla | Migración | Columnas |
|---|---|---|
| conversaciones_bot | `2026_09_06_042109` | id; usuario_id (FK, cascade); curso_id (FK, `set null`); mensajes jsonb (por defecto `[]`); timestamps |
| propuestas_bot | `2026_09_06_043423` | id; usuario_id (FK, cascade); tipo string(50); datos_propuesta jsonb; estado string(20) (por defecto "pendiente"); aprobado_por (FK a users, sin acción de borrado); fecha_respuesta nullable; timestamps |

## 8. Administración e integración

### bitacoras_auditoria
Creada en `2026_09_25_170004`. Modelo: `BitacoraAuditoria`.

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| id | id | No | |
| ejecutado_por | bigint → users | Sí | `nullOnDelete`. Null = sistema externo (API). |
| tipo_evento | string(50) | No | 19 tipos en `BitacoraAuditoria::TIPOS` |
| curso_id | bigint → cursos | Sí | `nullOnDelete` |
| usuario_afectado_id | bigint → users | Sí | `nullOnDelete` |
| entidad, entidad_id | string(100), bigint | Sí | |
| valor_anterior, valor_nuevo, motivo, descripcion | text | Sí | |
| ip | string(45) | Sí | |
| created_at | timestamp | No | Por defecto ahora. No hay `updated_at`. |

Sin índices propios más allá de la PK y de las columnas FK. En PostgreSQL las FK no crean índice automáticamente; ver archivo 14.

### configuraciones_plataforma
Creada en `2026_09_26_054528`. Columnas: id, clave string(100) única, valor text (JSON), timestamps. Sin modelo Eloquent: la usa el servicio `ConfiguracionPlataforma` con `DB::table`.

### integraciones_externas y logs_integracion_api
Creadas en `2026_09_25_170006`.
- `integraciones_externas`: id, nombre string(150), api_key_hash string(255), api_key_prefix string(12), activo boolean, descripcion nullable, ultimo_uso nullable, timestamps.
- `logs_integracion_api`: id, integracion_id (FK, `nullOnDelete`), ip, metodo, endpoint, status_code integer, payload_recibido jsonb nullable, error_mensaje text nullable, created_at.

## 9. Infraestructura de Laravel

`cache`, `cache_locks` (`0001_01_01_000001`); `jobs`, `job_batches`, `failed_jobs` (`0001_01_01_000002`). Son estándar y no tienen modelo propio. La cola `database` usa `jobs`.
