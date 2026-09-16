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
