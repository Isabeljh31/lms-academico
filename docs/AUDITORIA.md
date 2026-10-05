# Auditoría técnica — LearnUp

Fecha de cierre: 27 de setiembre de 2026 · Rama: `feature/rediseno-estudiante-y-modulos` · Fuente: `ERS_LearnUp_v2.docx`.

Documentos relacionados:

- `docs/TRAZABILIDAD.md`: matriz RF/RNF/CU con los estados inicial y final.
- `docs/PANTALLAS.md`: revisión de pantallas.
- `docs/CAMBIOS.md`: detalle por archivo.
- `docs/PROGRESO.md`: bitácora de avance.

## 1. Resumen ejecutivo

Se analizó el código contra el ERS v2 (108 RF, 21 RNF, 25 CU). Luego se completaron y corrigieron los 19 módulos en el orden del ERS, se reforzó la seguridad y se escribieron pruebas unitarias, funcionales, de seguridad y de pantallas.

| Indicador | Antes | Después |
|---|---|---|
| Pruebas | 128 (97 pasan, 31 fallan o con error) | **432 (432 pasan)** — 385 al cerrar la primera auditoría |
| RF completos | 30 | **106** |
| RF parciales | 56 | **0** |
| RF faltantes o rotos | 20 (10 faltan, 10 rotos) | **0** |
| RF no aplica (trabajo futuro) | 2 | 2 (RF-08 2FA, RF-09 Google) |
| Hallazgos de seguridad abiertos | 16 detectados | **0 abiertos** (19 corregidos) |
| `composer audit` / `npm audit` | — | 0 vulnerabilidades / 0 vulnerabilidades |
| Emojis en la interfaz | presentes en vistas y notificaciones | 0 (todo con iconos SVG) |

Las reglas críticas del negocio ahora se validan en el backend y no solo en la vista:

- Pesos que suman 100 %.
- Matrícula activa.
- Visibilidad de contenido publicado o programado.
- Bloqueo del curso finalizado.
- Intentos y tiempo del quiz.
- Nota mínima configurable.
- Bitácora inmutable.

Cada acción de Livewire se autoriza con policies y se limita al curso autorizado, lo que evita IDOR.

El estilo del Estudiante **no cambió**. Solo se corrigió funcionalidad y se agregaron elementos copiando las clases existentes (ver § 4). Profesor y Supervisor tienen una interfaz nueva de gestión, construida con componentes reutilizables.

La segunda auditoría, de procesos y flujos, está en la § 12. Revisó las 10 reglas de negocio, los flujos 9.1 a 9.4, la matriz de permisos y el diseño de los tres roles.

**Git:** no se ejecutó ningún comando de git que modifique el repositorio. Todo el trabajo está sin commitear en el árbol de trabajo (ver § 10).

## 2. Inconsistencias del ERS y cómo se resolvieron

Se verificó contra `ERS_LearnUp_v2.docx` (versión 2.0). Algunas inconsistencias conocidas de versiones anteriores **ya no existen** en esta versión; se dejan registradas igual.

| # | Inconsistencia reportada | Verificación en ERS v2 | Decisión |
|---|---|---|---|
| 1 | RF-52 duplicado (peso del rubro / nota mínima) | **No existe.** RF-52 es único (recálculo de quiz). Pesos = RF-55/56; nota mínima = RF-107. | Sin cambios de numeración. |
| 2 | RF-61 duplicado (nota en rojo / progreso) | **No existe.** RF-61 = clonar esquema; nota en rojo = RF-64; progreso = RF-66 a RF-69. | Sin cambios. |
| 3 | Requisitos sin ID en 3.6, 3.7 y 3.16 | **No existe.** 3.6.1 tiene RF-50 a RF-53; RF-65 cubre la bitácora de ajustes; 3.16 tiene RF-95 a RF-99. | No se crean IDs nuevos. |
| 4 | 3.16 "Auditoría" con el texto de la API | **No existe.** 3.16 = auditoría, 3.17 = API. | Módulo de Auditoría: bitácora inmutable, solo Supervisor, filtros por fecha, usuario, tipo y curso. |
| 5 | "Curso" vs "Oferta de Curso"; 3.4 "Registro a Curso" | v2 usa solo "Curso" y 3.4 = "Matrícula". | Curso = `App\Models\Curso`; Matrícula = `App\Models\Inscripcion`. No se renombran tablas. |
| 6 | La matriz permite al Profesor dar de baja; RF-30 solo al Supervisor | En v2 RF-29 y la matriz permiten a ambos. | **Solo el Supervisor matricula y da de baja** (acción administrativa auditada, RF-96). El Profesor ve la lista en modo lectura. Aplicado en policy y vistas. |
| 7 | Registro público activo vs 2.4 | Confirmado. | Ruta y enlaces eliminados (`/register` → 404). |
| 8 | RF-06: intentos y tiempo | v2 fija 3 intentos / 15 min. | Implementado como valores configurables (`LOGIN_MAX_INTENTOS`, `LOGIN_MINUTOS_BLOQUEO`). |
| 9 | Entregas hasta 20 MB vs límite de 10 MB de Cloudinary (error 500) | Confirmado. | Límite único de 10 MB (`config/archivos.php`). Los errores de subida se muestran como validación en vez de un 500. |
| 10 | `tailwindcss ^3` y `@tailwindcss/vite ^4` a la vez | Confirmado: el plugin v4 no se importaba en ningún lado. | Se quitó `@tailwindcss/vite`. El build quedó idéntico (mismo hash de CSS). |
| 11 | RNF-06 pide buckets privados; el código usa Cloudinary público | Confirmado. | Se mantiene Cloudinary por instrucción del cliente. RNF-06 queda Parcial con el riesgo documentado. |
| 12 | 19 módulos en el ERS vs 18 en el pedido | — | Se siguió el ERS v2 (19). |
| 13 | CU-24 y CU-25 existen en v2 | — | Se cubren CU-01 a CU-25. |
| 14 | "Clases en vivo" en la sección 11 sin RF asociado | — | Fuera de alcance (§ 8). |
| 15 | RF-23 "uno o más profesores" vs modelo de datos en singular | — | Un profesor titular por curso (§ 8). |
| 16 | "Supervisor" vs "administrador" | El código usa `supervisor`. | Se respeta `supervisor`; se corrigieron los tests que usaban `Rol::Administrador`. |
| 17 | "Curso finalizado" sin definición operativa | — | Finalizado = estado `archivado`. Bloquea esquema, notas, calificación, contenido y edición. |

## 3. Mejoras por módulo

| Módulo | Principales cambios |
|---|---|
| 01 Autenticación | Bloqueo configurable 3/15; mensaje genérico (no revela si la cuenta existe); cuenta desactivada expulsa la sesión (`CuentaActiva`); primer login ya no escribe la contraseña en el log; registro público y borrado de cuenta eliminados. |
| 02 Usuarios | Alta con nombres/apellidos y contraseña temporal en todas las vías (`GestorCuentas`); edición reparada; importación CSV reparada con validación fila por fila y reporte; filtros por rol, estado y curso; desactivar con confirmación. |
| 03 Cursos | Solo el Supervisor crea; código de curso; publicar exige al menos un módulo; clonación completa (módulos, lecciones, tareas y quizzes como borrador, esquema) sin estudiantes ni notas; curso archivado de solo lectura. |
| 04 Matrícula | `GestorMatriculas` único para la vía manual y la API; baja lógica con motivo auditado; notificación in-app y por correo; sin autoinscripción. |
| 05 Contenido | Tabla y pantalla de lecciones; estados Borrador/Publicado/Programado en módulos, lecciones, tareas y quizzes; el estudiante solo ve contenido visible; orden y edición de módulos. |
| 06 Tareas | Instrucciones, fecha de inicio, intentos, formatos permitidos, entrega por archivo/texto/enlace, reentrega, devolución con comentario, estados de RF-43, tareas grupales por grupo. |
| 07 Quizzes | Opción múltiple, tiempo límite verificado en servidor, intentos, orden aleatorio, retroalimentación, banco de preguntas, corrección con advertencia, recálculo auditado y notificación a los afectados; estadísticas por pregunta. |
| 08 Evaluación | Suma 100 % obligatoria en backend y guardado atómico; nota 0–20; nota final ponderada con la nota mínima configurable; práctica separada de la nota final; motivo obligatorio al ajustar; notificación al estudiante al registrar una nota de rubro. |
| 09 Progreso | `ProgresoAcademicoService` (lecciones completadas, tareas entregadas, quizzes rendidos); avance del estudiante y promedio para el profesor. |
| 10 Asistencia | Tablas nuevas; pase de lista (presente, tardanza, ausente, justificado); % acumulado; historial del estudiante; asistencia opcional por curso. |
| 11 Grupos | Renombrar, mover, solo matriculados, entregas agrupadas. |
| 12 Calendario | Tareas, quizzes, evaluaciones y sesiones, filtrado por usuario y contenido visible. |
| 13 Mensajería | Tabla nueva; contactos limitados a compañeros y profesores de sus cursos; adjuntos seguros; notificación de mensaje nuevo; avisos masivos solo del titular. |
| 14 Notificaciones y comunicados | Comunicados programados (`comunicados:publicar` cada 5 min); notificaciones por tarea, quiz, calificación, mensaje, contenido, fecha y matrícula, sin emojis. |
| 15 Reportes | Panel de gestión para Profesor y Supervisor (RF-88/90); reportes por curso (RF-89); dashboard institucional con periodo, abandono, rendimiento y atraso (RF-91); riesgo de abandono con asistencia real. |
| 16 IA | Contexto limitado a los datos del usuario y a su curso; `cursoId` bloqueado y validado con policy; reglas contra inyección de instrucciones; historial recortado; propuestas solo para matrículas activas. |
| 17 Auditoría | Modelo inmutable y trigger en PostgreSQL; filtros tolerantes; exportación CSV; registro de notas, cuentas, matrículas, esquema, configuración, API y comunicados. |
| 18 API | Límite de peticiones; JSON obligatorio (415); curso por id o código; rechaza cuentas no estudiante; respeta el bloqueo local; log con el código final; gestión de API Keys (crear/revocar). |
| 19 Configuración | Configuración en tabla de BD (con respaldo del JSON anterior); nota mínima 0–20; institución, zona horaria y formato de fecha; cada cambio queda auditado con el valor anterior y el nuevo. |

## 4. Mejoras visuales

**Profesor y Supervisor**: se rediseñaron las 32 pantallas de gestión con `resources/views/components/gestion/`:

- encabezado
- alertas
- indicador
- vacío
- botón
- tarjeta
- modal
- campo
- control

Resultado:

- Paleta sobria sin degradados.
- Iconos Heroicons (`<x-icono>`).
- Estados vacíos en todas las listas.
- Confirmación en toda acción destructiva.
- Errores de validación junto a cada campo.
- Tablas con desplazamiento horizontal en móvil.

**Estudiante: estilo sin cambios (confirmado).** Se compararon las vistas del Estudiante con `git diff`, en modo solo lectura (ver `docs/PANTALLAS.md`, tabla del Estudiante). Solo hubo:

- Correcciones funcionales:
  - fechas de vencimiento que leían un campo inexistente;
  - HTML roto `</span</p>`;
  - el texto "Reprobado" pasó a "Desaprobado".
- Reemplazo de emojis por iconos SVG del mismo tamaño.
- Elementos nuevos que copian exactamente las clases de un elemento vecino:
  - "Mi Asistencia" (copia de "Mi Boleta");
  - "Marcar como completada" (copia de "Material Adjunto");
  - campos de respuesta escrita y enlace (copia del formulario de entrega);
  - controles del quiz.

No se cambiaron colores, tipografía, espaciados ni la disposición.

**Emojis:** no queda ninguno en `app/`, `resources/`, `routes/`, `database/`, `config/` ni `tests/`. Cada una de las 51 pruebas de pantalla lo verifica.

## 5. Pruebas antes y después

| Tipo | Antes | Después | Carpeta |
|---|---|---|---|
| Unitarias | 1 (ejemplo) | **29** | `tests/Unit` |
| Seguridad | 0 | **146** | `tests/Feature/Seguridad` |
| Funcionales (flujos 9.1–9.4 y CU) | 0 | **53** | `tests/Feature/Flujos` |
| Excel, calendario por rol y datos de ejemplo | 0 | **17** | `tests/Feature/HojasCalculo`, `CalendarioPorRolTest`, `DatosDeEjemploTest` |
| Pantallas | 0 | **52** | `tests/Feature/RevisionPantallasTest.php` |
| Configuración regional (RF-108) | 0 | **4** | `tests/Feature/ConfiguracionRegionalTest.php` |
| Existentes (Breeze y módulos) | 127 | 131 (actualizadas) | resto de `tests/Feature` |
| **Total** | **128 (97 pasan)** | **432 (432 pasan)** | |

Pruebas de seguridad (144):

- Matriz de acceso por rol en 39 rutas (Supervisor, Profesor, Estudiante, invitado), más profesor ajeno, estudiante no matriculado, estudiante dado de baja y curso en borrador.
- Autenticación: bloqueo, cuenta desactivada, primer login, enumeración de cuentas.
- IDOR.
- Subida de archivos: extensiones peligrosas, tamaño, doble extensión, nombre UUID.
- API: API Key, revocación, 415, límite de peticiones, rol, log.
- Auditoría: inmutabilidad, acceso, filtros, CSV.
- Asistente de IA: curso manipulado, datos ajenos, inyección de instrucciones.
- Validación de entradas: XSS escapado, inyección SQL en filtros, CSRF, asignación masiva.

Los tests desfasados se actualizaron con motivo explícito:

- rol `administrador`;
- registro público;
- autoinscripción;
- profesor que creaba cursos;
- textos cambiados.

Ninguno se borró para "pasar". Un test antiguo enviaba `cursoId` desde el cliente, que era justamente la vulnerabilidad; se reescribió para pasar el curso en `mount`.

Auditoría de dependencias: `composer audit` → 0; `npm audit` → 0.

## 6. Hallazgos de seguridad

| # | Hallazgo | Gravedad | Estado | Corrección | Prueba |
|---|---|---|---|---|---|
| S-01 | La contraseña nueva del primer login se escribía en texto plano en el log | Alta | Corregido | Se eliminó el log | `S/AutenticacionTest` |
| S-02 | Subida local con la extensión del cliente dentro de `storage/public` (un `.php` quedaba publicado) | Alta | Corregido | `SubidorArchivos`: lista blanca, MIME, nombre UUID, 10 MB | `S/SubidaArchivosTest` |
| S-03 | Borrado físico de la propia cuenta (cascada de notas y entregas) | Alta | Corregido | Formulario y acción eliminados | `ProfileTest` |
| S-04 | Registro público habilitado | Alta | Corregido | Ruta eliminada | `S/AutenticacionTest` |
| S-05 | Autoinscripción y acceso a cualquier curso, incluidos los borradores | Alta | Corregido | `CursoPolicy` (`inscribirse` = false; `ver` exige matrícula activa y curso visible) | `S/AccesoPorRolTest` |
| S-06 | IDOR en inscripción, rubro, nota, lección, sesión, entrega y asistencia | Alta | Corregido | `#[Locked]` y consultas a través de las relaciones del curso | `S/IdorTest` |
| S-07 | Ruta `admin/metricas` duplicada sin `auth` | Media | Corregido | Ruta duplicada eliminada | `S/AccesoPorRolTest` |
| S-08 | `routes/test_primer_login.php` con código de prueba | Media | Corregido | Archivo eliminado | — |
| S-09 | Pantallas sin control de rol en la ruta | Media | Corregido | Middleware `role:` en todas las rutas | `S/AccesoPorRolTest` |
| S-10 | Mensajería abierta a cualquier usuario; avisos a cursos ajenos | Media | Corregido | Contactos por curso; aviso solo del titular | `S/IdorTest` |
| S-11 | `cursoId` del asistente modificable desde el cliente | Media | Corregido | `#[Locked]` + `Gate::authorize('ver')` en `mount` | `S/AsistenteIaTest` |
| S-12 | Bitácora modificable (`update`/`delete`) | Media | Corregido | Excepción en el modelo + trigger PostgreSQL | `S/AuditoriaTest` |
| S-13 | API sin límite de peticiones | Media | Corregido | Throttle `api-integracion` configurable | `S/ApiIntegracionTest` |
| S-14 | La API matriculaba cuentas con rol profesor | Media | Corregido | Rechazo con 422 | `S/ApiIntegracionTest` |
| S-15 | El login revelaba si la cuenta existe | Baja | Corregido | Mensaje genérico hasta validar la contraseña | `S/AutenticacionTest` |
| S-16 | La API registraba al estudiante como ejecutor en la bitácora | Baja | Corregido | Ejecutor nulo, etiqueta "Sistema externo (API)" | `S/AuditoriaTest` |
| S-17 | Inyección de instrucciones al asistente de IA (pedir datos de otros o ignorar reglas) | Media | Mitigado | Bloque de reglas en el sistema; el contexto solo contiene datos propios, así que no hay datos ajenos que filtrar | `S/AsistenteIaTest` |
| S-18 | Las propuestas del bot y el resumen semanal incluían estudiantes dados de baja y tareas en borrador | Baja | Corregido | Solo matrículas activas y contenido visible | `S/AsistenteIaTest` |
| S-19 | El motivo de la baja se perdía y no llegaba a la bitácora | Baja | Corregido | `GestorMatriculas::darDeBaja` pasa `motivo` | `F/FlujosPrincipalesTest` |

Riesgo residual:

- **RNF-06 (Media).** Los archivos en Cloudinary usan URLs públicas no adivinables, pero sin expiración. Ver § 8 y § 11.

## 7. Matriz de trazabilidad final

Detalle completo en `docs/TRAZABILIDAD.md`. Resumen:

| Grupo | Total | Completo | Parcial | No aplica |
|---|---|---|---|---|
| RF-01 a RF-108 | 108 | 106 | 0 | 2 |
| RNF-01 a RNF-21 | 21 | 17 | 3 | 1 |
| CU-01 a CU-25 | 25 | 25 con prueba | — | — |

- **RF parciales:** ninguno. RF-11, RF-23, RF-67 y el aviso de contenido programado de RF-84 quedaron completos en la segunda auditoría (§ 12); RF-108 al cerrarla (§ 12.7).
- **RNF parciales:** RNF-06, RNF-10 y RNF-11.
- **No aplica:** RF-08, RF-09 y RNF-13.

## 8. Pendientes

| Pendiente | Motivo | Recomendación |
|---|---|---|
| RNF-06: archivos privados | Se mantiene Cloudinary por instrucción del cliente | Migrar a Supabase Storage con buckets privados y URLs firmadas temporales |
| RNF-11: rendimiento ≤ 3 s | No se midió en producción | Medir en Railway con datos reales; agregar índices si hace falta |
| RF-08 / RF-09: 2FA y login con Google, y clases en vivo | Trabajo futuro según el ERS | Fase 2 del producto |
| `APP_NAME` = "LMS Academico" | No se tocó el `.env` (regla) | Cambiar a `APP_NAME=LearnUp` |
| Trigger de inmutabilidad en PostgreSQL | Las pruebas corren en SQLite | Probarlo una vez en un entorno PostgreSQL de staging antes de producción |
| Scheduler | `comunicados:publicar` y `contenido:notificar-programado` necesitan `schedule:run` | Configurar el cron o worker en Railway |
| Migraciones nuevas de la segunda auditoría | `curso_profesor`, `aviso_programado_en`, `condicion_completitud` (aditivas) | Aplicarlas con `php artisan migrate` en producción tras respaldar |
| `phpoffice/phpspreadsheet` (opcional) | Excel se genera y lee sin dependencias (`App\Services\HojasCalculo`) | Solo si se quieren gráficos o fórmulas dentro del Excel; requiere aprobación |

## 9. Decisiones tomadas sin consultar

1. ~~Solo el Supervisor matricula y da de baja.~~ Reemplazada en la § 12: matricula solo el Supervisor; dan de baja el Supervisor y el Profesor de su curso (matriz del ERS § 5).
2. Curso "finalizado" = estado `archivado`.
3. Bloqueo de login de 3 intentos / 15 minutos, configurable por `.env`.
4. Límite de subida de 10 MB en todas las vías; se mantiene Cloudinary.
5. La configuración pasa a una tabla de BD (`configuraciones_plataforma`), con respaldo del JSON anterior.
6. ~~Un profesor titular por curso.~~ Reemplazada en la § 12: titular y profesores adicionales (RF-23).
7. Tasa de finalización = estudiantes aprobados en cursos archivados / matriculados en esos cursos.
8. Tasa de abandono = matrículas dadas de baja / matrículas totales del periodo.
9. "Fuera de plazo" = entregó después de la fecha límite; "No entregado" = venció sin entrega.
10. La nota del quiz es la del mejor intento.
11. "Justificado" no cuenta en el % de asistencia; un registro faltante cuenta como ausente; la tardanza cuenta como asistencia.
12. La clonación copia módulos, lecciones, tareas y quizzes como **borrador** (conserva las fechas originales para que el Profesor las ajuste antes de publicar).
13. La importación acepta CSV con `,` o `;` y, desde la § 12, libros de Excel `.xlsx`.
14. Con intentos registrados en un quiz, solo se editan preguntas existentes; no se agregan ni quitan.
15. Solo se registran notas de rubro cuando el esquema suma 100 %.
16. Riesgo de abandono: asistencia mínima del 50 % y rendimiento a la fecha por debajo de la mínima configurada, solo en cursos en marcha (§ 12).
17. El asistente de IA sin clave de API responde en modo simulado en vez de fallar.
18. Se quitó `@tailwindcss/vite` (sin uso) en lugar de migrar a Tailwind 4.

## 10. Archivos

Resumen de `docs/CAMBIOS.md` (estado del árbol de trabajo, consultado con `git status`, solo lectura):

| Tipo | Cantidad | Principales |
|---|---|---|
| Modificados | 136 | Componentes Livewire de los 19 módulos, modelos, policies, notificaciones, rutas, vistas, tests existentes, `package.json` / `package-lock.json` |
| Nuevos | 80 | 11 migraciones aditivas, 8 notificaciones, 8 servicios (incluye `HojasCalculo` y el exportador de notas), 4 componentes Livewire, 14 vistas, 22 archivos de pruebas, 1 seeder de datos de ejemplo, 1 controlador, 2 comandos, `config/archivos.php`, `lang/es.json`, 1 middleware, 5 documentos en `docs/` |
| Eliminados | 3 | `livewire/pages/auth/register.blade.php`, `livewire/profile/delete-user-form.blade.php`, `routes/test_primer_login.php` |

Migraciones: todas aditivas (crear tablas o columnas, o hacer una columna nullable). Ninguna borra datos. No se ejecutó `migrate:fresh`, `migrate:reset`, `db:wipe` ni `db:seed`, ni nada contra Supabase. El `.env` no se leyó para imprimir secretos ni se modificó.

**Confirmación de git:**

- Solo se usaron comandos de lectura: `git status`, `git diff`, `git log` y un `git check-ignore`.
- No se ejecutó `add`, `commit`, `push`, `pull`, `fetch`, `merge`, `rebase`, `checkout`, `switch`, `branch`, `stash`, `reset`, `restore`, `revert`, `tag`, `clean`, `rm` ni `config`.
- No se tocó `.git`, `.gitignore` ni los hooks.

Respaldos fuera del repositorio:

- `../LearnUp_respaldo_20260926_0035` (inicial)
- `_modulo_02`
- `_modulo_14`
- `_fase_2`
- `_fase_3`
- `_fase_4`
- `_fase_5`
- `../LearnUp_respaldo_procesos_inicio` (antes de la segunda auditoría)
- `../LearnUp_respaldo_procesos_fin` (al cerrarla)
- `../LearnUp_respaldo_rf108_fin` (tras completar RF-108)

## 11. Riesgos y recomendaciones

| Riesgo | Impacto | Recomendación |
|---|---|---|
| El árbol de trabajo tiene todo el cambio sin commitear, en un repositorio compartido | Alto: un `checkout` o `stash` de otra persona en esta copia podría perderlo | Revisar el diff y commitear por módulos, desde el usuario |
| Migraciones nuevas aún no aplicadas en Supabase | Alto: las pantallas nuevas fallarían en producción | Ejecutar `php artisan migrate` (solo aditivo) en producción tras respaldar; nunca `migrate:fresh` |
| Cambio de permisos del Supervisor (§ 12.4) | Medio: quien usaba la cuenta de Supervisor para cargar contenido o notas ya no puede | Asignar un profesor (titular o adicional) a cada curso antes de desplegar |
| URLs públicas de Cloudinary (RNF-06) | Medio: quien obtenga un enlace puede descargar el archivo | Migrar a almacenamiento privado con URLs firmadas |
| Trigger PostgreSQL sin probar en pgsql | Medio | Probar en staging |
| Scheduler sin configurar | Medio: los comunicados programados no salen | Cron o worker para `schedule:run` |
| Proveedor de IA externo | Medio: el contexto del curso sale a Groq/Anthropic | Informarlo en la política de privacidad; el contexto ya se limita a los datos propios |
| SMTP no verificado | Medio: bienvenida y recuperación dependen del correo | Probar el envío real en staging |
| Fechas guardadas antes de RF-108 | Bajo: la plataforma operaba en UTC; los sellos automáticos anteriores (creación, entrega, bitácora) quedan 5 horas adelantados al leerse en hora de Lima. Los plazos que escribieron los profesores no cambian: ahora se cierran a la hora que ellos eligieron | Aceptar el desfase histórico; si se requiere exactitud, una migración de datos puntual restando 5 horas a esas columnas, previo respaldo |
| Rendimiento no medido | Bajo o medio | Medir tras el despliegue; revisar índices en `inscripciones`, `entregas`, `notas_rubro` |

## 12. Segunda auditoría: procesos y flujos (26 y 27/09/2026)

Pedido: verificar RF por RF, las reglas de negocio (ERS § 7), los flujos punta a punta (§ 9) y la matriz de permisos (§ 5), implementar lo que faltara y mejorar el diseño de los tres roles.

El pedido usa la numeración de una versión anterior del ERS; la equivalencia con el ERS v2 está en `docs/TRAZABILIDAD.md` § 4.

### 12.1 Resultado

| Indicador | Al empezar | Al terminar |
|---|---|---|
| Pruebas | 385 (385 pasan) | **432 (432 pasan)** |
| RF del pedido en "Cumple" | 78 de 89 aplicables | **89 de 89** (RF-09/10, 2FA y Google, son futuro) |
| RF del ERS v2 completos | 102 | **106** (0 parciales; RF-08 y RF-09 no aplican) |
| Reglas de negocio § 7 | 9 de 10 | **10 de 10** |
| Pasos de los flujos 9.1–9.4 | 26 de 28 | **28 de 28** |
| Acciones de la matriz § 5 que coinciden | 13 de 17 | **17 de 17** |

Pint limpio. `npm run build` ejecutado. No se agregaron dependencias.

### 12.2 Reglas de negocio (ERS § 7)

| # | Regla | Estado | Evidencia |
|---|---|---|---|
| 1 | Pesos del esquema = 100 % | Cumple | `CalculadoraNotas::pesosSumanCien` en backend · `CalculadoraNotasTest`, `FlujosPrincipalesTest` |
| 2 | Alta de cuenta ⇒ cambio obligatorio en primer login | Cumple | `GestorCuentas` + `PrimerLogin` · `AutenticacionTest` |
| 3 | Baja lógica, nunca borrado físico | Cumple | `Inscripcion::darDeBaja` · `FlujosPrincipalesTest` (también cuando la hace el Profesor) |
| 4 | Clonar no copia estudiantes, notas ni entregas | Cumple | `Curso::clonar` · `test_flujo_de_clonacion_de_curso` |
| 5 | Notas de tareas/quizzes nunca entran solas a un rubro | Cumple | `CalculadoraNotas` solo usa `notas_rubro`; Excel con hoja "Práctica" aparte |
| 6 | Sistema externo = fuente de verdad; solo se desactiva el acceso local | Cumple | `GestorMatriculas` · el Profesor no puede tocar matrículas de API (`test_el_profesor_no_desactiva_una_matricula_que_llego_por_api`) |
| 7 | Sesiones y semanas independientes, definidas por el Supervisor | Cumple | `cursos.sesiones_clase_count` y `semanas_duracion` en `Cursos\Formulario` |
| 8 | Periodo finalizado no se modifica | Cumple | Curso archivado bloquea contenido, notas, esquema, asistencia y bajas |
| 9 | Borrador/Programado nunca visible | Cumple | Scopes y policies · el aviso de lo programado solo sale al publicarse |
| 10 | Toda matrícula (manual o API) notifica in-app y por correo | Cumple | `MatriculadoEnCurso` (database + mail) · `FlujosPrincipalesTest`, `ApiIntegracionTest` |

La regla 9 estaba "Cumple" en visibilidad, pero la notificación de "nuevo contenido" (RF-84) no salía para lo programado. Se completó con `contenido:notificar-programado`.

### 12.3 Flujos punta a punta (ERS § 9)

| Flujo | Paso | Estado | Evidencia |
|---|---|---|---|
| 9.1 | Supervisor crea cuenta → temporal + correo | Cumple | `test_flujo_alta_y_matricula_manual` |
| 9.1 | Primer login exige cambio | Cumple | ídem |
| 9.1 | Supervisor matricula → in-app + correo | Cumple | ídem |
| 9.1 | Estudiante ve "Mis cursos" y consume contenido | Cumple | Panel corregido: la "próxima entrega" era una fecha vencida · `test_el_panel_del_estudiante_muestra_la_proxima_entrega_futura_y_no_cursos_archivados` |
| 9.2 | Webhook con API Key, valida payload, crea usuario si no existe | Cumple | `ApiIntegracionTest` |
| 9.2 | Matrícula marcada "Integración externa" + notificación | Cumple | ídem |
| 9.2 | Estudiante accede y consume | Cumple | ídem + `AccesoPorRolTest` |
| 9.3 | Supervisor define esquema (100 %) | Cumple | `test_flujo_de_evaluacion_con_nota_final_ponderada` |
| 9.3 | Profesor crea práctica (opcional) | Cumple | Solo el Profesor del curso (antes también el Supervisor) |
| 9.3 | Profesor registra 0–20 por rubro | Cumple | ídem + notificación `NotaRubroRegistrada` |
| 9.3 | Nota final ponderada automática | Cumple | Con "En curso" mientras falten rubros (ver 12.5-e) |
| 9.3 | Estudiante consulta el desglose | Cumple | `BoletaEstudiante` |
| 9.4 | Clonar con contenido y esquema como plantilla | Cumple | + banco de preguntas (antes no se copiaba) |
| 9.4 | Sin estudiantes, notas ni entregas; la oferta original intacta | Cumple | `test_flujo_de_clonacion_de_curso` |
| 9.4 | Ajustar fechas, sesiones, semanas, esquema; asignar profesor(es); matricular | Cumple | `Cursos\Formulario` con profesores adicionales |

Pasos que no estaban completos al empezar:
- **9.1:** la "próxima entrega" del panel mostraba una fecha ya vencida.
- **9.4:** el banco de preguntas no se copiaba al clonar.

### 12.4 Matriz de permisos (ERS § 5)

| Acción | ERS | Antes | Después | Evidencia |
|---|---|---|---|---|
| Crear cuentas / importar | Supervisor | Igual | Igual | `AccesoPorRolTest` |
| Matricular | Supervisor | Igual | Igual | `test_el_profesor_no_puede_matricular` |
| Dar de baja | Profesor (propio), Supervisor (todas) | Solo Supervisor | **Coincide** | `test_el_profesor_da_de_baja_en_su_propio_curso_con_historial_y_auditoria`, `test_el_profesor_no_da_de_baja_en_un_curso_ajeno` |
| Crear/clonar curso, esquema | Supervisor | Igual | Igual | `AccesoPorRolTest` |
| Módulos, lecciones, recursos | Profesor (propio) | Profesor y Supervisor | **Coincide** | `test_el_supervisor_consulta_el_curso_pero_no_gestiona_contenido_ni_notas` |
| Tareas / quizzes | Profesor (propio) | Profesor y Supervisor | **Coincide** | `AccesoPorRolTest` (crear/editar: 403 al Supervisor) |
| Registrar y editar notas | Profesor (propio) | Profesor titular (el Supervisor titular también) | **Coincide** (incluye profesores adicionales) | `test_un_profesor_adicional_gestiona_y_califica_el_curso` |
| Ver su progreso | Estudiante | Igual | Igual | |
| Ver reportes | Profesor (propio), Supervisor (todos) | Igual | Igual | El Supervisor consulta tareas, libro, asistencia y grupos en solo lectura |
| Enviar mensajes | Estudiante, Profesor | Igual | Igual | `AccesoPorRolTest` (403 al Supervisor) |
| Comunicados, auditoría, nota mínima, API, dashboard institucional | Supervisor | Igual | Igual | `AccesoPorRolTest` |

Implementación: `CursoPolicy` separa `gestionar` (Profesor del curso) de `supervisar` (consulta: Supervisor + Profesor), y agrega `matricular` y `darDeBaja`.

### 12.5 Decisiones tomadas sin consultar (segunda auditoría)

a. **Curso = Oferta de Curso** (RF-19/RF-20 del pedido). Es la misma entidad `Curso`, como en el ERS v2. La clonación cumple el papel de plantilla para cada periodo; no se crea una tabla de ofertas.

b. **"Examen"** (RF-38 del pedido) no es una entidad aparte. El ERS v2 enumera Módulo, Lección, Tarea y Quiz, y las evaluaciones calificadas son los rubros. Un examen en línea se crea como Quiz.

c. **El Supervisor queda en solo lectura** en contenido, tareas, quizzes, notas, asistencia y grupos (matriz § 5). "Por calificar", lecciones y banco de preguntas son solo del Profesor.

d. **Bajas por el Profesor:** solo en sus cursos y solo matrículas manuales. Las que llegaron por API las desactiva el Supervisor.

e. **Nota final durante el periodo.** Mientras falten rubros:
   - la nota se muestra **acumulada**;
   - la condición es **"En curso (x de y rubros)"**, sin rojo.

   Aprobado o Desaprobado, y el rojo de RF-64, se aplican con el esquema completo. Promedios, riesgo y bajo rendimiento usan el **rendimiento a la fecha** (promedio ponderado de los rubros calificados). La tasa de aprobación cuenta solo esquemas completos. Antes, a mitad de periodo, todos figuraban "Desaprobado".

f. **Bajo rendimiento (RF-89)** = rendimiento a la fecha bajo la mínima, o 2 o más tareas vencidas sin entregar. El avance menor a 50 % solo cuenta en cursos cerrados, porque a mitad del periodo incluye contenido futuro.

g. **Profesores adicionales (RF-23):**
   - Tienen los mismos permisos que el titular sobre el curso.
   - Si se retira al titular, el primer profesor adicional pasa a ser titular.
   - La clonación no copia a los profesores adicionales: el flujo 9.4 dice que el Supervisor asigna el profesor del nuevo periodo.

h. **Condiciones de completitud (RF-67):**
   - Lección: "marcar como vista" o "al abrir su video o material".
   - Módulo: "actividades" (lecciones + tareas entregadas + quizzes rendidos, por defecto), "solo lecciones" o "aprobación".

i. **Asistencia (RF-73):** la omite o la reactiva el Profesor del curso. El Supervisor también puede hacerlo al editar el curso.

j. **Recuperación de contraseña:** desbloquea la cuenta y da por cumplido el cambio del primer login, porque el usuario acaba de elegir su contraseña.

k. **Excel sin dependencias:** `App\Services\HojasCalculo` lee y escribe `.xlsx` con las extensiones `zip` y `SimpleXML` de PHP. `phpoffice/phpspreadsheet` sería la librería a instalar si se aprueba.

l. **Cursos archivados:** no cuentan como activos en paneles, riesgo, atraso ni calendario del Estudiante. Se siguen consultando como historial.

m. **Contenido programado anterior a la migración:** se marca como ya avisado, para no enviar notificaciones retroactivas.

n. **Datos de ejemplo:** se cargaron en la base SQLite local con `DemoAcademicoSeeder`. Supabase no se tocó.

o. **Zona horaria (RF-108):** la plataforma opera en la zona elegida (por defecto `America/Lima`); las fechas se guardan en esa hora local, sin conversión, porque así las escribe el Profesor en los formularios. Los formatos con nombre de mes en etiquetas cortas del calendario y paneles ("lun 29 sep") se mantienen: no son ambiguos y el ERS pide elegir el formato numérico.

### 12.6 Diseño

**Calendario** (Estudiante, Profesor y Supervisor), con la paleta de cada rol:
- Panel de cursos con su color y filtro.
- Filtro por tipo de actividad.
- Eventos coloreados por curso: lo que se entrega o evalúa en sólido, las clases en estilo secundario.
- Agenda por rol:
  - Estudiante: entregas, quizzes y clases.
  - Profesor: clases, entregas por calificar por semana y evaluaciones por registrar.
  - Supervisor: cursos en marcha, evaluaciones, notas pendientes de registrar y comunicados programados.
- Modal de nuevo evento para todos los roles.
- Vista "Agenda" en móvil.

**Exportaciones a Excel** (libro de calificaciones y bitácora de auditoría):
- Título y subtítulo en banda de color.
- Secciones.
- Encabezados en negrita con color.
- Bordes.
- Formato numérico (0.00, %, fecha).
- Columnas autoajustadas.
- Encabezado fijo con filtro automático.
- Notas en rojo o verde según la condición.
- Fila de promedios.
- Ajuste a página al imprimir.

Verificado abriendo los archivos en Microsoft Excel.

**Revisión de pantallas** con capturas reales, con JavaScript, a 1440 px y 500 px. Detalle en `docs/CAMBIOS.md`. El estilo del Estudiante se mantuvo: solo cambió su calendario (pedido explícito) y textos funcionales.

### 12.7 RF-108: zona horaria y formato de fecha

Quedó Completo (antes Parcial: se guardaban pero no se aplicaban).

| Aspecto | Implementación |
|---|---|
| Zona horaria | `ConfiguracionPlataforma::aplicarZonaHoraria()` fija `app.timezone` y la zona de PHP en cada solicitud web (`AppServiceProvider`), en los comandos programados (`comunicados:publicar`, `contenido:notificar-programado`, `bot:detectar-atrasos`) y en los seeders. Así `now()`, la apertura y cierre de tareas y quizzes y la publicación programada usan la hora de la institución. Al guardar en Configuración se aplica de inmediato. |
| Formato de fecha | Macros de Carbon `formatoFecha()`, `formatoFechaHora()` y `formatoDiaMes()`: reemplazan los 53 formatos fijos de vistas, notificaciones, contexto de IA, propuestas del bot y exportaciones. |
| Excel | Las celdas de fecha usan el formato elegido (`dd/mm/yyyy`, `dd-mm-yyyy` o `yyyy-mm-dd`). |
| Calendario | Las horas se envían sin desfase y FullCalendar las muestra en la hora de la plataforma, aunque el navegador esté en otra zona. |
| Sellos de la base | `logs_integracion_api.created_at` ahora lo asigna la aplicación (antes el valor por defecto de la base, en UTC). |
| Valores inválidos | Una zona o formato no reconocido vuelve al predeterminado; sin la tabla (instalación nueva) también. |
| Pantalla | Configuración explica el alcance y muestra una vista previa del formato. |

Pruebas: `tests/Feature/ConfiguracionRegionalTest.php` (4):
- la zona guardada se aplica y se vuelve a aplicar en cada solicitud;
- los plazos se comparan con la hora de la zona configurada;
- el formato elegido aparece en pantalla, notificaciones y Excel;
- valores inválidos usan los predeterminados.

### 12.8 Git

- No se ejecutó ningún comando que modifique el repositorio: solo `git status`, siempre en lectura.
- Todo queda sin commitear.
- Conviene revisar el diff y commitear por bloques: permisos, profesores adicionales, completitud, Excel, calendario y datos de ejemplo.