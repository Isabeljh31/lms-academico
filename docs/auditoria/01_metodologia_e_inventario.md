# 01. Metodología e inventario

Auditoría técnica de LearnUp realizada el 13/10/2026 sobre la rama `feature/auditoria-ers-contenido-por-semanas-y-mejoras-ux` (commit `93fc323` más los cambios sin commitear). Fuente de requisitos: `ERS_LearnUp_v2.docx` (raíz del repositorio, versión 2.0, setiembre 2026).

## 1. Alcance

- Todo el código de `app/`, `routes/`, `database/`, `resources/`, `tests/`, `config/` y `bootstrap/`.
- Archivos de despliegue: `railway.json`, `Procfile`, `docker-compose.yml`, `composer.json`, `package.json`, `vite.config.js`, `tailwind.config.js`.
- Documentación del repositorio (`docs/`, `ARQUITECTURA.md`, `README.md`, `INFORME_FINAL.md`, `AGENTS.md`, `CLAUDE.md`), tratada como afirmaciones a verificar.
- Historial de git (solo lectura).

## 2. Reglas seguidas

- Solo lectura. El único directorio escrito en el repositorio es `docs/auditoria/`.
- No se ejecutaron `migrate`, `db:seed` ni comandos que escriban en la base de datos.
- No se usaron `git add`, `commit`, `push`, `checkout`, `reset` ni `stash`.
- No se imprimieron valores de `.env` ni de otros secretos. Del `.env` solo se leyeron `DB_CONNECTION`, `DB_DATABASE`, `APP_ENV`, `APP_DEBUG`, `APP_NAME`, `QUEUE_CONNECTION`, `IA_PROVEEDOR`, `FILESYSTEM_DISK` y `MAIL_MAILER`, más los nombres de las demás variables, sin sus valores.
- Antes de ejecutar las pruebas se verificó el aislamiento de la base de datos (ver § 4).
- Los cálculos intermedios (extracción del ERS, volcados de código, inventarios, resultado JUnit) se guardaron en el scratchpad de la sesión, fuera del repositorio.

## 3. Herramientas usadas

| Herramienta | Uso |
|---|---|
| Lectura directa de archivos | Todo el código de `app/`, `routes/`, `database/migrations`, `config/` relevante y las vistas clave |
| Scripts PHP en el scratchpad | Inventario con conteo de líneas; extracción del texto del ERS desde el `.docx`; tabla de rutas; resumen JUnit; resumen de auditorías de dependencias |
| `git log`, `git status`, `git diff --stat`, `git ls-files`, `git check-ignore`, `git log -G` | Historial, cambios sin commitear, archivos versionados y búsqueda de secretos en el historial |
| `php artisan route:list --json`, `php artisan migrate:status` | Rutas reales y estado de las migraciones en la base local (lectura) |
| `vendor/bin/phpunit` | Suite completa con reporte JUnit |
| Pruebas temporales en el scratchpad | 5 pruebas que confirman defectos encontrados por lectura (archivo `VerificacionAuditoriaTest.php`, fuera del repositorio) |
| Herramienta `database-query` de Laravel Boost | Consultas `SELECT` sobre la base SQLite local |
| `composer show`, `composer audit`, `npm ls`, `npm audit` | Versiones instaladas y vulnerabilidades conocidas |

## 4. Aislamiento de las pruebas (verificado antes de ejecutarlas)

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Motor de pruebas | SQLite en memoria | `phpunit.xml:26-28` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vacío) |
| Archivo `.env.testing` | No existe | `Test-Path .env.testing` = False |
| Configuración en caché que anule `phpunit.xml` | No existe | `Test-Path bootstrap/cache/config.php` = False |
| Base de desarrollo | SQLite local (`database/database.sqlite`), no Supabase | `.env`: `DB_CONNECTION=sqlite` |
| Cola, correo y caché en pruebas | `sync`, `array`, `array` | `phpunit.xml:25,29,30` |

Conclusión: las pruebas no pueden tocar la base de desarrollo ni la de Supabase. Se ejecutaron.

## 5. Exclusiones

| Carpeta | Motivo | Existencia y tamaño |
|---|---|---|
| `vendor/` | Dependencias de terceros | 10.740 archivos, 82,8 MB |
| `node_modules/` | Dependencias de terceros | 5.888 archivos, 63,5 MB |
| `storage/` (logs, framework, app) | Datos generados en tiempo de ejecución | 412 archivos, 8,1 MB |
| `public/build/` | Salida compilada de Vite | 3 archivos, 0,54 MB |
| `.git/` | Metadatos de git; se consultó con comandos de lectura | — |
| `DISEñO FIGMA/` | Prototipo de diseño (React/Vite), no es código de la app; se inventarió | 27 archivos, 0,38 MB (ver archivo 16) |
| `.claude/` | Skills de agentes; solo se verificó si contiene secretos (no contiene) | 37 archivos, 0,15 MB |

## 6. Inventario cuantitativo

### 6.1 Archivos y líneas por carpeta

| Carpeta | Archivos | Líneas |
|---|---|---|
| `app/` (total) | 124 | 14.338 |
| `app/Models` (incluye `Concerns/`) | 27 | 2.629 |
| `app/Livewire` | 41 | 7.028 |
| `app/Services` | 17 | 2.931 |
| `app/Policies` | 7 | 317 |
| `app/Http` (controladores y middleware) | 10 | 436 |
| `app/Console/Commands` | 4 | 324 |
| `app/Notifications` | 12 | 434 |
| `app/Mail` | 1 | 50 |
| `app/Providers` | 2 | 136 |
| `app/View/Components` | 2 | 34 |
| `app/Enums` | 1 | 19 |
| `bootstrap/` | 5 | 451 |
| `config/` | 12 | 1.595 |
| `database/migrations` | 46 | 1.780 |
| `database/seeders` | 5 | 1.155 |
| `database/factories` | 8 | 299 |
| `lang/` | 1 | 9 |
| `resources/views` | 83 | 7.969 |
| `resources/js` | 1 | 42 |
| `resources/css` | 1 | 166 |
| `routes/` | 8 | 289 |
| `tests/` (total) | 56 | 7.317 |
| `tests/Feature` | 49 | 6.912 |
| `tests/Unit` | 5 | 319 |
| `docs/` (antes de esta auditoría) | 6 | 1.984 |

Carpetas de `app/` que no existen: `Jobs`, `Observers`, `Rules`, `Exceptions`, `Actions`, `Support`, `Traits`.

### 6.2 Conteos por tipo de artefacto

| Artefacto | Cantidad | Detalle |
|---|---|---|
| Modelos Eloquent | 26 | más 1 trait (`Models/Concerns/TieneEstadoPublicacion`) |
| Componentes Livewire de clase | 39 | más `Livewire/Actions/Logout` (acción) y `Livewire/Forms/LoginForm` (form object) |
| Componentes Volt (archivo único) | 7 | `pages/auth/{login, forgot-password, reset-password, verify-email, confirm-password}`, `profile/{update-password-form, update-profile-information-form}` |
| Clases de servicio | 15 | más 1 interfaz (`Bot/Contracts/ProveedorIA`) y 1 trait (`Bot/Concerns/ConstruyeContextoIA`) |
| Policies | 7 | Curso, Entrega, Grupo, PropuestaBot, Quiz, Tarea, Usuario |
| Middleware propios | 4 | CuentaActiva, ForzarHttps, PrimerLogin, ValidarApiKey |
| Controladores | 6 | Api/Matricula, Archivo, Auth/VerifyEmail, RecursoLeccion, Reportes/BoletaPdf, Controller base |
| Comandos artisan propios | 4 | `bot:detectar-atrasos`, `contenido:notificar-programado`, `archivos:privatizar`, `comunicados:publicar` |
| Notificaciones | 12 | 1 por correo y base de datos (`MatriculadoEnCurso`); 11 solo base de datos |
| Mailables | 1 | `BienvenidaMail` |
| Jobs propios | 0 | Las colas solo transportan el correo y la notificación anteriores |
| Migraciones | 46 | 8 lotes en la base local |
| Tablas en la base | 42 | más la tabla `migrations` de Laravel; ver archivo 04 |
| Rutas registradas | 64 | 41 propias de la aplicación más rutas de Livewire, Boost, almacenamiento y `/up` (archivo 06) |
| Vistas Blade | 83 | incluye 7 Volt y 2 parciales |
| Componentes Blade anónimos | 25 | `components/` y `components/gestion/` |
| Archivos de prueba | 54 | 49 Feature y 5 Unit, más `TestCase` y el trait `Concerns/PreparaEscenarioAcademico` |
| Casos de prueba ejecutados | 482 | 1.468 aserciones (archivo 11) |
| Factories | 8 | Curso, Entrega, Inscripcion, Modulo, Quiz, ResultadoQuiz, Tarea, User |
| Seeders | 5 | Database, RoleAndUser, Curso, DemoAcademico, PropuestaBot |

### 6.3 Archivos en la raíz del repositorio

`.editorconfig`, `.env` (ignorado por git), `.env.example`, `.gitattributes`, `.gitignore`, `.mcp.json` (versionado, sin secretos), `.npmrc`, `.phpunit.result.cache` (ignorado), `AGENTS.md`, `ARQUITECTURA.md` (95 KB), `artisan`, `boost.json`, `CLAUDE.md`, `composer.json`, `composer.lock`, `docker-compose.yml`, `ERS_LearnUp_v2.docx`, `ERS_LearnUp_v2.docxZone.Identifier` (metadato de Windows versionado por error), `INFORME_FINAL.md`, `package.json`, `package-lock.json`, `phpunit.xml`, `postcss.config.js`, `Procfile`, `railway.json`, `README.md`, `tailwind.config.js`, `vite.config.js`.

## 7. Cobertura de la auditoría

Cada archivo de `app/`, `routes/`, `database/`, `resources/`, `config/`, `tests/` y `docs/` está cubierto por algún archivo de la auditoría. La verificación final (17 § 7) encontró 294 de 352 archivos citados por nombre; los 58 restantes (migraciones, factories, componentes de Breeze y vistas de componentes) están cubiertos por grupo y se listan en 17 § 7. La tabla dice dónde.

| Carpeta | Archivo de la auditoría |
|---|---|
| `app/Models/*` (26 + trait) | 05 |
| `app/Livewire/*` (41) | 07a y 07b |
| `app/Services/*` (17) | 08 |
| `app/Http/*`, `app/Console/*`, `app/Notifications/*`, `app/Mail/*`, `app/Providers/*`, `app/View/*`, `app/Enums/*` | 09, y 06 para middleware y policies |
| `app/Policies/*` | 06 |
| `routes/*` (8) | 06; el programador (`routes/console.php`) en 09 |
| `database/migrations/*` (46) | 04 |
| `database/seeders/*`, `database/factories/*` | 15 (seeders) y 11 (factories) |
| `config/*` (12) | 13 (`session`, `auth`, `archivos`), 15 (`database`, `filesystems`, `queue`, `mail`, `services`, `app`), 14 (`logging`, `cache`, `permission`) |
| `bootstrap/*` | 03 y 13 |
| `resources/views/*`, `resources/js`, `resources/css` | 10 |
| `tests/*` (54) | 11 (y 12a, 12b como evidencia) |
| `docs/*` y documentos de la raíz | 16 |
| `lang/es.json` | 10 |

Archivos no cubiertos línea por línea:
- Las 83 vistas se revisaron por patrones (salida sin escapar, confirmaciones, accesibilidad, emojis, CDN), pero solo una parte se leyó completa. Se leyeron completas: `layouts/app`, `dashboard`, `components/selector-fecha-hora`, `pages/auth/login`, `pages/auth/reset-password`, `welcome/navigation`. Del resto se leyeron fragmentos.
- `config/permission.php`, `config/cache.php` y `config/logging.php` se revisaron solo en sus claves relevantes.
- `bootstrap/cache/packages.php` y `services.php` son archivos generados y no se revisaron.
