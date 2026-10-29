# 14. Calidad del código y rendimiento

Método: script de conteo de métodos por tokens de PHP (scratchpad, `metodos.php`), búsquedas de patrones, `composer show --direct`, `npm ls --depth=0`, `composer audit` y `npm audit` (14/10/2026). No se ejecutó Pint (las reglas del proyecto lo reservan para después de modificar código) ni se midieron tiempos en producción.

## 1. Consultas N+1 y consultas que crecen con los datos

`tests/Feature/RendimientoTest.php` verifica que 7 páginas no hagan más consultas al pasar de 3 a 15 estudiantes en **un** curso: libro de calificaciones, detalle del curso (profesor), tareas, por calificar, matrícula, dashboard institucional y riesgo de abandono. Lo siguiente queda fuera de esa prueba:

| Dónde | Patrón | Crece con | Evidencia |
|---|---|---|---|
| `Reportes\Dashboard` (profesor y Supervisor) | `ProgresoAcademicoService::resumenCurso` llama a `avance()` por estudiante (unas 4 consultas cada uno) y se repite por curso; el Supervisor recorre **todos** los cursos | estudiantes × cursos | `ProgresoAcademicoService.php:188`; `Reportes/Dashboard.php:53-57` |
| `PanelAdmin\Metricas::rendimientoPorCurso` | `inscripcionesActivas()->pluck()` y `resumenDelCurso()` por curso | cursos | `PanelAdmin/Metricas.php:87-88` |
| `Reportes\PanelGestion` | Promedio por curso con 2 consultas; el Supervisor calcula todos los cursos archivados | cursos | `PanelGestion.php:48-54,82-85` |
| `EventoCalendario\Index::notasPendientes` (Supervisor) | Conteo por curso y por rubro | cursos × rubros | `EventoCalendario/Index.php:449-456` |
| `Admin\Profesores\Index::render` | Conteo de estudiantes por profesor y carga de todas las entregas de sus cursos | profesores (8 por página) y entregas | `Admin/Profesores/Index.php:180-207` |
| `Cursos\Show` (estudiante) | `resultadoDe()` por quiz; `TareaPolicy::ver` por tarea (consulta de pertenencia al grupo en tareas grupales) | quizzes y tareas del curso | `Cursos/Show.php:220,598`; `TareaPolicy.php:47-48` |
| `PropuestaBotService::detectarAtrasos` (diario) | 2 consultas por matrícula activa más una búsqueda de duplicados en PHP | matrículas | `PropuestaBotService.php:58-112` |
| `NotificarContenidoProgramado` (cada 5 min) | `Gate::allows('ver')` por estudiante en cada tarea o quiz programado | estudiantes × contenido | `NotificarContenidoProgramado.php:104-114` |
| `Usuarios\ImportarCSV::importar` | Una consulta `exists` y una creación con auditoría por fila (hasta 1000) | filas | `ImportarCSV.php:101-143` |
| `BotSugerencias\Widget::enviar` | Unas 8 consultas para el contexto más la llamada HTTP | mensajes | `ConstruyeContextoIA.php:43-165` |
| Todas las páginas | `Campanita` (2 consultas) y `aplicarZonaHoraria` (1 consulta) | peticiones | `Campanita.php:31-32`; `AppServiceProvider.php:89-91` |

Opinión: con el volumen de una institución pequeña (cientos de estudiantes) no es crítico; el `Reportes\Dashboard` del Supervisor es el primero que se degradará porque multiplica estudiantes por cursos. RNF-11 (≤ 3 s) no se midió en producción.

Consultas que cargan colecciones completas en memoria (sin paginar): `Admin\Metricas\Index` (todos los estudiantes activos y sus matrículas), `PanelAdmin\Metricas::cursosConMasAtraso` (todas las tareas vencidas con sus entregas), `Comunicado::destinatarios()` (todos los usuarios de un rol), `PanelGestion::quizzesActivos` (filtra en PHP).

## 2. Índices que faltan (de 04b § 4)

`entregas (tarea_id, estudiante_id)` sin índice único; sin índice en `entregas.estudiante_id`, `tareas.curso_id`, `resultados_quiz (quiz_id, estudiante_id)` ni `bitacoras_auditoria.created_at`. En PostgreSQL, `foreignId()->constrained()` no crea índice: las consultas de los N+1 anteriores recorren tablas sin índice al crecer.

## 3. Métodos largos (más de 40 líneas)

39 métodos superan 40 líneas. Los 15 más largos:

| Líneas | Método | Observación |
|---|---|---|
| 104 | `Livewire/Cursos/Formulario::guardar` (línea 85) | Valida, comprueba roles, publica, sube portada, guarda, sincroniza y audita |
| 82 | `Livewire/Lecciones/Index::guardar` (115) | Mezcla reglas de archivo y enlace |
| 81 | `Livewire/Admin/Metricas/Index::render` (47) | Regla de riesgo dentro de `render` |
| 75 | `Services/Reportes/ProgresoAcademicoService::resumenDashboardEstudiante` (229) | — |
| 75 | `Livewire/Quizzes/Formulario::persistir` (208) | Guarda, recalcula, audita y notifica |
| 73 | `Models/Curso::clonar` (250) | Clonación completa en el modelo |
| 71 | `Livewire/EventoCalendario/Index::eventos` (194) | — |
| 70 | `Livewire/Cursos/EsquemaEvaluacion::guardar` (83) | — |
| 67 | `Livewire/Usuarios/ImportarCSV::importar` (82) | — |
| 67 | `Http/Controllers/Api/MatriculaController::sincronizar` (29) | — |
| 66 | `Services/HojasCalculo/HojaXlsx::xml` (194) | Generación de XML |
| 66 | `Livewire/Tareas/Detalle::entregar` (110) | Contiene la regla con el defecto H-04 |
| 62 | `Services/HojasCalculo/LibroXlsx::estilos` (163) | XML de estilos |
| 60 | `Livewire/Tareas/Formulario::guardar` (86) | — |
| 58 | `Services/Matricula/GestorMatriculas::sincronizarDesdeApi` (87) | — |

Clases más grandes: `Cursos\Show` (601 líneas, 32 métodos), `EventoCalendario\Index` (468), `Quizzes\Formulario` (412), `LibroCalificaciones` (349). Vistas más grandes: `cursos/show.blade.php` (668), `evento-calendario/index.blade.php` (590), `estudiante/panel-inicio.blade.php` (304).

## 4. Duplicación

| Patrón | Copias | Evidencia | Riesgo |
|---|---|---|---|
| Calificar una tarea (actualizar nota, auditar, notificar) | 3, con reglas distintas (grupo, notificación, motivo, decimales) | `Tareas/Detalle::calificar`, `Docente/PorCalificar/Index::guardarCalificacion`, `Cursos/LibroCalificaciones::guardarNotaTarea` | H-16; corregir un caso no corrige los otros |
| "Estudiantes con matrícula activa" (`inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')`) | 10 | `NotificarContenidoProgramado:106`, `Asistencia/Index:232`, `Cursos/Show:589`, `Grupos/Index:182`, `Lecciones/Index:187`, `Quizzes/Formulario:266`, `Quizzes/Index:51`, `Tareas/Formulario:135`, `Tareas/Index:68`, `ProgresoAcademicoService:179` | Unos filtran `activo` del usuario y otros no |
| Estado "borrador" = `visible=false` + `estado_contenido='publicado'` | 2 | `Tareas/Formulario:120-121`, `Quizzes/Formulario:224-225` | Dos campos para un concepto (05) |
| Publicar u ocultar con notificación | 2 | `Tareas/Index::alternarVisibilidad`, `Quizzes/Index::alternarVisibilidad` | — |
| Autorización por `abort_unless(hasRole('supervisor'))` en lugar de policy | 20 llamadas en 7 componentes | `Admin/*`, `PanelAdmin/*` | Dos estilos de autorización (03) |
| Regla de pesos al 100 % | 2 | `EsquemaEvaluacion::pesosValidos` (sin uso) y `CalculadoraNotas::pesosSumanCien` | — |
| Visibilidad de contenido para el estudiante | Scopes correctos, pero 3 consultas no los usan | `PanelInicio::eventosDelMes` (H-01), `ProgresoAcademicoService::avance` (H-02), `dashboard.blade.php` (H-24) | Origen de 3 defectos Altos |

## 5. Código muerto o sin uso

| Elemento | Evidencia |
|---|---|
| `EsquemaEvaluacion::pesosValidos()` y `sumaPesos()`, `RubroEvaluacion::notaDeEstudiante()`, `SesionClase::porcentajeAsistencia()`, `BancoPregunta::opcionesCorrectas()` | 05 § 3 |
| `Asistencia::badge()` | Sin llamadas en `app/` ni `resources/views` |
| `PropuestaBotService::leerCalendario()`, `CalculadoraNotas::notasFinalesDelCurso()`, `ProgresoAcademicoService::notaFinalPorRubros()` | 08 |
| `CalculadoraAsistencia::porcentajeDe()` | Solo en pruebas |
| `Leccion::completadaPor()` | Solo en pruebas (05) |
| `Cursos\Show::$moduloMaterialId` y `getAnunciosDelCursoProperty()` | Sin uso fuera de la clase ni en la vista |
| `BotSugerencias\Widget::$enviando` | Sin uso fuera de la clase |
| Campo `esquemas_evaluacion.bloqueado` | Nunca se pone en true (04b § 5) |
| Vista `livewire/welcome/navigation.blade.php` | `welcome.blade.php` eliminada (06 § 1.6) |
| Volt `verify-email` y `confirm-password` | `User` no implementa `MustVerifyEmail`; ninguna ruta propia usa `password.confirm` (07b § 10) |
| Ruta de Volt a `resources/views/pages` | La carpeta no existe (09 § 7.2) |
| 1.281 clases `dark:` | El modo oscuro nunca se activa (10 § 8) |
| Comando `inspire` | Plantilla de Laravel (`routes/console.php:7-9`) |
| `tests/Unit/ExampleTest.php` | Plantilla (`assertTrue(true)`) |

## 6. Marcadores TODO, FIXME y similares

Búsqueda de `TODO`, `FIXME`, `XXX` y `HACK` en `app/`, `routes/`, `config/`, `database/`, `resources/views`, `resources/js` y `tests/`: **ninguno**. Comentarios con artefactos `[cite: 1]` en `PropuestaBot.php` y `ConversacionBot.php` (05 § 1).

## 7. Comentarios desactualizados (detalle en el archivo 16)

- `config/services.php:38-40` (disco "public" sin Cloudinary).
- `GroqProveedor.php:16-18` y `ConstruyeContextoIA` ("Llama 3.3 70B").
- `routes/web.php:72` ("el Profesor solo consulta").
- `Docente/PorCalificar/Index.php:17-20` ("cursos cuyo titular es el usuario").
- `User.php:111` (docblock de RF-23 sobre otro método).
- `tailwind.config.js:13-17` ("nada la activa hoy" es cierto, pero las vistas siguen llenas de `dark:`).

## 8. Dependencias: versiones declaradas e instaladas

### 8.1 Composer (directas)
| Paquete | Declarada | Instalada | Observación |
|---|---|---|---|
| php | ^8.3 | 8.3.33 (CLI local) | Railway (Nixpacks) elige la versión según `composer.json`; no verificado cuál usa |
| laravel/framework | ^13.17 | 13.30.1 | — |
| livewire/livewire | ^3.6.4 | 3.8.7 | — |
| livewire/volt | ^1.7.0 | 1.11.2 | — |
| spatie/laravel-permission | ^8.3 | 8.3.0 | — |
| barryvdh/laravel-dompdf | ^3.1 | 3.1.2 | — |
| cloudinary/cloudinary_php | ^1.20 | 1.20.2 | SDK 1.x heredado; el código usa la API estática 1.x (`\Cloudinary`, `Uploader`). Opinión: migrar al SDK actual cuando se pruebe con la cuenta real |
| laravel/tinker | ^3.0 | 3.0.2 | — |
| Desarrollo: laravel/boost, breeze, pail, pao, pint, faker, mockery, collision, phpunit | — | 2.9.0, 2.4.2, 1.2.7, 1.1.4, 1.30.5, 1.24.1, 1.6.15, 8.9.5, 12.5.34 | Breeze sigue instalado aunque su andamiaje ya se adaptó |

### 8.2 npm
| Paquete | Declarada | Instalada | Observación |
|---|---|---|---|
| @fullcalendar/* (5) | ^6.1.21 | 6.1.21 | Producción (bundle) |
| chart.js | ^4.5.1 | 4.5.1 | Producción (bundle) |
| tailwindcss | ^3.1.0 | 3.4.19 | Desarrollo; la corrección de sus avisos exige Tailwind 4 (cambio mayor) |
| vite | ^8.0.0 | 8.2.2 | Desarrollo |
| laravel-vite-plugin | ^3.1 | 3.2.0 | Desarrollo |
| @tailwindcss/forms, autoprefixer, postcss | ^0.5.2, ^10.4.2, ^8.4.31 | 0.5.11, 10.5.5, 8.5.28 | Desarrollo |
| concurrently | ^10.0.3 | 10.0.5 | Desarrollo; aviso crítico vía `shell-quote` |
| @laravel/multiplex (opcional) | ^0.4.1 | no instalado | `UNMET OPTIONAL DEPENDENCY` |

Node local: v24.19.0. No hay campo `engines` en `package.json`: la versión de Node en Railway la decide Nixpacks (no verificado).

### 8.3 Auditorías de dependencias
- `composer audit`: `league/commonmark` 2.10.0 con 2 avisos (alto y medio), dependencia de producción; ver 13 § 12.2.
- `npm audit`: 10 avisos (2 críticos, 6 altos, 2 moderados), **todos en dependencias de desarrollo**; `npm audit --omit=dev`: 0.

## 9. Estilo y estructura

- Hecho: convenciones consistentes: nombres en español, docblocks que citan RF del ERS, componentes por carpeta de módulo, servicios por módulo (RNF-17).
- Hecho: lógica de negocio en componentes Livewire (riesgo de abandono, entrega grupal, calificación) y en modelos (`Curso::clonar`, `Quiz::recalcularResultados`), además de los servicios (03 § 1).
- Hecho: no hay `Model::shouldBeStrict()` ni `preventLazyLoading()` en desarrollo, que ayudarían a detectar N+1 antes de producción.
- Hecho: no hay análisis estático (PHPStan/Larastan) configurado (no hay `phpstan.neon` en la raíz; archivo 01 § 6.3).
- Opinión: la calidad general es buena para un proyecto académico (pruebas amplias, nombres claros, autorización consistente); los puntos débiles son la duplicación de reglas críticas y las consultas de paneles que no reutilizan los *scopes* de visibilidad.

## 10. Hallazgos de este archivo

No se crean hallazgos nuevos de severidad propia: los problemas de rendimiento y duplicación se agrupan en H-31.

| ID | Severidad | Resumen |
|---|---|---|
| H-31 | Bajo | N+1 en `Reportes\Dashboard`, `PanelAdmin\Metricas`, `PanelGestion` y calendario del Supervisor, fuera de `RendimientoTest`; 3 copias de la lógica de calificación |
