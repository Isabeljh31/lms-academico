# 06. Rutas y permisos

Fuente: `php artisan route:list --json` (13/10/2026), `routes/*.php`, `app/Policies/*` y `app/Http/Middleware/*`.

## 1. Tabla de rutas (64)

Abreviaturas de middleware:
- **W** = grupo `web`, que incluye sesión, CSRF, `CuentaActiva` y `PrimerLogin`.
- **A** = `auth`.
- **V** = `verified`.
- **R:x** = `role:x` (Spatie).

"Control adicional" es la autorización que hace el destino (archivo 07).

### 1.1 Generales, autenticación y archivos

| Método | URI | Nombre | Middleware | Destino | Control adicional |
|---|---|---|---|---|---|
| GET | `/` | inicio | W | Closure: redirige al login o al panel | — |
| GET | `dashboard` | dashboard | W, A, V | Vista `dashboard` | Embebe `PanelInicio` (estudiante) o `PanelGestion` |
| GET | `profile` | profile | W, A | Vista `profile` (Volt) | — |
| POST | `logout` | logout | W, A | Closure con `Logout` | — |
| GET | `login` | login | W, guest | Volt `pages.auth.login` | `LoginForm` |
| GET | `forgot-password` | password.request | W, guest | Volt | — |
| GET | `reset-password/{token}` | password.reset | W, guest | Volt | — |
| GET | `verify-email` | verification.notice | W, A | Volt | — |
| GET | `verify-email/{id}/{hash}` | verification.verify | W, A, signed, throttle 6/1 | `VerifyEmailController` | — |
| GET | `confirm-password` | password.confirm | W, A | Volt | — |
| GET | `primer-login` | primer-login | W, A, V | `Auth\CambiarPasswordPrimerLogin` | — |
| GET | `evento-calendario` | evento-calendario.index | W, A, V | `EventoCalendario\Index` | Datos filtrados por rol |
| GET | `por-calificar` | docente.por-calificar | W, A, V, R:profesor | `Docente\PorCalificar\Index` | Solo entregas de sus cursos |
| GET | `mensajeria` | mensajeria.bandeja | W, A, V, R:estudiante\|profesor | `Mensajeria\Bandeja` | Contactos dentro de cursos comunes |
| GET | `archivos/entregas/{entrega}` | archivos.entrega | W, A, V | `ArchivoController@entrega` | `EntregaPolicy::ver` |
| GET | `archivos/mensajes/{mensaje}` | archivos.mensaje | W, A, V | `ArchivoController@mensaje` | Remitente o destinatario |
| GET | `archivos/cursos/{curso}/portada` | archivos.portada | W, A, V | `ArchivoController@portada` | `CursoPolicy::ver` |
| GET | `archivos/usuarios/{usuario}/foto` | archivos.foto | W, A, V | `ArchivoController@foto` | Cualquier usuario autenticado |
| GET | `usuarios/importar` | usuarios.importar | W, A, V, R:supervisor | `Usuarios\ImportarCSV` | `UsuarioPolicy::verListado` |
| GET | `modulos/{modulo}/lecciones` | lecciones.index | W, A, V, R:profesor | `Lecciones\Index` | `CursoPolicy::gestionar` |
| GET | `reportes` | reportes.dashboard | W, A, V | `Reportes\Dashboard` | Datos según el rol |

### 1.2 Cursos (`routes/cursos.php` y parte de `web.php`)

| Método | URI | Nombre | Middleware | Destino | Control adicional |
|---|---|---|---|---|---|
| GET | `cursos` | cursos.index | W, A, V | `Cursos\Index` | Listado filtrado por rol |
| GET | `cursos/crear` | cursos.crear | W, A, V, R:supervisor | `Cursos\Formulario` | `crear` |
| GET | `cursos/{curso}` | cursos.show | W, A, V | `Cursos\Show` | `ver` |
| GET | `cursos/{curso}/editar` | cursos.editar | W, A, V, R:supervisor | `Cursos\Formulario` | `administrar` |
| GET | `cursos/{curso}/esquema` | cursos.esquema | W, A, V, R:supervisor | `Cursos\EsquemaEvaluacion` | `administrar` |
| GET | `cursos/{curso}/boleta` | cursos.boleta | W, A, V | `Cursos\BoletaEstudiante` | Estudiante matriculado |
| GET | `cursos/{curso}/boleta.pdf` | reportes.boleta | W, A, V | `BoletaPdfController` | `verProgreso` |
| GET | `cursos/{curso}/matriculas` | cursos.matriculas | W, A, V, R:supervisor\|profesor | `Cursos\GestionMatricula` | `supervisar`; matricular y dar de baja por acción |
| GET | `cursos/{curso}/libro-calificaciones` | cursos.libro-calificaciones | W, A, V, R:profesor\|supervisor | `Cursos\LibroCalificaciones` | `supervisar`; registrar notas requiere `calificar` |
| GET | `cursos/{curso}/asistencia` | asistencia.index | W, A, V, R:profesor\|supervisor | `Asistencia\Index` | `supervisar`; registrar requiere `gestionar` |
| GET | `cursos/{curso}/mi-asistencia` | cursos.mi-asistencia | W, A, V, R:estudiante | `Asistencia\MiHistorial` | `ver` |
| GET | `cursos/{curso}/grupos` | cursos.grupos.index | W, A, V, R:profesor\|supervisor | `Grupos\Index` | `supervisar`; cambios requieren `gestionar` |
| GET | `cursos/{curso}/banco-preguntas` | cursos.banco-preguntas | W, A, V, R:profesor | `Quizzes\BancoPreguntas` | `gestionar` |
| GET | `cursos/{curso}/lecciones/{leccion}/{recurso}` | cursos.lecciones.recurso | W, A, V | `RecursoLeccionController` | `ver` y visibilidad; `recurso` debe ser video, material o un número |
| GET | `cursos/{curso}/tareas` | cursos.tareas.index | W, A, V | `Tareas\Index` | `ver` |
| GET | `cursos/{curso}/tareas/crear` | cursos.tareas.crear | W, A, V | `Tareas\Formulario` | `TareaPolicy::crear` |
| GET | `cursos/{curso}/tareas/{tarea}` | cursos.tareas.show | W, A, V, scopeBindings | `Tareas\Detalle` | `TareaPolicy::ver` |
| GET | `cursos/{curso}/tareas/{tarea}/editar` | cursos.tareas.editar | W, A, V, scopeBindings | `Tareas\Formulario` | `gestionar` |
| GET | `cursos/{curso}/quizzes` | cursos.quizzes.index | W, A, V | `Quizzes\Index` | `ver` |
| GET | `cursos/{curso}/quizzes/crear` | cursos.quizzes.crear | W, A, V | `Quizzes\Formulario` | `QuizPolicy::crear` |
| GET | `cursos/{curso}/quizzes/{quiz}` | cursos.quizzes.show | W, A, V, scopeBindings | `Quizzes\Resolver` | `QuizPolicy::ver` |
| GET | `cursos/{curso}/quizzes/{quiz}/editar` | cursos.quizzes.editar | W, A, V, scopeBindings | `Quizzes\Formulario` | `gestionar` |
| GET | `cursos/{curso}/quizzes/{quiz}/estadisticas` | cursos.quizzes.estadisticas | W, A, V, scopeBindings | `Quizzes\Estadisticas` | `supervisar` |

Las rutas de lecciones (`cursos/{curso}/lecciones/{leccion}/{recurso}`) no usan `scopeBindings`. El controlador lo compensa: comprueba que la lección pertenezca al curso (`RecursoLeccionController.php:23`).

### 1.3 Supervisor (`routes/admin.php`, prefijo `admin/`, middleware W, A, V, R:supervisor)

| URI | Nombre | Destino | Control adicional |
|---|---|---|---|
| `admin/usuarios` | admin.usuarios.index | `Usuarios\Index` | `verListado` |
| `admin/profesores` | admin.profesores.index | `Admin\Profesores\Index` | `hasRole` en `mount` y en cada acción |
| `admin/comunicados` | admin.comunicados.index | `Admin\Comunicados\Index` | `hasRole` |
| `admin/metricas` | admin.metricas | `PanelAdmin\Metricas` | `hasRole` |
| `admin/analitica-riesgo` | admin.analitica-riesgo | `Admin\Metricas\Index` | `hasRole` |
| `admin/configuracion` | admin.configuracion | `PanelAdmin\Configuracion` | `hasRole` |
| `admin/integraciones` | admin.integraciones | `PanelAdmin\Integraciones` | `hasRole` |
| `admin/auditoria` | admin.auditoria | `PanelAdmin\Auditoria` | `hasRole` |

### 1.4 Asistente de IA (`routes/bot.php`)

| URI | Nombre | Middleware | Destino |
|---|---|---|---|
| `bot/propuestas` | bot.propuestas | W, A, V, R:estudiante | `BotSugerencias\Bandeja` |
| `bot/resumen` | bot.resumen | W, A, V, R:profesor | `BotSugerencias\ResumenSemanal` |

El widget del chat (`BotSugerencias\Widget`) no tiene ruta: se incluye en el layout para **todos** los roles (`layouts/app.blade.php:161`).

### 1.5 API e infraestructura

| Método | URI | Middleware | Destino | Nota |
|---|---|---|---|---|
| POST | `api/v1/matriculas` | `api`, `throttle:api-integracion`, `ValidarApiKey` | `Api\MatriculaController@sincronizar` | Sin nombre de ruta |
| GET | `up` | — | Health check de Laravel | Usado por Railway |
| GET y PUT | `storage/{path}` | — (controlador del framework) | Disco `local` con `serve=true` | Exige firma válida (ver archivo 13) |
| POST | `livewire/update` | W | Livewire | Todas las acciones de los componentes |
| POST | `livewire/upload-file` | W, throttle 60/1 | Livewire | Subidas temporales |
| GET | `livewire/preview-file/{filename}` | W | Livewire | Firmada |
| GET | `livewire/livewire.js`, `livewire.min.js.map` | — | Recursos estáticos | — |
| POST | `_boost/browser-logs` | — | Laravel Boost | Solo existe con dependencias de desarrollo; el build de Railway usa `--no-dev` (`railway.json:5`) |

### 1.6 Registro público

- No existe `register`: `Route::has('register')` es falso y `GET` o `POST /register` devuelven 404.
- Prueba: `tests/Feature/Auth/RegistrationTest.php` (3 pruebas, pasan).
- No existe la vista Volt `pages/auth/register`.
- Queda un enlace condicional a `register` en `resources/views/livewire/welcome/navigation.blade.php:13-18`. No se muestra porque la ruta no existe, y la vista ya no se usa: código muerto.

## 2. Middleware propios

| Middleware | Qué hace | Riesgos |
|---|---|---|
| `ForzarHttps` | Solo en producción: si la petición no es segura, redirige con 301 (GET) o 308 (otros métodos); si es segura, agrega HSTS de 1 año con `includeSubDomains` | Sin `preload`, lo cual es correcto. Depende de `APP_ENV=production`. |
| `CuentaActiva` | Si el usuario autenticado tiene `activo=false`, cierra la sesión, la invalida y redirige al login con un mensaje | Solo actúa en el grupo `web`. La API no tiene usuarios. |
| `PrimerLogin` | Si `primer_login=true`, redirige a `primer-login` cualquier ruta que no sea `primer-login`, `logout` o `livewire.*`, o cuyo path no empiece con `livewire/` | Deja pasar **todas** las llamadas `livewire/update`. Para usarlas hace falta el *snapshot* firmado de un componente ya renderizado, que el usuario no tiene porque no puede abrir otras páginas, salvo los componentes del layout. Riesgo bajo; ver archivo 13. |
| `ValidarApiKey` | Lee `Bearer`, busca la integración activa por prefijo y verifica el hash, crea un `LogIntegracionApi` (202 o 401), responde 401 si la clave falla y al final actualiza el código real | El log se crea **antes** de validar el cuerpo, así que también registra las solicitudes rechazadas (RF-102 y RF-106) |

Además, `TrustProxies` confía en todos los proxies (`at: '*'`), pero solo en `X-Forwarded-Proto`, `Host` y `Port` (`bootstrap/app.php:24`).

## 3. Policies, una por una

| Policy | Método | Regla | Archivo:línea |
|---|---|---|---|
| CursoPolicy | ver | Supervisor siempre; profesor si es docente; estudiante si el curso está publicado o archivado y tiene matrícula activa | `CursoPolicy.php:27-40` |
| | crear, administrar, matricular | Solo supervisor | `:42-55` |
| | darDeBaja | Supervisor, o `gestionar` | `:57-60` |
| | gestionar | Rol profesor y docente del curso | `:62-65` |
| | supervisar | Supervisor, o `gestionar` | `:67-70` |
| | calificar | `gestionar` | `:72-75` |
| | inscribirse | Siempre false (no hay autoinscripción) | `:81-84` |
| | verProgreso | `supervisar`, o estudiante matriculado | `:90-94` |
| TareaPolicy | crear y gestionar | `CursoPolicy::gestionar` del curso | `TareaPolicy.php:12-20` |
| | supervisar | `CursoPolicy::supervisar` | `:23-26` |
| | ver | `supervisar`; o estudiante con la tarea visible, puede ver el curso y, si es grupal asignada, pertenece al grupo | `:33-49` |
| | eliminar | `gestionar` y sin entregas | `:56-59` |
| QuizPolicy | crear, gestionar, supervisar | Igual que en tareas | `QuizPolicy.php:12-26` |
| | ver | `supervisar`; o estudiante con quiz visible y curso visible | `:32-41` |
| | responder | Estudiante, `ver`, curso no archivado, quiz abierto y con intentos | `:47-54` |
| | eliminar | `gestionar` y sin resultados | `:56-59` |
| EntregaPolicy | crear | Estudiante, puede ver la tarea, la tarea ya inició, curso no archivado | `EntregaPolicy.php:16-22` |
| | ver | La propia entrega, o `supervisar` del curso | `:28-32` |
| | calificar | `calificar` del curso y curso no archivado | `:37-42` |
| GrupoPolicy | crear, gestionar, supervisar | Delega en `CursoPolicy` | `GrupoPolicy.php` |
| PropuestaBotPolicy | responder | Dueño de la propuesta y estado pendiente | `PropuestaBotPolicy.php:10-13` |
| UsuarioPolicy | verListado | Supervisor | `UsuarioPolicy.php:9-12` |
| | actualizarRol | Supervisor y no sobre sí mismo | `:14-17` |

`CursoPolicy::ver` permite que un estudiante con matrícula activa vea un curso **archivado**: es el historial. Lo que no ve es su contenido en borrador.

## 4. Matriz de permisos: ERS § 5 frente a la implementación

**Hecho:** la matriz del ERS tiene **18 acciones**, no 17 como dicen `docs/AUDITORIA.md` y `docs/BRIEFING_PROYECTO.md` ("17 de 17").

| # | Acción del ERS | ERS (E / P / S) | Implementación real | Estado |
|---|---|---|---|---|
| 1 | Crear cuentas de usuario | — / — / Todas | `Usuarios\Index::crearUsuario` (`verListado`) y `Admin\Profesores\Index::crearProfesor` (`hasRole`); rutas `R:supervisor` | Cumple |
| 2 | Importar usuarios masivamente | — / — / Sí | `Usuarios\ImportarCSV` (`R:supervisor` y `verListado`) | Cumple |
| 3 | Matricular estudiante | — / — / Sí | `CursoPolicy::matricular` y `GestionMatricula::puedeMatricular` | Cumple |
| 4 | Dar de baja a un estudiante | — / Propio / Todas | `CursoPolicy::darDeBaja`; el profesor no puede dar de baja matrículas que llegaron por API (`GestionMatricula.php:63-68`). Prueba: `FlujosPrincipalesTest::test_el_profesor_da_de_baja_en_su_propio_curso…` y `…no_da_de_baja_en_un_curso_ajeno` | Cumple |
| 5 | Crear curso | — / — / Sí | `CursoPolicy::crear` y `R:supervisor` | Cumple |
| 6 | Clonar curso | — / — / Sí | `Show::clonarCurso` con `administrar` | Cumple |
| 7 | Definir esquema de evaluación | — / — / Sí | `EsquemaEvaluacion` con `administrar` y `R:supervisor` | Cumple |
| 8 | Crear módulos, lecciones y recursos | — / Propio / — | `gestionar` (solo el profesor docente). El Supervisor no puede: `Show::prepararSeccion` y `Lecciones\Index` exigen `gestionar`. | Cumple |
| 9 | Crear tareas y quizzes | — / Propio / — | `TareaPolicy::crear` y `QuizPolicy::crear` con `gestionar` | Cumple |
| 10 | Registrar y editar notas | — / Propio / — | `calificar` = `gestionar`; el Supervisor ve el libro de calificaciones en solo lectura | Cumple |
| 11 | Ver su propio progreso y calificaciones | Sí / — / — | `BoletaEstudiante` (solo estudiante matriculado). `BoletaPdfController` usa `verProgreso`, que también deja pasar al profesor y al Supervisor, pero genera la boleta del usuario autenticado, no la de un estudiante. | Cumple (detalle de la boleta PDF en el archivo 09) |
| 12 | Ver reportes de su(s) curso(s) | — / Propio / Todos | `Reportes\Dashboard` filtra por docente o todos; `supervisar` | Cumple |
| 13 | Enviar mensajes | Sí / Sí / — | Ruta `R:estudiante\|profesor` y `mount` | Cumple |
| 14 | Emitir comunicados generales | — / — / Sí | `Admin\Comunicados` (`R:supervisor`). El profesor publica **anuncios de curso** (RF-81), que se guardan en la misma tabla con `curso_id`. | Cumple |
| 15 | Consultar bitácora de auditoría | — / — / Sí | `PanelAdmin\Auditoria` (`R:supervisor` y `hasRole`) | Cumple |
| 16 | Configurar nota mínima | — / — / Sí | `PanelAdmin\Configuracion` | Cumple |
| 17 | Configurar integración externa (API Key) | — / — / Sí | `PanelAdmin\Integraciones` | Cumple |
| 18 | Ver dashboard institucional | — / — / Sí | `PanelAdmin\Metricas` | Cumple |

Resultado: las 18 acciones se cumplen.

Permisos reales que la matriz no contempla:
1. El Supervisor **consulta** en solo lectura tareas, quizzes, libro de calificaciones, asistencia y grupos (`supervisar`). Se justifica por "Ver reportes: Todos".
2. El Supervisor puede **cambiar el rol** de cualquier otro usuario a cualquiera de los 3 roles, incluido `supervisor` (`Usuarios/Index.php:122-146`; `UsuarioPolicy::actualizarRol`). El ERS solo prevé crear Estudiante o Profesor (RF-10). Ver H-12.
3. El Supervisor **reabre** cursos archivados (H-03).
4. Todos los roles, incluido el Supervisor, ven el widget del asistente de IA (`layouts/app.blade.php:161`). El ERS lo define para el Estudiante (RF-92 a RF-94).
5. Registrar asistencia (RF-70, CU-17) no está en la matriz. Lo hace el profesor del curso (`Asistencia\Index::autorizarEdicion`, que exige `gestionar`).

## 5. Punto 7: ¿puede el Profesor dar de baja estudiantes?

| Fuente | Qué dice |
|---|---|
| ERS v2 RF-29 | "El Supervisor y el Profesor del Curso deben poder dar de baja a un Estudiante de dicho Curso." |
| ERS v2 § 5, matriz | "Dar de baja a un estudiante: Profesor Propio, Supervisor Todas" |
| ERS v2 RF-32 | Si la matrícula vino por API, "el Supervisor solo puede desactivar el acceso local" |
| Policy | `CursoPolicy::darDeBaja`: supervisor, o profesor docente (`CursoPolicy.php:57-60`) |
| Ruta | `cursos/{curso}/matriculas` con `role:supervisor\|profesor` (`routes/web.php:73-75`) |
| Código | `GestionMatricula::puedeDarDeBajaA`: el profesor solo puede dar de baja matrículas manuales activas de su curso; las de API, solo el Supervisor (`GestionMatricula.php:63-68`). Además, `GestorMatriculas::darDeBaja` rechaza la baja en cursos archivados. |
| Pruebas | `FlujosPrincipalesTest::test_el_profesor_da_de_baja_en_su_propio_curso_con_historial_y_auditoria` (línea 264) y `test_el_profesor_no_da_de_baja_en_un_curso_ajeno` (línea 285) |

Conclusión: **sí**, y es coherente en el ERS, la policy, la ruta y el código. El comentario de `routes/web.php:72` ("el Profesor solo consulta") está **desactualizado**: el profesor también da de baja.

## 6. Punto 8: el Supervisor no carga contenido ni notas; el Profesor solo en sus cursos

- Supervisor sin contenido ni notas:
  - `gestionar` y `calificar` exigen el rol profesor (`CursoPolicy.php:62-75`);
  - las rutas `lecciones.index` y `cursos.banco-preguntas` exigen `role:profesor`;
  - **Cumple**.
  - Excepción: el Supervisor sí define el esquema (es su función, RF-54) y sí sube la portada del curso (`Cursos\Formulario`), que es un dato del curso, no contenido.
- Profesor solo en sus cursos:
  - `esDocente()` (titular o adicional) en todas las policies;
  - `scopeBindings` en tareas y quizzes;
  - comprobación de pertenencia en `RecursoLeccionController` (línea 23), `LibroCalificaciones::rubroDelCurso` y `estudianteMatriculado`, y en `Grupos\Index` con `$this->curso->grupos()->findOrFail`;
  - pruebas: `tests/Feature/Seguridad/IdorTest.php` (11) y `AccesoPorRolTest.php` (82 casos);
  - **Cumple**.
- Caso límite: si el Supervisor retira al profesor de un curso sin profesores adicionales, el curso queda con el Supervisor como `profesor_id` (`Admin/Profesores/Index.php:152`). Como `gestionar` exige el rol profesor, nadie puede gestionar ese curso hasta que se asigne otro profesor. Está documentado en el mensaje al usuario (línea 170).
