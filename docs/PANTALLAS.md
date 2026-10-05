# Pantallas por rol

Inventario de pantallas (ruta → componente → vista) y resultado de la revisión de la Fase 4.

## Cómo se revisó

1. **Prueba HTTP por pantalla** — `tests/Feature/RevisionPantallasTest.php` (51 casos): cada pantalla carga con
   el rol correspondiente y datos reales, responde 200, muestra su texto clave y **no contiene emojis**.
2. **Matriz de acceso** — `tests/Feature/Seguridad/AccesoPorRolTest.php`: cada ruta con Supervisor, Profesor,
   Estudiante e invitado (82 casos), más profesor ajeno, estudiante no matriculado, dado de baja y curso en borrador.
3. **Capturas** — Chrome headless sobre el HTML renderizado (`CAPTURAR_PANTALLAS=<carpeta> php artisan test
   tests/Feature/RevisionPantallasTest.php`): 51 pantallas × escritorio (1366 px) y móvil (500 px, el mínimo de
   Chrome headless; por debajo del punto `sm` de 640 px).
   Limitación: sin JavaScript no corren Alpine ni Livewire, así que en las capturas los valores de los campos
   `wire:model` aparecen vacíos y las pestañas se muestran todas a la vez. Se verificó la disposición, la paleta,
   los iconos, los estados vacíos y el desbordamiento horizontal.
4. **Estudiante sin cambios de estilo** — comparación con `git diff` (solo lectura) de cada vista del Estudiante
   contra el commit `8ead19b` (ver la tabla del Estudiante).

Leyenda: Revisado = carga, sin errores, sin emojis, responsivo, permisos probados.

## Pantallas del Estudiante (el estilo no se cambió)

| # | Pantalla | Ruta | Componente / vista | Revisión |
|---|---|---|---|---|
| E-01 | Iniciar sesión | `/login` | Volt `pages.auth.login` | Revisado. Sin cambios de vista. |
| E-02 | Cambio de contraseña inicial | `/primer-login` | `Auth\CambiarPasswordPrimerLogin` | Revisado. Vista sin cambios; se corrigió la lógica (ya no escribe la contraseña en el log). |
| E-03 | Recuperar contraseña | `/forgot-password` | Volt | Revisado. Sin cambios. |
| E-04 | Panel de inicio | `/dashboard` | `Estudiante\PanelInicio` + `dashboard.blade.php` | Revisado. Único cambio: emoji del aviso de comunicado → icono del mismo tamaño. |
| E-05 | Mis cursos | `/cursos` | `Cursos\Index` | Revisado. Emoji de fecha → icono; subtítulo "Catálogo de cursos disponibles" → "Tus cursos matriculados" (ya no hay catálogo público). Solo muestra cursos con matrícula activa y visibles. |
| E-06 | Detalle de curso | `/cursos/{curso}` | `Cursos\Show` | Revisado. Mismas clases. Agregados con el estilo existente: botón "Mi Asistencia" (igual a "Mi Boleta") y botón "Marcar como completada" (igual a "Material Adjunto"). Corregido: `</span</p>` roto y la fecha de vencimiento (mostraba "Sin fecha límite"). Solo se muestra contenido publicado. **27/09:** pestaña Contenido organizada por semanas (Prácticas de campo primero, luego Semana 1..N con fechas, tema, progreso "X de Y completados" e ícono por tipo de recurso; incluye las tareas y quizzes de cada semana). |
| E-07 | Tareas del curso | `/cursos/{curso}/tareas` | `Tareas\Index` | Revisado. Mismas tarjetas; el estado usa los de RF-43 ("No entregado", "Fuera de plazo", "Devuelto"). |
| E-08 | Detalle y entrega de tarea | `/cursos/{curso}/tareas/{tarea}` | `Tareas\Detalle` | Revisado. Mismas clases. Agregados con el mismo estilo del formulario: respuesta escrita, enlace, formatos permitidos, instrucciones y reentrega. Corregidos: fecha de vencimiento (usaba un campo inexistente) y nota mínima configurable. Símbolo del recibo → icono. |
| E-09 | Quizzes del curso | `/cursos/{curso}/quizzes` | `Quizzes\Index` | Revisado. Mismas tarjetas; muestra el mejor intento. |
| E-10 | Rendir quiz | `/cursos/{curso}/quizzes/{quiz}` | `Quizzes\Resolver` | Revisado. Mismas clases de tarjeta y colores; agregados: opción múltiple, tiempo restante, intentos, retroalimentación y "Nuevo intento". |
| E-11 | Boleta por rubros | `/cursos/{curso}/boleta` | `Cursos\BoletaEstudiante` | Revisado. Emojis → iconos del mismo tamaño; "Reprobado" → "Desaprobado". |
| E-12 | Boleta PDF | `/cursos/{curso}/boleta.pdf` | `Reportes\BoletaPdfController` | Revisado. Sin cambios. |
| E-13 | Mi progreso | `/reportes` | `Reportes\Dashboard` | Revisado. Mismas clases; el número principal es la nota final ponderada (en rojo bajo la mínima) y se muestra el % de avance. |
| E-14 | Calendario | `/evento-calendario` | `EventoCalendario\Index` | Revisado. Vista sin cambios; ahora incluye quizzes, sesiones y evaluaciones. |
| E-15 | Mensajería | `/mensajeria` | `Mensajeria\Bandeja` | Revisado. Vista sin cambios de estilo (texto "10 MB" y `rel="noopener"`). |
| E-16 | Propuestas del bot | `/bot/propuestas` | `BotSugerencias\Bandeja` | Rediseñada (27/09): jerarquía por estado, color por curso, ícono por tipo, selector de fecha del sistema y "Ver en calendario". Ver `docs/CAMBIOS.md`. |
| E-17 | Widget del asistente | todas | `BotSugerencias\Widget` | Revisado. Vista sin cambios. |
| E-18 | Campanita | todas | `Notificaciones\Campanita` | Revisado. Sin cambios. |
| E-19 | Perfil | `/profile` | `profile.blade.php` | Revisado. Se quitó el formulario de borrado de cuenta (RF-17). |
| E-20 | Mi asistencia (nueva) | `/cursos/{curso}/mi-asistencia` | `Asistencia\MiHistorial` | Revisado. Nueva, con las mismas clases de la Boleta. |

## Pantallas del Profesor (rediseñadas con `components/gestion`)

| # | Pantalla | Ruta | Componente | Revisión |
|---|---|---|---|---|
| P-01 | Panel | `/dashboard` | `Reportes\PanelGestion` | Revisado. Indicadores RF-88, lista de cursos, comunicados. |
| P-02 | Mis cursos | `/cursos` | `Cursos\Index` | Revisado. Tarjetas sin degradado, estado vacío. |
| P-03 | Gestión del curso | `/cursos/{curso}` | `Cursos\Show` | Revisado. **27/09:** "Contenido por semanas": Prácticas de campo y Semana 1..N (duración del curso), con fechas, clases de la semana, estado, preparar/editar/mover/vaciar; anuncios. |
| P-04 | Lecciones | `/modulos/{modulo}/lecciones` | `Lecciones\Index` | Revisado. Subida de archivo, estado, orden, % de completadas. |
| P-05 | Tareas / crear / editar | `/cursos/{curso}/tareas…` | `Tareas\Index`, `Tareas\Formulario` | Revisado. Formulario por secciones con validación visible. |
| P-06 | Entregas de una tarea | `/cursos/{curso}/tareas/{tarea}` | `Tareas\Detalle` | Revisado. Calificar, devolver, sin entregar. |
| P-07 | Quizzes / formulario / estadísticas | `/cursos/{curso}/quizzes…` | `Quizzes\*` | Revisado. Tipos de pregunta, configuración, recálculo con confirmación. |
| P-08 | Banco de preguntas (nueva) | `/cursos/{curso}/banco-preguntas` | `Quizzes\BancoPreguntas` | Revisado. |
| P-09 | Libro de calificaciones | `/cursos/{curso}/libro-calificaciones` | `Cursos\LibroCalificaciones` | Revisado. Nota final en rojo bajo la mínima, CSV, pestaña de práctica. Peso sin ceros de relleno. |
| P-10 | Grupos | `/cursos/{curso}/grupos` | `Grupos\Index` | Revisado. Renombrar, mover. |
| P-11 | Asistencia | `/cursos/{curso}/asistencia` | `Asistencia\Index` | Revisado. Pase de lista, resumen por estudiante. |
| P-12 | Por calificar | `/por-calificar` | `Docente\PorCalificar\Index` | Revisado. |
| P-13 | Reportes | `/reportes` | `Reportes\Dashboard` | Revisado. RF-89 completo. |
| P-14 | Calendario | `/evento-calendario` | `EventoCalendario\Index` | Revisado. |
| P-15 | Mensajería | `/mensajeria` | `Mensajeria\Bandeja` | Revisado. |
| P-16 | Resumen del bot | `/bot/resumen` | `BotSugerencias\ResumenSemanal` | Revisado. |
| P-17 | Matrícula (consulta) | `/cursos/{curso}/matriculas` | `Cursos\GestionMatricula` | Revisado. Solo lectura para el Profesor. |

## Pantallas del Supervisor (rediseñadas con `components/gestion`)

| # | Pantalla | Ruta | Componente | Revisión |
|---|---|---|---|---|
| S-01 | Panel | `/dashboard` | `Reportes\PanelGestion` | Revisado. RF-90. |
| S-02 | Usuarios | `/admin/usuarios` | `Usuarios\Index` | Revisado. Crear, editar (antes roto), activar/desactivar con confirmación. |
| S-03 | Importar CSV | `/usuarios/importar` | `Usuarios\ImportarCSV` | Revisado. Antes roto. |
| S-04 | Profesores | `/admin/profesores` | `Admin\Profesores\Index` | Revisado. |
| S-05 | Cursos / crear / editar | `/cursos…` | `Cursos\Index`, `Cursos\Formulario` | Revisado. |
| S-06 | Detalle y clonación | `/cursos/{curso}` | `Cursos\Show` | Revisado. Modal de clonación. |
| S-07 | Esquema de evaluación | `/cursos/{curso}/esquema` | `Cursos\EsquemaEvaluacion` | Revisado. Suma en vivo, bloqueo si no suma 100 %. |
| S-08 | Matrícula | `/cursos/{curso}/matriculas` | `Cursos\GestionMatricula` | Revisado. Confirmación de baja con motivo. |
| S-09 | Comunicados | `/admin/comunicados` | `Admin\Comunicados\Index` | Revisado. Publicación programada. |
| S-10 | Dashboard institucional | `/admin/metricas` | `PanelAdmin\Metricas` | Revisado. RF-91. |
| S-11 | Riesgo de abandono | `/admin/analitica-riesgo` | `Admin\Metricas\Index` | Revisado. |
| S-12 | Configuración | `/admin/configuracion` | `PanelAdmin\Configuracion` | Revisado. RF-107/RF-108. |
| S-13 | Integración API (nueva) | `/admin/integraciones` | `PanelAdmin\Integraciones` | Revisado. RNF-04. |
| S-14 | Auditoría | `/admin/auditoria` | `PanelAdmin\Auditoria` | Revisado. |
| S-15 | Reportes | `/reportes` | `Reportes\Dashboard` | Revisado. |

## Pantallas de invitado

| # | Pantalla | Ruta | Revisión |
|---|---|---|---|
| I-01 | Bienvenida | `/` | Revisado. El enlace a registro desaparece solo (usa `Route::has('register')`). |
| I-02 | Registro | `/register` | Eliminada: responde 404 (ERS 2.4). |
| I-03 | Login / recuperación | ver E-01, E-03 | Revisado. |

## Observaciones pendientes

- `APP_NAME` en `.env` es "LMS Academico"; el producto se llama LearnUp. No se tocó el `.env`; basta con cambiar
  `APP_NAME=LearnUp` para que el nombre aparezca en el menú y en los títulos.

## Segunda auditoría (27/09/2026): revisión con datos de ejemplo y JavaScript

Las pantallas se revisaron de nuevo con los datos de ejemplo (`DemoAcademicoSeeder`) y capturas reales con JavaScript:
cada página se renderizó con el kernel de Laravel y un usuario demo, y se capturó con Chrome headless a 1440 y 500 px.
Así sí corren Alpine, Livewire y FullCalendar, que en la revisión anterior quedaban fuera.

| Pantalla | Cambio |
|---|---|
| Calendario (E, P y S) | Rediseño por rol: agenda de la semana, cursos con color y filtro, filtro por tipo, eventos coloreados por curso, modal de nuevo evento, vista "Agenda" en móvil. Nueva prueba "S calendario". |
| Libro de calificaciones (P, S) | Nota final "En curso" mientras falten rubros; indicadores "Promedio a la fecha" y "Aprobación" sobre esquemas completos; exportación a Excel. |
| Boleta (E) | "En curso: x de y rubros calificados" (mismas clases); pesos sin ceros de relleno. |
| Panel (E) | "Próxima entrega" futura; cursos archivados fuera; "Ver mis cursos". |
| Panel y reportes (P) | Solo cursos en marcha en los indicadores; los archivados con etiqueta; nuevo criterio de bajo rendimiento. |
| Dashboard institucional y riesgo (S) | Sin cursos archivados en atraso y riesgo; "rendimiento a la fecha". |
| Curso (P, S) | Profesores adicionales en el encabezado; portada vacía con el código; el Supervisor ve el curso en solo lectura. |
| Detalle de tarea (P) | Entregas por calificar primero. |
| Asistencia (P, S) | El Profesor omite o reactiva el control; el Supervisor consulta. |
| Matrícula (P) | El Profesor puede dar de baja en su curso (matrículas manuales). |
| Lecciones y módulos (P) | Condición de completitud (RF-67). |
| Usuarios (S) | "Importar usuarios" (Excel o CSV); paginación en español. |
| Bitácora (S) | Exportación a Excel. |
