# LearnUp — briefing del proyecto

Resumen autocontenido para quien llega nuevo al proyecto. Estado al 05/10/2026. Fuente de requisitos:
`ERS_LearnUp_v2.docx`. El detalle está en `docs/AUDITORIA.md`, `docs/TRAZABILIDAD.md`, `docs/CAMBIOS.md` y
`ARQUITECTURA.md` § 0, pero no hace falta leerlos para entender el proyecto.

## 1. Qué es LearnUp

LearnUp es un **LMS académico puro**: gestiona el contenido de los cursos, la matrícula académica, la evaluación y el
seguimiento de estudiantes que ya existen en el sistema. Una institución puede operarlo a mano (el Supervisor crea
cuentas y matricula) o conectar su propio sistema (SIS/ERP) mediante una API, que sincroniza las matrículas
automáticamente.

Tiene tres roles:
- **Estudiante:** consume el contenido de sus cursos (organizado por semanas), entrega tareas, rinde quizzes, ve sus
  notas por rubro, su asistencia y su calendario, se comunica por mensajería y usa un asistente de IA.
- **Profesor:** gestiona el contenido de los cursos que dicta, crea tareas y quizzes, califica, registra notas,
  asistencia y grupos, y consulta reportes. Un curso puede tener un titular y profesores adicionales.
- **Supervisor:** administra cuentas (individual y por Excel/CSV), cursos y su esquema de evaluación, matrícula,
  clonación entre ciclos, comunicados, configuración general, API Keys y la bitácora de auditoría. Consulta el contenido
  y las notas, pero no los carga.

**Qué no es** (ERS 1.2.2): no cobra ni factura, no tiene cupones, no tiene portal público de marketing ni catálogo de
venta, **no tiene registro público** (toda cuenta la crea el Supervisor o llega por la API) y no emite certificados.
Si la institución necesita eso, lo resuelve en su sistema externo.

## 2. Stack real (instalado hoy)

| Pieza | Qué se usa |
|---|---|
| Lenguaje y framework | PHP 8.3 · Laravel 13.30 |
| Interfaz | Livewire 3.8 (componentes de clase) + Volt 1.11 (pantallas de login y perfil heredadas de Breeze) · Alpine.js |
| Estilos y build | Tailwind 3 (+ `@tailwindcss/forms`) · Vite 8 |
| Autenticación | Laravel Breeze (base de login y recuperación) + bloqueo por intentos, cambio de contraseña obligatorio y cuenta activa, propios |
| Roles y permisos | spatie/laravel-permission 8.3 (roles `supervisor`, `profesor`, `estudiante`) + policies de Laravel |
| Base de datos | SQLite en desarrollo y pruebas · PostgreSQL de Supabase en producción |
| Archivos | Cloudinary (SDK `cloudinary_php` 1.20) en modo **privado**; sin `CLOUDINARY_URL`, disco privado local |
| Colas | Conexión `database` con `queue:work` (sin Horizon ni Redis) |
| API externa | API Keys propias (guardadas como hash, revocables) · sin Sanctum |
| Correo | SMTP (`MAIL_*`) |
| IA | Groq por defecto; Claude (Anthropic) configurable con `IA_PROVEEDOR` |
| PDF | barryvdh/laravel-dompdf 3.1 (boleta de notas) |
| Excel | Lectura y escritura `.xlsx` propias (`app/Services/HojasCalculo`), sin librerías |
| Front | FullCalendar 6 (calendario) · Chart.js 4 (gráficos) · Flatpickr 4.6 desde cdnjs (selector de fecha) |
| Pruebas y estilo | PHPUnit 12 (482 pruebas) · Laravel Pint |
| Hosting | Railway (Nixpacks), configurado en `railway.json` |

## 3. Estado frente al ERS

| Sección del ERS | Resultado |
|---|---|
| Requisitos funcionales (RF) | 106 de 108 completos; RF-08 (2FA) y RF-09 (login con Google) son trabajo futuro según el propio ERS |
| Requisitos no funcionales (RNF) | 19 de 21; RNF-10 (usable sin capacitación) necesita una prueba con usuarios y RNF-13 (99 % de disponibilidad) depende del hosting |
| Matriz de permisos | 17 de 17 acciones correctas |
| Modelo de datos | Cumple (todas las entidades, con claves foráneas) |
| Reglas de negocio | 12 de 12 |
| Casos de uso | 25 de 25, probados de punta a punta |
| Flujos principales | 28 de 28 pasos |
| Arquitectura | Cumple; documentada en `ARQUITECTURA.md` § 0 |
| Fases | Fases 1, 2 y 3 completas; nada de lo que el ERS deja fuera |
| Suite de pruebas | 482 de 482 pasan |

**Conclusión:** cumple el ERS en todo lo que depende del código. Lo pendiente es verificación en producción (§ 5).

## 4. Decisiones clave

- **Contenido por semanas:** un curso dura N semanas (16 por defecto, las define el Supervisor). Cada semana es un
  `Modulo` con su número; hay además un bloque aparte de **Prácticas de campo**. Las unidades antiguas se migraron a la
  semana en que empieza su tramo del ciclo, sin perder contenido.
- **Semanas y sesiones de clase son independientes:** la semana agrupa material; la sesión es un encuentro con fecha y
  asistencia. Solo se cruzan por fecha.
- **Notas:** la nota final se calcula al leer, sin caché, a partir de rubros ponderados que suman 100 %. Las notas de
  tareas y quizzes son de práctica y no se mezclan con la nota final. Mientras falten rubros, el estudiante aparece
  "En curso", con su rendimiento a la fecha.
- **Permisos:** el Supervisor administra y consulta; el Profesor gestiona el contenido y las notas de sus cursos. Cada
  acción se valida en el servidor (rol por ruta y policy por acción).
- **Archivos privados:** la base de datos guarda una referencia `privado://...`, no una URL. Cada apertura pasa por una
  ruta que verifica permisos y redirige a un enlace que vence en 10 minutos.
- **Se mantuvo Cloudinary** (instrucción del cliente) en lugar de Supabase Storage, en modo privado.
- **API propia con API Keys** en vez de Sanctum: el ERS pide una clave por institución cliente, no tokens por usuario.
- **Colas solo para correo:** la bienvenida (cifrada, porque lleva la contraseña temporal) y el aviso de matrícula. El
  resto es síncrono.
- **Zona horaria y formato de fecha** configurables por el Supervisor y aplicados en toda la plataforma.
- **Bitácora de auditoría inmutable** (modelo y trigger en PostgreSQL) para notas y acciones administrativas.
- **El asistente de IA nunca cambia nada por sí solo:** propone recordatorios y el estudiante los acepta o los rechaza.
- **Clases en vivo** mediante el enlace de una videollamada externa (Meet, Zoom, Teams o Jitsi), sin video propio.
- **Sin portal público:** la raíz `/` lleva al login o al panel.
- **Paleta por rol:** azul para el Estudiante y menta/terracota para Profesor y Supervisor; íconos SVG, sin emojis.
- **Sin dependencias nuevas** durante las auditorías (Excel, calendario y selector de fecha se resolvieron con lo
  existente).

## 5. Qué falta antes de producción

1. **Respaldar** la base de Supabase y ejecutar `php artisan migrate`. Todas las migraciones son aditivas; nunca
   ejecutar `migrate:fresh`.
2. **Configurar `CLOUDINARY_URL`** y probar una subida real: entregar un archivo y abrirlo desde la app.
3. **Pasar a privados los archivos antiguos:** `php artisan archivos:privatizar` (simulación) y después `--aplicar`.
4. **Verificar el correo SMTP** (bienvenida y recuperación de contraseña).
5. **Revisar el programador y la cola** tras el primer despliegue (`storage/logs/scheduler.log` y `queue.log`).
6. **Medir tiempos de carga en Railway** (en local dan 0,32–0,38 s por página).
7. **Probar en PostgreSQL** el trigger que impide modificar la bitácora.
8. **Asignar un profesor a cada curso**: el Supervisor ya no carga contenido ni notas.
9. **Cambiar `APP_NAME` a "LearnUp"** en el `.env` de producción.
10. Opcional: prueba corta con usuarios reales (RNF-10) y, si crece el tráfico, cambiar `php artisan serve` por Nginx
    con PHP-FPM, FrankenPHP u Octane.

## 6. Estructura del código

- `app/Livewire/` — pantallas, por módulo:
  - Cuentas: `Auth`, `Forms`, `Usuarios`.
  - Cursos y contenido: `Cursos` (detalle, formulario, matrícula, esquema, libro de calificaciones, boleta), `Lecciones`.
  - Actividades: `Tareas`, `Quizzes`, `Docente/PorCalificar`.
  - Seguimiento: `Asistencia`, `Grupos`, `EventoCalendario`, `Mensajeria`, `Notificaciones`.
  - Paneles y reportes: `Estudiante`, `Reportes`.
  - Supervisor: `Admin` (comunicados, profesores, riesgo de abandono) y `PanelAdmin` (auditoría, configuración,
    integraciones, métricas).
  - Asistente de IA: `BotSugerencias`.
- `app/Services/` — lógica de negocio:
  - Evaluación: `Evaluacion` (cálculo de notas, exportación a Excel) y `Asistencia`.
  - Matrícula y cuentas: `Matricula`, `Usuarios`.
  - Reportes y calendario: `Reportes` (progreso), `Calendario` (colores por curso).
  - Infraestructura: `Multimedia` (archivos privados), `HojasCalculo` (Excel), `Bot` (proveedores de IA y propuestas).
  - `ConfiguracionPlataforma`: nota mínima, zona horaria y formato de fecha.
- `app/Models/` — 26 modelos, entre ellos `Curso`, `Modulo` (semana), `Leccion`, `RecursoLeccion`, `Tarea`, `Entrega`,
  `Quiz`, `Inscripcion`, `NotaRubro` y `BitacoraAuditoria`.
- `app/Policies/` — permisos por acción.
- `app/Http/` — controladores de API, archivos, recursos y boleta PDF; middleware de HTTPS, primer login, cuenta
  activa y API Key.
- `app/Console/Commands/` — comunicados programados, aviso de contenido programado, detección de atrasos y migración
  de archivos.
- `app/Notifications/` — 12 notificaciones (internas y por correo).
- `routes/`:
  - Por área: `web.php`, `cursos.php`, `admin.php`, `reportes.php`, `bot.php`, `auth.php`.
  - `api.php`: la integración externa.
  - `console.php`: el programador de tareas.
- `database/migrations/` (solo aditivas) y `database/seeders/`. `DemoAcademicoSeeder` carga datos de ejemplo
  completos. Todas las cuentas demo usan la contraseña `password123`: `admin@lms.edu.pe` (Supervisor),
  `profesor@lms.edu.pe` y `estudiante@lms.edu.pe`.
- `resources/views/` — vistas Blade de Livewire y componentes compartidos (`components/gestion/*`, `icono`,
  `selector-fecha-hora`).
- `tests/`:
  - `Unit` (cálculos);
  - `Feature/Seguridad` (accesos, IDOR, API, archivos, HTTPS);
  - `Feature/Flujos` (flujos y casos de uso del ERS);
  - pruebas por módulo, de pantallas y de rendimiento.
- `docs/` — auditoría, trazabilidad, cambios, pantallas y progreso.
- `ARQUITECTURA.md` — § 0 con la arquitectura actual; lo demás es la bitácora histórica.
