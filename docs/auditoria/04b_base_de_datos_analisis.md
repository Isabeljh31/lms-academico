# 04b. Base de datos: análisis

## 1. ¿Las migraciones son aditivas?

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Alguna migración elimina columnas o tablas en `up()` | No | Revisión de las 46 migraciones. `dropColumn` y `dropIfExists` solo aparecen en `down()`. |
| Alguna migración cambia el tipo de una columna existente | Una: `entregas.archivo_url` pasa de NOT NULL a nullable | `2026_09_26_054529_add_campos_contenido_y_evaluacion_ers.php:38` (`->change()`). Relaja una restricción y no pierde datos. |
| Alguna migración modifica datos existentes | Tres, solo rellenando columnas nuevas | `aviso_programado` (líneas 31–32: marca como ya avisado lo programado vencido); `semanas` (líneas 39–53: asigna `semana` y `orden` y fija `tipo_recurso` en "video"); ninguna borra filas |
| Migraciones ya ejecutadas que se editaron después | 11 (en el commit `3f413c0`), solo sangría | `git diff -w 6ca5c1e 3f413c0` no muestra diferencias |
| Reversibilidad | 45 de 46 tienen `down()` útil | `2026_09_06_042012_add_avatar_url_to_users_table.php:24-26`: `down()` vacío (no quita la columna) |
| Migraciones posteriores al 05/10/2026 | 2, ambas aditivas | `create_recursos_leccion_table` (tabla nueva); `add_enlace_reunion_to_sesiones_clase_table` (columna nullable) |

Riesgo de la migración de semanas (hecho): `2026_09_28_001808` reescribe `modulos.orden` de todos los cursos y crea el índice único `(curso_id, semana)`. Si en producción hay dos módulos de un mismo curso que reciban la misma semana, la creación del índice falla. Según el código no puede pasar: la fórmula `intdiv($indice * $semanas, $unidades) + 1` es estrictamente creciente cuando `$unidades <= $semanas`, y si no, cada unidad k va a la semana k. Además, la migración se ejecutó sin errores en la base local. No se pudo verificar contra los datos de Supabase.

## 2. Trigger de inmutabilidad de la bitácora (RF-99)

Archivo: `database/migrations/2026_09_26_202325_proteger_bitacora_auditoria_inmutable.php`.

| Aspecto | Hecho | Evidencia |
|---|---|---|
| Motores | Solo crea el trigger si el driver es `pgsql`. En SQLite (local y pruebas) no hace nada. | Líneas 21–23 |
| DELETE | Siempre rechazado | Líneas 28–30 |
| UPDATE de contenido | Rechazado si cambia `tipo_evento`, `valor_anterior`, `valor_nuevo`, `motivo`, `descripcion`, `created_at`, `entidad`, `entidad_id` o `ip` | Líneas 32–40 |
| UPDATE de claves foráneas | **Permitido** si la nueva FK es NULL. Es necesario para `ON DELETE SET NULL`, pero también permite que cualquier sesión de base de datos anonimice `ejecutado_por`, `curso_id` o `usuario_afectado_id` de un registro. | Líneas 41–43 |
| TRUNCATE | No está cubierto: el trigger es `FOR EACH ROW` y PostgreSQL no lo dispara con TRUNCATE | Línea 53 |
| Desactivación | El mismo usuario de base de datos que ejecuta las migraciones puede hacer `DROP TRIGGER`. No hay separación de privilegios. | No verificado en Supabase |
| Compatibilidad con el pooler de Supabase (puerto 6543, modo transacción) | Por análisis, compatible: `DB::unprepared` envía todo como consulta simple y `config/database.php` activa `PDO::ATTR_EMULATE_PREPARES => true`, lo que evita los errores de *prepared statements* con PgBouncer. | `config/database.php` (sección `pgsql`, opción `options`). **No verificado en ejecución.** |
| Cobertura de pruebas | **Ninguna.** Las pruebas corren en SQLite, donde el trigger no existe. `AuditoriaTest` (líneas 33 y 41) solo prueba los eventos `updating` y `deleting` del modelo. | `tests/Feature/Seguridad/AuditoriaTest.php` |

Opinión: en SQLite y en cualquier código que use el *query builder* (`DB::table('bitacoras_auditoria')->update(...)`), la inmutabilidad depende solo de que nadie escriba ese código. Hoy no existe en `app/`: se verificó con una búsqueda de `bitacoras_auditoria` en `app/` (solo aparece la propiedad `$table` del modelo).

## 3. Campos JSON

| Tabla.columna | Tipo | Uso | Riesgo |
|---|---|---|---|
| `quizzes.preguntas` | jsonb | Lista de preguntas con enunciado, tipo, opciones, respuesta(s) correcta(s), puntaje y retroalimentación | Las preguntas **no tienen ID estable**: se identifican por su posición. Ver § 5, punto 2. |
| `resultados_quiz.respuestas` | jsonb | Respuesta por índice de pregunta | Se ligan a las preguntas por posición |
| `quizzes.configuracion` | jsonb | tiempo_limite_min, intentos, aleatorizar, mostrar_retroalimentacion | Sin esquema en la base; lo normalizan los métodos de `Quiz` |
| `tareas.formatos_permitidos` | jsonb | Extensiones permitidas | Se filtran contra la lista blanca (`SubidorArchivos::extensionesPermitidas`) |
| `banco_preguntas.opciones` y `respuestas_correctas` | json | Pregunta reutilizable | Se **copia** al quiz (`BancoPregunta::aPreguntaDeQuiz`). Editar el banco no cambia los quizzes. |
| `modulos.materiales` | json | Lista de materiales `{nombre, url}` de la semana | El comando `archivos:privatizar` también recorre esta lista |
| `conversaciones_bot.mensajes` | jsonb | Historial del chat | Crece sin límite por usuario y curso (solo se envían los últimos 10 al proveedor) |
| `propuestas_bot.datos_propuesta` | jsonb | Datos de la propuesta | Búsqueda de duplicados en PHP, no en SQL (`PropuestaBotService.php:68-72`) |
| `logs_integracion_api.payload_recibido` | jsonb | Cuerpo de la solicitud, sin `password` ni `api_key` | Guarda datos personales (nombre y correo) sin plazo de retención |
| `configuraciones_plataforma.valor` | text con JSON | Valores de configuración | — |

`jsonb` en SQLite se guarda como texto. Los casts `array` hacen que el comportamiento sea igual en ambos motores.

## 4. Índices y restricciones

| Restricción | Dónde |
|---|---|
| Únicos | `users.email`; `cursos.codigo`; `inscripciones (curso_id, estudiante_id)`; `modulos (curso_id, semana)`; `esquemas_evaluacion.curso_id`; `notas_rubro (rubro_id, estudiante_id)`; `asistencias (sesion_id, estudiante_id)`; `grupo_estudiante (grupo_id, estudiante_id)`; `curso_profesor (curso_id, profesor_id)`; `lecciones_completadas (leccion_id, estudiante_id)`; `configuraciones_plataforma.clave` |
| Índices adicionales | `mensajes (destinatario_id, leido_at)`; `banco_preguntas (curso_id, tema)`; `notifications` (morph); `sessions.user_id`, `sessions.last_activity`; tablas de cola y caché |
| Faltan (opinión) | `entregas (tarea_id, estudiante_id)` único: hoy se pueden crear duplicados si se omite `updateOrCreate`. Sin índice en `entregas.estudiante_id`, `tareas.curso_id`, `resultados_quiz (quiz_id, estudiante_id)` ni `bitacoras_auditoria.created_at`. En PostgreSQL, `foreignId()->constrained()` no crea índice. |

Borrado en cascada (hecho): `inscripciones`, `entregas`, `notas_rubro`, `asistencias` y `resultados_quiz` tienen `onDelete cascade` hacia `users`. Eliminar físicamente un usuario borraría su historial académico. La aplicación no ofrece borrar usuarios (no hay ninguna llamada a `->delete()` sobre `User` en `app/`); solo los desactiva (RF-17). La protección depende de la aplicación, no de la base de datos.

## 5. Puntos específicos solicitados

### Punto 1: ¿existen "Oferta de Curso" y "Periodo Académico"?

- El ERS v2 **no** define esas entidades: la sección 6 lista CURSO con "profesor asignado, fechas, cantidad de Sesiones de Clase, duración en semanas", y cada ciclo se obtiene clonando el curso (RF-20).
- En el código no existen tablas ni modelos `ofertas` o `periodos`. El comentario de `app/Models/Curso.php:16` dice: "Curso del ERS (equivale a 'Oferta de Curso' en versiones previas del documento)". Es decir, se fusionaron en `cursos` (fechas en `fecha_inicio` y `fecha_fin`, estado en `estado`).
- "Periodo finalizado" = curso con `estado = 'archivado'` (`Curso::estaFinalizado`, `Curso.php:77-80`).
- Cómo se garantiza la inmutabilidad: con comprobaciones en la aplicación, no en la base de datos.
  - `EsquemaEvaluacion::editable` (`Cursos/EsquemaEvaluacion.php:51-54`).
  - `LibroCalificaciones::puedeCalificar` (`:71-74`).
  - `Show::asegurarCursoEditable` (`:491-494`).
  - Formularios de tareas y quizzes (`abort_if(estaFinalizado)`).
  - `Lecciones/Index::autorizarEdicion`, `Asistencia/Index::editable`, `Grupos/Index::autorizar`.
  - `GestorMatriculas` (`:154, :179`), `EntregaPolicy::calificar` y `crear`, `QuizPolicy::responder`.
- **Debilidad confirmada por ejecución**: el Supervisor puede **reabrir** un curso archivado cambiando su estado a publicado (`Cursos/Formulario.php:190-213`, `guardarSoloEstado`). Desde ese momento todas las restricciones anteriores dejan de aplicarse y se pueden cambiar notas y esquema del periodo anterior. La reapertura queda en la bitácora (`modificacion_curso`), pero no hay un estado irreversible. Prueba temporal `test_h_supervisor_reabre_curso_archivado`: pasa. Ver H-03 en el archivo 17.
- `esquemas_evaluacion.bloqueado` existe, pero ningún código lo pone en true (búsqueda en `app/`: solo se lee, y `Curso::clonar` lo crea en false). Es un campo muerto.

### Punto 10: tablas o migraciones agregadas después del 5 de octubre de 2026

| Migración | Qué agrega | ¿Aditiva? | Estado |
|---|---|---|---|
| `2026_10_05_192827_create_recursos_leccion_table` | Tabla `recursos_leccion` | Sí | Ejecutada en la base local (lote 7); sin commitear; no ejecutada en producción (no verificable) |
| `2026_10_05_194327_add_enlace_reunion_to_sesiones_clase_table` | Columna `sesiones_clase.enlace_reunion`, nullable | Sí | Ejecutada en la base local (lote 8); sin commitear |

## 6. Cobertura del modelo de la sección 6 del ERS

| Entidad del ERS | Implementación | Estado |
|---|---|---|
| Usuario, Estudiante, Profesor, Supervisor | `users` + roles de Spatie (no hay tablas separadas por rol) | Cumple |
| Curso (profesor, fechas, sesiones, semanas) | `cursos` (+ `curso_profesor`) | Cumple |
| Módulo | `modulos` (hoy = semana o Prácticas de campo) | Cumple, con el nombre cambiado en la interfaz |
| Lección | `lecciones` | Cumple |
| Recurso | `recursos_leccion`, además de `lecciones.video_url` y `material_url` y `modulos.materiales` | Cumple (sin commitear). Hay 3 lugares distintos donde guardar recursos (opinión: redundante). |
| Tarea | `tareas` | Cumple |
| Quiz | `quizzes` | Cumple |
| Pregunta, Respuesta | Dentro de `quizzes.preguntas` (JSON) y `banco_preguntas` | Desviación: no son tablas. Funciona, pero sin ID estable (§ 3). |
| Sesión de clase, Asistencia | `sesiones_clase`, `asistencias` | Cumple |
| Matrícula (origen manual o API) | `inscripciones.origen` | Cumple |
| Entrega | `entregas` | Cumple |
| Resultado de quiz | `resultados_quiz` | Cumple |
| Esquema de evaluación, Rubro (semana, peso), Nota de rubro (0–20) | `esquemas_evaluacion`, `rubros_evaluacion`, `notas_rubro` | Cumple |
| Grupo | `grupos`, `grupo_estudiante` | Cumple |
| Mensaje, Notificación, Comunicado, Evento de calendario | `mensajes`, `notifications`, `comunicados`, `eventos_calendario` | Cumple |
| Bitácora de auditoría (usuario, acción, fecha, anterior, nuevo, motivo) | `bitacoras_auditoria` | Cumple |
| Configuración general | `configuraciones_plataforma` | Cumple |
| Integración externa (empresa cliente, API Key) | `integraciones_externas`, `logs_integracion_api` | Cumple |
| Fuera de alcance: pago, transacción, cupón, certificado, reseña | No existen | Cumple |

Tablas que no están en el ERS: `conversaciones_bot`, `propuestas_bot` (asistente de IA), `lecciones_completadas` (progreso, RF-66), `banco_preguntas` (RF-47), `curso_profesor` (RF-23) y las de infraestructura. Todas corresponden a algún RF.

## 7. Datos de la base local (consulta de solo lectura, 13/10/2026)

| Métrica | Valor |
|---|---|
| Cursos | 9 |
| Usuarios | 30 |
| Módulos (semanas y prácticas) | 28; 1 es de Prácticas de campo |
| Módulos con título heredado "Unidad N: …" | 27 de 28 |
| Lecciones con descripción que menciona "unidad" | 37 de 58 (ejemplo: `Lectura y video de la unidad "Límites y continuidad": Idea intuitiva de límite.`) |
| Tareas con descripción que menciona "unidad" | 23 de 26 (ejemplo: `Resuelve los ejercicios de la unidad 1.`) |
| Entregas con referencia `privado://` | 0 de 71. Todas son URLs públicas: los datos de ejemplo usan imágenes de `ui-avatars.com`. |
| Registros en la bitácora | 40 |
| Esquemas con `bloqueado = 1` | 0 |
| Semanas fuera de la duración del curso | 0 |

Conclusión del punto 9 (textos autogenerados): la base local se sembró con una versión anterior del seeder, por eso conserva los nombres "Unidad N" y descripciones como "…de la unidad…". El seeder actual (`DemoAcademicoSeeder.php:323, 343, 520`) genera textos plantilla con la palabra "semana" ("Material de la semana…", "Actividad de práctica de la Semana N…"). `CursoSeeder.php:33` sigue creando módulos "Unidad 1/2/3". En la interfaz, `Modulo::tema()` (`Modulo.php:96-101`) quita el prefijo "Unidad N:", así que el estudiante ve "Semana 1: Introducción".
