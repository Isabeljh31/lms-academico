# Progreso del trabajo

Archivo para retomar el trabajo sin perder contexto.

## Estado

- [x] Respaldo inicial fuera del repositorio (`../LearnUp_respaldo_20260926_0035`)
- [x] Fase 1 — Análisis: trazabilidad, pantallas, auditoría inicial, línea base de pruebas (128 tests: 97 ok)
- [x] Fase 2 — Módulos 01 a 14 y 18 (respaldo `../LearnUp_respaldo_modulo_14`; suite existente 131/131 en verde)
- [x] Módulo 15: RF-88, RF-89 y RF-90 (panel y reportes). Suite 131/131.
- [x] Módulo 15: RF-91 en `PanelAdmin\Metricas` (nuevos estudiantes, activos/inactivos, completados, abandono, promedio y aprobación por curso; vista sin emojis). Suite 131/131.
- [x] Emojis: ninguno en app/, resources/, routes/, database/, tests/, config/ (última vista: `admin/metricas`, riesgo de abandono, reescrita). Suite 131/131.
- [x] Módulo 16 (IA): contexto restringido, `cursoId` bloqueado, defensa ante inyección. Suite 131/131.
- [x] Módulo 17 (Auditoría): filtros, exportación CSV, trigger de inmutabilidad en PostgreSQL. Suite 131/131.
- [x] Módulo 19 (Configuración): nota mínima y parámetros generales con auditoría. Suite 131/131.
- [x] Limpieza de Tailwind 4 (`@tailwindcss/vite` quitado; build idéntico; npm audit 0). Suite 131/131.
- [x] Fase 2 completa (respaldo `../LearnUp_respaldo_fase_2`).
- [x] Fase 3a — Unitarias (`tests/Unit`: CalculadoraNotas, CalculadoraAsistencia, Tarea, Quiz) y seguridad (`tests/Feature/Seguridad`: acceso por rol, autenticación, IDOR, subida de archivos, API, auditoría, IA, validación de entradas). Suite 299/299.
- [x] Fase 3b — Funcionales (`tests/Feature/Flujos`), composer audit y npm audit limpios. Suite 332/332 (respaldo `../LearnUp_respaldo_fase_3`).
- [x] Fase 4 — 51 pantallas probadas y capturadas (escritorio y móvil), `docs/PANTALLAS.md` actualizado. Suite 384/384 (respaldo `../LearnUp_respaldo_fase_4`).
- [x] Fase 5 — Auditoría final: `docs/AUDITORIA.md` (11 secciones), estado final en `docs/TRAZABILIDAD.md`, notificación de nota de rubro (RF-84). Suite 385/385 (respaldo `../LearnUp_respaldo_fase_5`).

Trabajo terminado. Nada se subió a git: el commit queda a cargo del usuario.

## Notas para retomar

- Rol de supervisor en código: `supervisor` (enum `App\Enums\Rol`). No renombrar.
- Estilo del Estudiante: no se cambia. El layout `layouts/app.blade.php` ya separa por rol con `$esEstudiante`;
  solo se editan las ramas de Profesor/Supervisor. Componentes nuevos de gestión en `components/gestion/`.
- Iconos: `<x-icono nombre="...">` (Heroicons outline).
- BD de pruebas: SQLite `:memory:` (`phpunit.xml`). La BD local es SQLite; no se toca Supabase.
- Resumen de pruebas: `php artisan test --compact | php <scratchpad>/resumen.php`.

## Auditoría de procesos y flujos (segundo pedido, 2026-09-26)

Numeración: el pedido usa la numeración del ERS v1 (con RF-52 y RF-61 duplicados). La equivalencia con el ERS v2
queda en `docs/TRAZABILIDAD.md`, sección "Auditoría de procesos".

- [x] Respaldo `../LearnUp_respaldo_procesos_inicio`.
- [x] ARQUITECTURA.md § 11 (Supervisor + sin registro público): ya ejecutado en código; se actualiza su estado.
- [x] 3.1 Autenticación: la recuperación de contraseña ahora desbloquea la cuenta (RF-06/RF-08). Suite 393/393.
- [x] 3.2 Usuarios: importación directa desde Excel `.xlsx` (RF-12) con lector propio sin dependencias
      (`App\Services\HojasCalculo`), más escritor `.xlsx` con formato para las exportaciones. Suite 393/393.
- [x] Matriz de permisos (ERS § 5): el Supervisor queda en solo lectura en contenido, tareas, quizzes, notas,
      asistencia y grupos (`supervisar`); crear contenido y calificar es solo del Profesor del curso. Suite 400/400.
- [x] 3.3 Cursos: RF-24 (pedido) / RF-23 (ERS v2) — profesores adicionales (`curso_profesor`). Suite 400/400.
- [x] 3.4 Matrícula: el Profesor da de baja en su curso (matrículas manuales); API solo el Supervisor. Suite 400/400.
- [x] 3.5 Contenido: aviso automático del contenido Programado al publicarse (`contenido:notificar-programado`,
      cada 5 min). Suite 403/403.
- [x] 3.6 Tareas y quizzes: aviso de cambio de fecha límite de un quiz; la clonación copia el banco de preguntas.
      Suite 403/403.
- [x] 3.7 Calificaciones: exportación a Excel con formato (resumen, notas por rubro, práctica). Suite 409/409.
- [x] 3.8 Progreso: RF-62 (pedido) / RF-67 (ERS v2) — condición de completitud de lección y módulo definida por el
      Profesor. Suite 409/409.
- [x] 3.9 Asistencia: RF-68 (pedido) / RF-73 (ERS v2) — el Profesor omite o reactiva la asistencia de su curso.
- [x] 3.10 Grupos, 3.12 Mensajería, 3.13 Notificaciones, 3.14 Reportes, 3.17 API: cumplen (revisados; co-docentes
      incluidos en mensajería, calendario y paneles).
- [x] 3.15 IA: el contexto incluye el calendario del estudiante (quizzes abiertos, sesiones, evaluaciones, eventos
      propios) para el plan de estudio (RF-87 pedido / RF-92 ERS).
- [x] 3.16 Auditoría: exportación a Excel con hoja de filtros y resumen por tipo. Suite 413/413.
- [x] Datos de ejemplo (`DemoAcademicoSeeder`) cargados en SQLite local. Excel verificado en Microsoft Excel.
- [x] Nota final "En curso" mientras falten rubros (hallazgo con datos reales). Suite 421/421.
- [x] Diseño: calendario por rol (Estudiante, Profesor, Supervisor). Suite 426/426.
- [x] Revisión visual de pantallas de los 3 roles con datos de ejemplo y correcciones. Suite 428/428.
- [x] TRAZABILIDAD.md § 4 (RF del pedido → ERS v2), AUDITORIA.md § 12 (reglas, flujos, permisos, decisiones, diseño), PANTALLAS.md.
- [x] Segunda auditoría terminada. Respaldo `../LearnUp_respaldo_procesos_fin`. Nada se subió a git.
- [x] RF-108 completo: zona horaria y formato de fecha aplicados en toda la plataforma. RF del ERS v2: 106 Completo,
      0 Parcial, 2 No aplica. Suite 432/432. Respaldo `../LearnUp_respaldo_rf108_fin`. Nada se subió a git.
