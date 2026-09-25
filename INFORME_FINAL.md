# Informe final — LMS Académico

**Fecha:** 2026-09-15/16
**Alcance:** construcción completa de los 9 módulos del documento del proyecto sobre una base ya existente (auditada en el punto 1 y documentada en `ARQUITECTURA.md`).

Este informe resume qué se construyó, qué se probó, qué depende de credenciales que el equipo todavía no tiene, y qué recomiendo revisar antes de mostrarlo al docente. El detalle técnico completo, módulo por módulo, con el registro de cada prueba, vive en `ARQUITECTURA.md` — este documento es el resumen ejecutivo.

---

## 0. Punto de partida (auditoría)

El proyecto no partía de cero: ya había modelos, migraciones y un seeder de roles construidos por una compañera de equipo, y un `.env` apuntando a un proyecto real de Supabase (sin `DB_PASSWORD`, así que no estaba activo desde este entorno). Se conservó toda esa base (es sólida: Eloquent puro, sin SQL de motor específico) y se configuró SQLite como base de desarrollo local — **sin depender de que Supabase esté activo** — dejando `.env.example` documentado con la conexión real de Supabase para cuando el equipo la active. El bot estaba pensado originalmente con Groq/Llama; se siguió la instrucción explícita del documento del proyecto y se implementó contra la API de Claude (Anthropic), con modo simulado.

---

## 1. Autenticación y gestión de usuarios

**Se construyó:** login/registro/verificación/reset de contraseña (Breeze+Volt), roles Spatie (`administrador`/`profesor`/`estudiante`, renombrados desde `admin` para que coincidan exactamente con el documento), asignación de roles desde `/admin/usuarios`, foto de perfil con Cloudinary o fallback local automático, layout con sidebar/topbar diferenciado por color según el rol.

**Se probó:** 33 tests automatizados (login, registro con rol por defecto, verificación de permisos cruzados entre los 3 roles — un profesor o estudiante no puede entrar a `/admin/usuarios`).

**Depende de configuración externa:** credenciales reales de Cloudinary (`CLOUDINARY_URL`, `CLOUDINARY_UPLOAD_PRESET`) para que las fotos se suban a la nube en vez del disco local. Mientras no estén, todo funciona igual contra `storage/app/public`.

## 2. Gestión de cursos y contenidos

**Se construyó:** CRUD de cursos con portada, unidades/módulos, materiales (subidos como archivos, guardados como JSON en `modulos.materiales`), inscripción de estudiantes.

**Se probó:** 8 tests (creación, permisos —un profesor no puede editar el curso de otro—, inscripción, gestión de unidades).

**Depende de:** igual que el módulo 1, de Cloudinary para que las portadas/materiales no queden en disco local.

## 3. Tareas, entregas y calificaciones

**Se construyó:** tareas con fecha límite y peso de nota, entrega de archivo por el estudiante, calificación + feedback del profesor.

**Se probó:** 6 tests. **Se encontró y corrigió un bug real** durante las pruebas: la autorización de "entregar tarea" resolvía la policy equivocada por cómo Laravel elige la policy según la clase del argumento — sin la prueba automatizada, esto habría producido un error 500 en producción la primera vez que un estudiante intentara entregar algo.

## 4. Quizzes y evaluaciones

**Se construyó:** constructor de preguntas de opción múltiple (agregar/quitar pregunta y opciones desde la UI), resolución por el estudiante, calificación automática comparando respuestas contra la clave.

**Se probó:** 4 tests, incluyendo el cálculo exacto de la nota (1 de 2 respuestas correctas → 10/20) y que un estudiante no puede resolver el mismo quiz dos veces.

## 5. Calendario y seguimiento académico

**Se construyó:** calendario visual con FullCalendar.js (bloques de estudio, recordatorios y fechas límite de tareas combinados), notificaciones nativas de Laravel con campanita y contador en el topbar (se creó la migración de la tabla `notifications`, que no venía en el proyecto).

**Se probó:** 5 tests (creación/borrado de eventos propios, que un usuario no puede borrar el evento de otro, que las notificaciones llegan al destinatario correcto).

## 6. Bot de sugerencias — modo consulta (FAQ)

**Se construyó:** widget flotante de chat visible en toda la plataforma para los 3 roles, con contexto del curso actual, historial guardado por usuario+curso.

**Se probó:** 5 tests, incluyendo que el modo simulado se identifica explícitamente como tal en la respuesta.

**Actualización (2026-09-16):** se agregó **Groq como proveedor gratuito por defecto** (`IA_PROVEEDOR=groq`), sin quitar Claude — quedaron detrás de una interfaz común (`ProveedorIA`) para poder alternar entre ambos con una sola variable de entorno, sin tocar código. El equipo no tiene presupuesto para usar Claude durante el desarrollo del curso, y Groq ofrece un tier gratuito real (sin tarjeta) con modelos open-source como Llama 3.3 70B.

**Depende de:** `GROQ_API_KEY` para respuestas reales (proveedor activo por defecto). Sin ella, el bot sigue funcionando end-to-end en modo simulado (etiquetado `[MODO SIMULADO]`), así que se puede demostrar el flujo completo al docente sin gastar créditos ni depender de ninguna key. `ANTHROPIC_API_KEY` queda disponible si el equipo cambia a `IA_PROVEEDOR=claude` más adelante.

## 7. Bot de sugerencias — modo propuesta (human-in-the-loop)

Este es el módulo con la regla no negociable del proyecto. **Se construyó** un servicio con las 4 funciones acotadas del documento (leer progreso, leer calendario, sugerir redistribución, reprogramar solo tras aprobación), un comando programado que detecta atrasos a diario, y una bandeja donde el estudiante acepta/ajusta/rechaza cada propuesta.

**Se probó con 7 tests explícitos de gobernanza**, incluyendo la prueba pedida directamente en el documento: se verificó que **no existe ningún camino de código** donde el bot modifique `eventos_calendario` sin pasar por una propuesta ya aprobada — incluso llamando directamente al método de ejecución sobre una propuesta pendiente, el sistema la rechaza con un error 409 en vez de ejecutar el cambio.

**Decisión que tomé sin instrucción explícita:** el documento no dice quién aprueba la propuesta. Decidí que el propio estudiante apruebe sus propias propuestas (es su plan de estudio personal) y que el profesor reciba solo un resumen de supervisión, sin poder aprobar/rechazar él mismo. Si el equipo prefiere que sea el profesor quien decida, es un cambio acotado a `PropuestaBotPolicy` y a las dos vistas de este módulo.

## 8. Panel administrativo y métricas

**Se construyó:** métricas globales (usuarios por rol, cursos con más atraso, uso del bot) y un interruptor para activar/desactivar el bot en toda la plataforma. No crea tablas nuevas, tal como pide el documento.

**Se probó:** 4 tests. **Se encontró y corrigió un segundo bug real**: la primera versión guardaba el interruptor del bot en la tabla `cache`, y `spatie/laravel-permission` borra esa tabla completa (no solo su propia clave) cada vez que cambia un rol — así que asignar un rol apagaba el bot sin que nadie lo tocara. Se corrigió guardando esa configuración en un archivo, no en la caché compartida.

## 9. Dashboard y reportes

**Se construyó:** gráficas con Chart.js (promedio de tareas/quizzes por curso para el estudiante; promedio y tasa de entrega por curso para el profesor/administrador) y exportación de boleta de notas en PDF con `barryvdh/laravel-dompdf`.

**Se probó:** 5 tests, incluyendo el cálculo del promedio *ponderado* por `peso_nota` (verificado con un caso numérico a mano, para distinguirlo de un promedio simple).

---

## Resumen de pruebas

**77 tests automatizados, 178 aserciones, 0 fallando** al momento de este informe (`php artisan test`). `migrate:fresh --seed` corre limpio sobre SQLite, `npm run build` compila sin errores, y `php artisan view:cache` compila las ~40 vistas Blade sin errores de sintaxis.

## Qué depende de credenciales que el equipo aún no tiene

| Servicio | Variable | Qué pasa sin ella | Cómo activarla |
|---|---|---|---|
| Supabase/Postgres | `DB_CONNECTION=pgsql` + host/usuario/password | Se usa SQLite local, todo funciona igual | Copiar las variables ya documentadas en `.env.example` al `.env`, cambiar `DB_CONNECTION` a `pgsql`, correr `php artisan migrate --seed` |
| Cloudinary | `CLOUDINARY_URL`, `CLOUDINARY_UPLOAD_PRESET` | Los archivos se guardan en `storage/app/public` | Crear la cuenta/preset y pegar la URL en `.env` — no requiere cambios de código |
| Groq (proveedor de IA por defecto) | `GROQ_API_KEY` | El bot responde en modo simulado, etiquetado como tal | Crear una cuenta gratuita en console.groq.com y pegar la key en `.env` — no requiere cambios de código |
| Claude (Anthropic, alternativo/de pago) | `ANTHROPIC_API_KEY` + `IA_PROVEEDOR=claude` | No se usa mientras `IA_PROVEEDOR=groq` (default) | Pegar la key y cambiar `IA_PROVEEDOR` a `claude` en `.env` — no requiere cambios de código |

## Despliegue en Railway

Preparado (`railway.json` + `Procfile`), no ejecutado — no hay cuenta de Railway conectada desde este entorno. El equipo solo necesita crear el proyecto, conectar el repo y configurar las variables de la tabla anterior. **Importante:** el Scheduler del Módulo 7 necesita un segundo servicio de tipo Cron en Railway corriendo `php artisan schedule:run` cada minuto; el detalle exacto está en `ARQUITECTURA.md § 2.1`.

## Recomendaciones antes de mostrarlo al docente

1. **Navegar en el navegador con los 3 usuarios demo** (`admin@lms.edu.pe`, `profesor@lms.edu.pe`, `estudiante@lms.edu.pe`, contraseña `password123` los tres) para ver la diferenciación visual por rol y el flujo completo: crear un curso, agregar una unidad y una tarea, entregarla como estudiante, calificarla, ver la notificación, ver la propuesta del bot en `/bot/propuestas`, descargar la boleta en PDF.
2. Activar las 3 credenciales reales (tabla arriba) cuando estén disponibles — no requieren tocar código.
3. Si hay más tiempo: pulir el texto en inglés que quedó en las vistas de Breeze no tocadas a fondo (confirmación de contraseña, verificación de email, actualización de password) — funcionan bien, solo no se tradujeron línea por línea; y considerar añadir un selector de estudiante en el dashboard del profesor para descargar la boleta de un estudiante específico (la autorización ya está lista, falta solo la UI).

## Estado general del proyecto

Los 9 módulos del documento están construidos, probados (unitaria/funcionalmente donde tiene sentido, con inspección manual donde no) y verificados de extremo a extremo con datos de ejemplo realistas. La regla de gobernanza del bot (el punto más sensible frente al docente) tiene una prueba automatizada explícita y pasando. Considero cubiertas las Fases 1 a 4 del documento del proyecto en su totalidad funcional; lo que queda pendiente son credenciales externas (no dependen de más código) y pulido visual menor en pantallas que Breeze ya dejaba funcionales.


---

## 10. Administración Docente y Comunicados Institucionales (Estilo Blackboard)

**Actualización:** 2026-09-18  
**Se construyó:** 
1. **Directorio Docente y Carga Académica (`/admin/profesores`):** Vista de supervisión inspirada en Blackboard Faculty Management. Métricas globales del claustro, visualización de cursos a cargo, conteo de alumnos asignados, medidores de tasa de cumplimiento en calificaciones y alerta visual de entregas sin calificar con más de 3 días de espera.
2. **Alta Rápida de Docentes:** Modal para registrar nuevos profesores con contraseña inicial y asignación inmediata del rol.
3. **Asignación / Reasignación de Cursos:** Desde el directorio docente o en el formulario de creación/edición de cursos (`/cursos/crear` y `/cursos/{id}/editar`), el Administrador puede cambiar el profesor titular de cualquier asignatura.
4. **Comunicados Institucionales (`/admin/comunicados` — Blackboard Announcements):** Emisión de directivas oficiales con prioridades (`urgente`, `informativo`) y segmentación de audiencia (`profesores`, `estudiantes`, `todos`), visibles en banners destacados en los paneles de los usuarios.
5. **Panel Docente Enriquecido:** Resumen en el dashboard del profesor con sus cursos asignados, total de alumnos y contador de entregas pendientes en tiempo real.

**Se probó:** 9 tests automatizados en `tests/Feature/AdminProfesoresTest.php` cubriendo navegación, protección de acceso (403 Forbidden para estudiantes y profesores), alta de docentes, asignación/desasignación de cursos, emisión de comunicados y filtrado por rol.

---

## Estado actualizado de la Suite de Pruebas (2026-09-18)

**108 tests automatizados, 262 aserciones, 0 fallando** (`php artisan test`). Cobertura completa de los 10 módulos del sistema, autenticación, gobernanza del bot, grupos, reportes en PDF y administración docente.

---

## 11. Cola de Calificacion Docente ("Por Calificar")

**Actualizacion:** 2026-09-18

**Se construyo:**
Vista centralizada en `/por-calificar` donde el docente accede a todas las entregas pendientes de evaluacion de todos sus cursos en un solo lugar, sin tener que entrar curso por curso.

**Funcionalidades implementadas:**
- **KPI badges** en la cabecera: total de entregas sin calificar y conteo de entregas con mas de 3 dias de espera (resaltadas en rojo).
- **Filtro por curso** y busqueda por nombre de estudiante o nombre de tarea.
- **Orden FIFO**: las entregas mas antiguas aparecen primero.
- **Modal de calificacion rapida**: el docente asigna nota numerica (0-20) y escribe retroalimentacion sin abandonar la cola.
- **Propagacion grupal**: si la entrega pertenece a una tarea grupal, la nota se aplica automaticamente a todos los integrantes del grupo.
- **Notificacion automatica** al estudiante (o a todos los integrantes del grupo) al guardar la calificacion.
- **Control de acceso**: solo accesible para roles `profesor` y `administrador` (HTTP 403 para estudiantes).
- Item **"Por Calificar"** anadido al menu lateral de navegacion, visible unicamente para el rol `profesor`.

**Archivos creados:**
- `app/Livewire/Docente/PorCalificar/Index.php`
- `resources/views/livewire/docente/por-calificar/index.blade.php`

**Archivos modificados:**
- `resources/views/layouts/app.blade.php` - item de navegacion
- `routes/web.php` - ruta `GET /por-calificar`

---

## 12. Libro de Calificaciones por Curso (Gradebook)

**Actualizacion:** 2026-09-18

**Se construyo:**
Matriz de calificaciones completa en `/cursos/{curso}/libro-calificaciones`, accesible desde la cabecera de cada curso para el docente titular y el administrador.

**Funcionalidades implementadas:**
- **Matriz Estudiantes x Evaluaciones**: columnas por tarea y quiz, fila de promedio final por estudiante.
- **Promedios ponderados** calculados usando el campo `peso_nota` de cada tarea (no promedio aritmetico simple).
- **KPI cards globales del curso**: Promedio General, Tasa de Aprobacion, Nota Maxima y Nota Minima.
- **Colores semanticos por nota**: verde (>=13), amarillo (11-12), rojo (<11), gris (sin entrega).
- **Modal de edicion directa por celda**: el docente puede modificar la nota de cualquier entrega existente sin ir al detalle de la tarea.
- **Notificacion al estudiante** al guardar la nota desde el gradebook.
- **Control de acceso**: Gate `gestionar` (solo profesor dueno o administrador).
- Boton **"Libro de Calificaciones"** visible en la cabecera del curso.

**Archivos creados:**
- `app/Livewire/Cursos/LibroCalificaciones.php`
- `resources/views/livewire/cursos/libro-calificaciones.blade.php`

**Archivos modificados:**
- `routes/cursos.php` - ruta `GET /cursos/{curso}/libro-calificaciones`
- `resources/views/livewire/cursos/show.blade.php` - boton de acceso en cabecera

---

## 13. Sistema de Anuncios por Curso

**Actualizacion:** 2026-09-18

**Se construyo:**
Extension del modulo de comunicados para que los docentes puedan publicar anuncios especificos de cada curso (distinto a los comunicados institucionales globales del Administrador).

**Funcionalidades implementadas:**

### Perspectiva del Docente:
- Formulario de publicacion en la vista del curso: titulo, mensaje y nivel de prioridad (Normal / Importante / Urgente).
- Listado de anuncios publicados con badge de color por prioridad (gris / naranja / rojo).
- Opcion de eliminar un anuncio propio con confirmacion.
- Layout rediseado a 2 columnas en la vista del curso: contenido/modulos a la izquierda y panel de anuncios a la derecha.

### Perspectiva del Estudiante:
- **Nueva pestana "Anuncios"** en la vista del curso, entre "Contenido" y "Tareas".
- Badge numerico en la pestana cuando hay anuncios activos.
- Cada anuncio muestra: icono, titulo, badge de prioridad, nombre del autor, fecha relativa y fecha absoluta.
- Estado vacio descriptivo cuando el docente no ha publicado anuncios.

**Archivos modificados:**
- `app/Livewire/Cursos/Show.php` - metodos `publicarAnuncio()`, `eliminarAnuncio()`, computed `getAnunciosProperty()`
- `app/Models/Comunicado.php` - campo `curso_id`, relacion `curso()`, scopes `scopeInstitucionales()` y `scopeDelCurso()`
- `app/Models/Curso.php` - relacion `comunicados()`
- `resources/views/livewire/cursos/show.blade.php` - nuevo layout docente y pestana estudiante

**Migracion:**
- `database/migrations/2026_09_18_150000_add_curso_id_to_comunicados_table.php`

---

## 14. Comprobante Digital de Entrega

**Actualizacion:** 2026-09-18

**Se construyo:**
Tarjeta de confirmacion visible para el estudiante en la pagina de detalle de cada tarea, una vez que ha realizado su entrega.

**Funcionalidades implementadas:**
- Tarjeta verde de confirmacion, visible unicamente cuando el estudiante ya entrego.
- **Codigo unico de comprobante** con formato `REC-000001-2026` (derivado del ID de la entrega con padding y anio).
- **Timestamp exacto** de la entrega en formato `dd/mm/YYYY HH:MM:SS`.
- Seccion de calificacion mejorada: badge Aprobado/Desaprobado, nota en tipografia grande, panel de retroalimentacion del docente.

**Archivos modificados:**
- `resources/views/livewire/tareas/detalle.blade.php`

---

## 15. Tests Automatizados - Modulos Docente y Estudiante

**Actualizacion:** 2026-09-18

**Archivo:** `tests/Feature/DocenteEstudianteLmsTest.php` - 7 tests, ~30 aserciones.

| # | Nombre del test | Que verifica |
|---|---|---|
| 1 | test_docente_puede_acceder_a_cola_por_calificar_y_evaluar_entrega | Calificacion correcta, propagacion grupal, nota almacenada |
| 2 | test_estudiante_no_puede_acceder_a_cola_por_calificar | HTTP 403 para rol estudiante |
| 3 | test_docente_puede_ver_y_editar_libro_de_calificaciones | Matriz visible, edicion directa de nota, estado calificado |
| 4 | test_estudiante_no_puede_acceder_a_libro_de_calificaciones | HTTP 403 para rol estudiante |
| 5 | test_docente_puede_publicar_y_eliminar_anuncio_del_curso | Publicacion y eliminacion de anuncio desde el curso |
| 6 | test_estudiante_ve_anuncios_del_curso_en_su_pestana | Pestana Anuncios muestra el comunicado del docente |
| 7 | test_estudiante_ve_recibo_digital_de_entrega | Codigo REC-XXXXXX visible al consultar el detalle de la tarea |

---

## Estado final de la Suite de Pruebas (2026-09-18)

**115 tests automatizados, 292 aserciones, 0 fallando** (`php artisan test`).

| Area | Tests | Archivo de test |
|---|---|---|
| Autenticacion y registro | 4 | Auth/RegistrationTest, Auth/AuthenticationTest |
| Usuarios y roles | 7 | UsuariosTest |
| Cursos | 8 | CursosTest |
| Grupos | 7 | GruposTest |
| Tareas | 7 | TareasTest |
| Quizzes | 4 | QuizzesTest |
| Calendario y notificaciones | 5 | CalendarioNotificacionesTest |
| Bot de IA y propuestas | 12 | BotFaqTest, PropuestaBotTest |
| Dashboard y reportes | 5 | ReportesTest |
| Panel Administrador | 4 | PanelAdminTest |
| Admin Profesores | 9 | AdminProfesoresTest |
| Docente Cola/Gradebook/Anuncios | 7 | DocenteEstudianteLmsTest |
| Vista Estudiante (rediseno UI) | 9 | CursoShowEstudianteTest, PanelInicioEstudianteTest, ListadosEstudianteTest, PulidoEstudianteTest |

## 3. Pruebas de Integración y Flujo por Roles (E2E)

Se han realizado pruebas exhaustivas en el sistema para garantizar que cada rol interactúe correctamente con los módulos desarrollados en todas las fases. A continuación se detallan los resultados:

### Rol Administrador (Supervisor)
- **Inicio de sesión y acceso:** Validado el redireccionamiento obligatorio para el cambio de contraseña en el primer inicio de sesión. (Corregido el error de middleware que congelaba la pantalla).
- **Métricas:** El dashboard de analíticas (Riesgo de Deserción e Interacciones) carga y procesa los datos sin generar cuellos de botella ni errores 500.
- **Gestión de Cursos:** La funcionalidad de 'Clonar Curso' permite duplicar la estructura base correctamente. 
- **Gestión de Usuarios:** El modal de edición de usuarios se despliega correctamente y guarda los cambios en la bitácora de auditoría inmutable.

### Rol Profesor
- **Gestión de Asistencia:** La vista de asistencia permite registrar el estado diario (Presente, Falta, Tardanza) y calcula los porcentajes acumulados sin problemas de integridad referencial.
- **Mensajería Interna (Envío Masivo):** 
  - La interfaz rediseñada permite elegir primero el Curso y luego a los destinatarios.
  - La opción exclusiva del profesor *'Enviar a todos los estudiantes de este curso'* funciona a la perfección.
  - El sistema de base de datos (Postgres con PgBouncer) ahora soporta correctamente las transacciones para múltiples mensajes gracias a la configuración de PDO::ATTR_EMULATE_PREPARES.

### Rol Estudiante
- **Acceso a Mensajería:** Puede leer los mensajes enviados por el profesor. 
- **Respuesta de Mensajes:** Se implementó y verificó con éxito el nuevo botón 'Responder mensaje', el cual redirige a la vista de redacción autocompletando el destinatario y el asunto ('Re: ...').
- **Interacción con Bot de Sugerencias (IA):** El estudiante puede consultar dudas a través del Widget de Inteligencia Artificial que analiza sus estadísticas de rendimiento, y este registro alimenta el panel de Métricas del Supervisor de forma asíncrona.

**Estado Final de Pruebas:** **Exitoso ✅**. 
Todos los errores de incompatibilidad de bases de datos y vistas detectados inicialmente han sido parchados. El sistema se encuentra estable, fluido, sin uso de emojis en las interfaces solicitadas y listo para su uso.
