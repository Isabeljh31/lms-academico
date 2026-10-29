# 11. Pruebas

## 1. Ejecución y resultado real

| Dato | Valor | Evidencia |
|---|---|---|
| Fecha de la ejecución | 13/10/2026 | `junit.xml` en el scratchpad de la sesión anterior |
| Código probado | `93fc323` más los cambios sin commitear; `app/` no cambió después (archivo 07a) | `find -newer` |
| Motor | SQLite en memoria; aislamiento verificado antes de ejecutar | archivo 01 § 4; `phpunit.xml:26-28` (revisado de nuevo el 14/10/2026) |
| Casos | **482** | `junit.xml` |
| Aserciones | **1.468** | `junit.xml` |
| Fallos, errores, omitidas | 0, 0, 0 | `junit.xml` |
| Duración | 106,2 s | `junit.xml` |
| Archivos de prueba | 54 (49 Feature y 5 Unit) más `TestCase` y el trait `Concerns/PreparaEscenarioAcademico` | `tests/` |

La suite completa **no** se volvió a ejecutar en esta sesión. Sí se ejecutaron, una por una, 2 pruebas temporales nuevas guardadas fuera del repositorio (scratchpad de esta sesión), con la misma configuración de SQLite en memoria.

### 1.1 Pruebas temporales de la auditoría (fuera del repositorio)

"Pasa" significa que el defecto existe.

| Prueba | Archivo temporal | Resultado | Hallazgo |
|---|---|---|---|
| `test_h_panel_inicio_muestra_titulo_de_tarea_programada` | `VerificacionAuditoriaTest.php` (sesión anterior) | Pasa | H-01 |
| `test_h_panel_inicio_muestra_tareas_de_curso_dado_de_baja` | idem | Pasa | H-01 |
| `test_h_avance_no_llega_a_100_con_tarea_de_otro_grupo` | idem | Pasa | H-02 |
| `test_h_entrega_grupal_de_integrante_nuevo_borra_nota_de_los_demas` | idem | Pasa | H-04 |
| `test_h_supervisor_reabre_curso_archivado` | idem | Pasa | H-03 |
| `test_h_el_host_reenviado_cambia_las_urls_generadas` | `HostReenviadoTest.php` (esta sesión) | Pasa | H-22 |
| `test_h_el_panel_del_estudiante_muestra_anuncios_de_un_curso_ajeno` | `AnuncioAjenoTest.php` (esta sesión) | Pasa | H-24 |

Ninguna de las 7 pruebas temporales se agregó al repositorio. Todas se pueden convertir en pruebas de regresión que deben **fallar** después de corregir el defecto (archivo 17).

## 2. Mapa por archivo y módulo

| Archivo | Casos | Aserciones | Tiempo (s) | Módulo o tema |
|---|---|---|---|---|
| Auth/AuthenticationTest | 5 | 14 | 10,9 | Login y logout (Breeze) |
| Auth/EmailVerificationTest | 3 | 7 | 0,4 | Verificación de correo (Breeze) |
| Auth/PasswordConfirmationTest | 3 | 9 | 0,6 | Confirmación de contraseña (Breeze) |
| Auth/PasswordResetTest | 4 | 10 | 2,0 | Recuperación de contraseña |
| Auth/PasswordUpdateTest | 2 | 7 | 0,8 | Cambio de contraseña en el perfil |
| Auth/RegistrationTest | 3 | 6 | 0,1 | No existe registro público |
| AdminProfesoresTest | 10 | 25 | 1,1 | Profesores, comunicados |
| AnaliticaRiesgoTest | 3 | 12 | 0,1 | Riesgo de abandono |
| BlackboardDocenteAlumnoTest | 7 | 30 | 10,7 | Flujo docente-alumno |
| BotFaqTest | 5 | 19 | 5,1 | Asistente de IA |
| CalendarioNotificacionesTest | 5 | 6 | 4,4 | Calendario y avisos |
| CalendarioPorRolTest | 6 | 19 | 0,3 | Calendario por rol |
| ClasesEnVivoTest | 3 | 15 | 2,6 | Enlace de videollamada |
| ConfiguracionRegionalTest | 4 | 14 | 1,8 | Zona horaria y formato (RF-108) |
| ContenidoPorSemanasTest | 7 | 43 | 2,7 | Semanas y Prácticas de campo |
| CursoShowEstudianteTest | 4 | 10 | 0,3 | Detalle del curso del estudiante |
| CursosTest | 11 | 20 | 1,5 | Cursos |
| DatosDeEjemploTest | 3 | 24 | 2,1 | Seeders de demostración |
| ExampleTest (Feature) | 1 | 4 | 0,0 | `/` redirige |
| Flujos/CasosDeUsoPrincipalesTest | 8 | 48 | 13,0 | Casos de uso de punta a punta |
| Flujos/CasosDeUsoTest | 36 | 108 | 4,2 | Casos de uso CU-01 a CU-22 |
| Flujos/FlujosPrincipalesTest | 17 | 82 | 7,4 | Flujos del ERS § 9 |
| GruposTest | 7 | 20 | 0,6 | Grupos |
| HojasCalculo/ExportacionesTest | 5 | 20 | 1,5 | Exportación a Excel |
| HojasCalculo/LibroXlsxTest | 5 | 30 | 0,2 | Lector y escritor xlsx |
| ListadosEstudianteTest | 3 | 11 | 0,1 | Listados del estudiante |
| PanelAdminTest | 4 | 11 | 1,4 | Panel del Supervisor |
| PanelInicioEstudianteTest | 5 | 16 | 1,6 | Panel del estudiante |
| ProfileTest | 4 | 16 | 0,9 | Perfil (Breeze) |
| PropuestaBotTest | 10 | 37 | 1,6 | Propuestas del asistente |
| PulidoEstudianteTest | 3 | 5 | 0,1 | Detalles visuales |
| QuizzesTest | 10 | 23 | 0,5 | Quizzes |
| RecursosLeccionTest | 4 | 19 | 0,4 | Recursos de lección |
| RendimientoTest | 7 | 28 | 1,6 | N+1 (RNF-11) |
| ReportesTest | 5 | 6 | 1,6 | Reportes y boleta PDF |
| RevisionPantallasTest | 52 | 156 | 8,6 | Carga de cada pantalla por rol |
| RolesYFuncionalidadTest | 3 | 11 | 0,4 | Roles |
| Seguridad/AccesoPorRolTest | 82 | 209 | 4,2 | Matriz de permisos por ruta |
| Seguridad/ApiIntegracionTest | 14 | 43 | 0,6 | API de matrícula |
| Seguridad/ArchivosPrivadosTest | 8 | 35 | 0,8 | RNF-06 |
| Seguridad/AsistenteIaTest | 6 | 23 | 0,3 | Privacidad del asistente |
| Seguridad/AuditoriaTest | 6 | 14 | 0,5 | Bitácora |
| Seguridad/AutenticacionTest | 14 | 61 | 3,9 | Bloqueo, primer ingreso, cuenta desactivada |
| Seguridad/HttpsTest | 4 | 11 | 0,1 | HTTPS y HSTS |
| Seguridad/IdorTest | 11 | 14 | 0,6 | Acceso a recursos ajenos |
| Seguridad/SubidaArchivosTest | 6 | 22 | 0,4 | Lista blanca y tamaño |
| Seguridad/ValidacionEntradasTest | 7 | 16 | 0,3 | Validación en el servidor |
| TareasTest | 10 | 17 | 0,5 | Tareas |
| UsuariosTest | 8 | 16 | 0,3 | Usuarios |
| Unit/ExampleTest | 1 | 1 | 0,0 | Plantilla |
| Unit/Models/QuizTest | 6 | 10 | 0,3 | Corrección del quiz |
| Unit/Models/TareaTest | 7 | 7 | 0,1 | Estado de la tarea |
| Unit/Services/Asistencia/CalculadoraAsistenciaTest | 4 | 6 | 0,0 | Porcentaje de asistencia |
| Unit/Services/Evaluacion/CalculadoraNotasTest | 11 | 22 | 0,2 | Nota final y condición |

Concentración (hecho): `AccesoPorRolTest` (82) y `RevisionPantallasTest` (52) suman 134 casos (28 %) y comprueban sobre todo códigos de respuesta y textos. Las reglas de negocio están en `CasosDeUsoTest`, `FlujosPrincipalesTest`, las pruebas por módulo y las 4 unitarias.

## 3. Mapa por requisito (menciones explícitas)

Las pruebas citan requisitos en nombres, comentarios o docblocks. Búsqueda de `RF-`, `RNF-` y `CU-` en `tests/`:
- **RF citados (49 de 108):** RF-03, 05, 06, 11, 13, 15, 17, 18, 23, 25, 26, 35, 36, 37, 38, 42, 43, 44, 45, 46, 49, 50, 53, 56, 58, 64, 66, 67, 68, 70, 71, 73, 74, 75, 78, 79, 80, 83, 84, 87, 89, 92, 94, 97, 98, 99, 100, 106 y 108.
- **RNF citados (10 de 21):** RNF-01 a RNF-06, RNF-11 y RNF-21 (más RNF-03 en el nombre de `AccesoPorRolTest`).
- **CU citados (19 de 25):** CU-01 a CU-06, CU-08, CU-09, CU-11 a CU-14, CU-16 a CU-22. Sin mención: CU-07, CU-10, CU-15, CU-23, CU-24 y CU-25.

Que un RF no se cite no significa que no se pruebe: por ejemplo, la clonación (RF-20, RF-21) se prueba en `FlujosPrincipalesTest` y `RolesYFuncionalidadTest`, la baja (RF-29, RF-30) en `FlujosPrincipalesTest` y la API completa (RF-101 a RF-105) en `ApiIntegracionTest` sin citar cada número. La cobertura funcional de cada requisito está en la columna "Evidencia" de los archivos 12a y 12b.

## 4. Componentes y servicios sin pruebas de su lógica

| Elemento | Situación | Evidencia |
|---|---|---|
| `Quizzes\BancoPreguntas` | Solo se prueba que la página carga; guardar, editar y eliminar sin prueba | archivo 07b § 6.1 |
| `Reportes\PanelGestion` | Solo carga del panel | 07b § 7.2 |
| `Notificaciones\Campanita` | Sin prueba de marcar leídas (RF-86) | 07b § 4.1 |
| `Asistencia\MiHistorial` | Carga de la ruta; no se verifica el porcentaje mostrado | 07a § 4.2 |
| `BotSugerencias\ResumenSemanal` | Solo acceso por rol | 07a § 6.2 |
| Boleta PDF | Solo tipo de contenido; no su contenido (H-23) | `ReportesTest:73-96` |
| `PrivatizarArchivos --aplicar` con Cloudinary | Sin prueba (solo disco local) | `ArchivosPrivadosTest` |
| `GestorCuentas` con fallo de correo | No verificado (búsqueda de `Mail::fake` con excepción) | — |
| Tareas grupales con integrante nuevo, grupo eliminado o cambio de grupo | Sin prueba (H-04) | — |
| Avance con tareas grupales asignadas | Sin prueba (H-02) | — |
| Reapertura de curso archivado | No hay prueba que la prohíba (H-03) | — |
| Tiempo límite del quiz con recarga de página | Sin prueba (H-13) | — |
| Anuncios de curso en el panel del estudiante | Sin prueba (H-24) | — |
| `Reportes\Dashboard` con muchos estudiantes | No está en `RendimientoTest` y sí tiene N+1 (`resumenCurso` llama a `avance` por estudiante) | 07b § 7.1 |
| Crecimiento por cantidad de cursos | `RendimientoTest` solo varía la cantidad de estudiantes de un curso; `PanelAdmin\Metricas` y `PanelGestion` hacen consultas por curso | 07b § 5.4, § 7.2 |

## 5. Riesgos que las pruebas no cubren (integraciones reales)

| Dependencia | Qué se prueba | Qué no | Riesgo |
|---|---|---|---|
| **Cloudinary** | `urlTemporal` construye una URL firmada con una configuración falsa (`ArchivosPrivadosTest:121-128`); todas las subidas se prueban con `CLOUDINARY_URL` vacía (disco local) | Subida real con `type=private` y `resource_type=auto`; descarga de PDF; `Uploader::rename` del comando; `destroy` | El archivo 03 § 5 documenta que en producción "toda subida a Cloudinary fallaba" con el SDK 2.x; la corrección está sin commitear y nunca se probó contra una cuenta real |
| **SMTP** | `Mail::fake` (7 usos en 5 archivos) y `Notification::fake` (18 usos en 7 archivos) | Entrega real, plantillas en un cliente de correo, cola con el worker real, cifrado de la cola con la `APP_KEY` de producción | Correos de bienvenida y matrícula que no llegan; enlaces con `APP_URL` incorrecto (archivo 09 § 6) |
| **Supabase / PostgreSQL** | Nada: 0 menciones de `pgsql` o `postgres` en `tests/`; todo corre en SQLite | Diferencias de tipos (`jsonb`, booleanos, `like` sensible a mayúsculas en PostgreSQL), índices, `->change()` de columnas, pooler en modo transacción con `PDO::ATTR_EMULATE_PREPARES` | Búsquedas que distinguen mayúsculas en producción (`like` en PostgreSQL es sensible; en SQLite no), migraciones que fallen en el primer despliegue |
| **Trigger de inmutabilidad de la bitácora** (RF-99) | Solo los eventos `updating`/`deleting` del modelo (`AuditoriaTest:33,41`) | El trigger `pgsql` (no existe en SQLite) | H-05: la protección real de la base no tiene ninguna prueba |
| **IA (Groq o Anthropic)** | `Http::fake` de Groq en 1 archivo (`AsistenteIaTest:33`); modo simulado sin clave | Llamada real, tiempos de 20 s, errores 429 del proveedor, Anthropic | Con clave real, consumo sin límite (H-15) |
| **Proxy de Railway** | `HttpsTest` simula `X-Forwarded-Proto` | `X-Forwarded-For` (H-08) y `X-Forwarded-Host` (H-22) reales | IP del proxy en bitácora y rate limit; enlaces envenenados |
| **Programador y worker** | Los comandos se llaman con `artisan` en las pruebas | `schedule:work` y `queue:work` en el contenedor, solapamientos | Avisos duplicados o no enviados (archivo 09 § 3) |
| **Navegador** | Ninguna prueba de navegador (no hay Dusk ni Playwright) | FullCalendar, Chart.js, Flatpickr desde CDN, Alpine, responsividad | Errores de JS que las pruebas de PHP no ven |

## 6. Calidad de las pruebas

Hechos (búsquedas en `tests/`):
- `RefreshDatabase` en 48 archivos; datos con factories (253 usos de `::factory()`) y con el trait `PreparaEscenarioAcademico` (`escenario()`, `matricular()`, `esquemaValido()`), que crea un curso completo con supervisor, profesor, estudiante, módulo, tarea y quiz.
- Livewire se prueba con `Livewire::test`/`Livewire::actingAs` (216 usos en 35 archivos): se llaman las acciones reales con su autorización.
- Autorización negativa: 67 aserciones de 403 en 21 archivos.
- `assertDatabaseHas` en 15 archivos (41 usos).
- Tiempo controlado con `travelTo`/`setTestNow` en 5 archivos.
- `DataProvider` en 3 archivos (`AccesoPorRolTest`, `RevisionPantallasTest`, `RendimientoTest`).
- `RendimientoTest` mide la cantidad de consultas con 3 y con 15 estudiantes en 7 páginas: buena práctica para RNF-11.
- Nombres de prueba descriptivos en español (por ejemplo, `test_el_profesor_no_da_de_baja_en_un_curso_ajeno`).

Debilidades (opinión, con evidencia):
- Las 2 pruebas más numerosas (134 casos) comprueban sobre todo que la página responde y contiene un texto; no comprueban la regla.
- Varias pruebas de "ver" usan `assertSee` de un texto genérico. Ninguna prueba busca que algo **no** se muestre al estudiante en el panel de inicio (por eso no detectaron H-01 ni H-24).
- No hay pruebas de casos límite de tareas grupales (H-04) ni del avance con grupos (H-02), aunque `GruposTest` existe (7 casos).
- `Unit/ExampleTest` y `Feature/ExampleTest` (este último sí prueba la redirección de `/`) siguen en el repositorio; el primero no aporta.
- `Http::fake` solo existe para Groq; el proveedor Anthropic (`ClaudeBotService`) no tiene prueba de su formato de petición y respuesta.
- La suite tarda 106 s; `AuthenticationTest` (10,9 s, 5 casos) y `BlackboardDocenteAlumnoTest` (10,7 s, 7 casos) son las más lentas por caso. No se investigó la causa.

## 7. Factories y datos de prueba

- 8 factories (archivo 05 § 4). Faltan factories para `Leccion`, `Grupo`, `NotaRubro`, `Comunicado`, `Mensaje`, `SesionClase`, `Asistencia` y `RecursoLeccion`; las pruebas los crean con `create()` directo.
- `DatosDeEjemploTest` (3 casos) ejecuta los seeders de demostración: comprueba que `DemoAcademicoSeeder` genera esquemas que suman 100 %. Los seeders crean usuarios con contraseña conocida (archivo 15).

## 8. Conclusión sobre las pruebas

- Hecho: la suite está en verde (482/482) y cubre bien autorización por rol, IDOR, API, archivos privados, bloqueo de login y la mayoría de flujos del ERS.
- Hecho: 7 defectos confirmados en esta auditoría pasan desapercibidos para la suite actual (H-01, H-02, H-03, H-04, H-22, H-24 y, por lectura, H-13).
- Hecho: ninguna integración externa real (Cloudinary, SMTP, PostgreSQL/Supabase, IA, proxy) está probada. El paso a producción depende de pruebas manuales que no están documentadas como ejecutadas (archivo 15).
