# 03. Arquitectura

## 1. Capas reales (hechos verificados en el código)

| Capa | Qué contiene | Dónde |
|---|---|---|
| Entrada HTTP | Rutas web (sesión), una ruta API (`POST /api/v1/matriculas`), `/up` y rutas de Livewire y del disco local | `routes/*.php`, `bootstrap/app.php:15-20` |
| Middleware global | `TrustProxies` (solo Proto/Host/Port) y `ForzarHttps` | `bootstrap/app.php:24-25` |
| Middleware del grupo web | `CuentaActiva` y `PrimerLogin`, además de los de Laravel (sesión, CSRF) | `bootstrap/app.php:33-35` |
| Middleware por ruta | `auth`, `verified`, `role:<rol>` (Spatie), `throttle:api-integracion`, `ValidarApiKey` | `routes/*.php` |
| Presentación y control | 39 componentes Livewire de clase y 7 Volt. En Livewire, el componente es a la vez controlador y estado de pantalla. | `app/Livewire`, `resources/views/livewire` |
| Controladores clásicos | 5: API de matrícula, archivos privados, recursos de lección, boleta PDF y verificación de correo | `app/Http/Controllers` |
| Autorización | 7 policies registradas a mano en `AppServiceProvider::boot` (líneas 56–62), además de `abort_unless(hasRole(...))` en `mount()` de algunos componentes | `app/Policies` |
| Lógica de negocio | 15 servicios (cálculo de notas, matrícula, cuentas, archivos, reportes, IA, Excel, configuración) | `app/Services` |
| Dominio y datos | 26 modelos Eloquent con métodos de negocio (clonar, recalcular quiz, estado de tarea, visibilidad) | `app/Models` |
| Persistencia | SQLite en local y en pruebas; PostgreSQL (Supabase) en producción según la documentación | `config/database.php` |
| Procesos en segundo plano | Programador (3 comandos) y cola `database` (2 mensajes en cola) | `routes/console.php`, `railway.json` |
| Servicios externos | SMTP; Cloudinary SDK 1.x; Groq o Anthropic por HTTP; cdnjs (Flatpickr), fonts.bunny.net y ui-avatars.com desde el navegador | ver § 4 |

Observación (opinión): la lógica no está repartida de forma uniforme. Hay cálculos importantes dentro de componentes Livewire, por ejemplo:
- la nota de rubro y la auditoría en `LibroCalificaciones`;
- la entrega grupal en `Tareas/Detalle`;
- el riesgo de abandono en `Admin/Metricas/Index`;
- la clonación en el modelo `Curso`.

Calificar una tarea está implementado **tres veces** con el mismo patrón (actualizar la nota, auditar y notificar): `Tareas/Detalle::calificar` (líneas 181–222), `Docente/PorCalificar/Index::guardarCalificacion` (63–106) y `Cursos/LibroCalificaciones::guardarNotaTarea` (170–213). Ver archivo 14.

## 2. Flujo de una petición típica

Ejemplo: el profesor registra una nota de rubro.

1. El navegador hace `GET /cursos/{curso}/libro-calificaciones`.
   - Middleware global: `TrustProxies` y `ForzarHttps` (en producción redirige a HTTPS y agrega HSTS).
   - Grupo `web`: sesión y CSRF, `CuentaActiva` (cierra la sesión si `activo = false`) y `PrimerLogin` (redirige a `/primer-login` si falta cambiar la contraseña).
   - Ruta: `auth`, `verified` y `role:profesor|supervisor` (`routes/cursos.php:56-58`).
2. Livewire monta `LibroCalificaciones` y `mount()` ejecuta `Gate::authorize('supervisar', $curso)` (`CursoPolicy::supervisar`).
3. `render()` calcula las filas con `CalculadoraNotas` (servicio) y lee `notas_rubro`.
4. El clic en una celda envía `POST /livewire/update` (mismo grupo `web`; `PrimerLogin` deja pasar las rutas `livewire/*`). Se ejecutan `abrirModalNota()` y luego `guardarNotaRubro()`:
   - `autorizarCalificacion()`: la policy `calificar` (solo el profesor del curso), el curso no archivado y un esquema que sume 100 %;
   - validación 0–20 con un decimal; motivo obligatorio si se edita;
   - `NotaRubro::updateOrCreate`, `BitacoraAuditoria::registrarCambioNota` y la notificación `NotaRubroRegistrada` (base de datos, síncrona).
5. La nota final no se guarda: se recalcula en cada lectura (RNF-12).

Flujo de la API: `POST /api/v1/matriculas`.
1. `throttle:api-integracion`: 60 por minuto por IP.
2. `ValidarApiKey`: busca por prefijo, verifica el hash, registra el log y responde 401 si la clave no es válida.
3. `MatriculaController::sincronizar`: exige JSON (si no, 415), valida, busca el curso por id o código y, dentro de una transacción, crea la cuenta si no existe (`GestorCuentas`) y sincroniza la matrícula (`GestorMatriculas`).
4. `ValidarApiKey` actualiza el código de estado en el log.

## 3. Cómo se conectan las piezas

| Desde | Hacia | Mecanismo |
|---|---|---|
| Componentes Livewire | Policies | `Gate::authorize` y `Gate::allows`, en `mount()` y en cada acción. Las propiedades con `#[Locked]` evitan que el navegador cambie ids sensibles. |
| Componentes Livewire | Servicios | Inyección de dependencias en los métodos (`guardar(SubidorArchivos $s)`, `render(CalculadoraNotas $c)`) o `app(...)` |
| Servicios | Modelos | Eloquent; consultas por lote en las calculadoras |
| Modelos | Bitácora | Métodos estáticos `BitacoraAuditoria::registrarCambioNota` y `registrarAccionAdmin`, llamados desde componentes y servicios. No hay observers. |
| Componentes y comandos | Notificaciones | `$usuario->notify()` o `Notification::send()`. 11 de 12 son solo de base de datos y síncronas; `MatriculadoEnCurso` se envía por correo y base de datos, en cola. |
| Servicio de IA | Proveedor | La interfaz `ProveedorIA`, enlazada en `AppServiceProvider::register` (líneas 41–45) según `config('services.ia.proveedor')` |
| Configuración | Todo | El singleton `ConfiguracionPlataforma` (`AppServiceProvider:48`) y las macros de Carbon `formatoFecha`, `formatoFechaHora` y `formatoDiaMes` (líneas 93–106) |

## 4. Colas, programador, almacenamiento, correo e IA

| Pieza | Implementación real | Evidencia |
|---|---|---|
| Cola | Conexión `database`, `after_commit=false`, `retry_after=90`. Encolados: `BienvenidaMail` (`ShouldQueue` y `ShouldBeEncrypted`) y `MatriculadoEnCurso` (`ShouldQueue`). | `config/queue.php:16,43-44`; `app/Mail/BienvenidaMail.php:23`; `app/Notifications/MatriculadoEnCurso.php:16` |
| Worker | `queue:work --tries=3 --max-time=3600` en un bucle de shell dentro del mismo contenedor web | `railway.json:8` |
| Programador | `bot:detectar-atrasos` a diario a las 06:00; `comunicados:publicar` y `contenido:notificar-programado` cada 5 minutos | `routes/console.php:13-19` |
| Almacenamiento | `SubidorArchivos`: Cloudinary `type=private` si existe `CLOUDINARY_URL`; si no, el disco `local` (`storage/app/private`) con `serve=true` y URLs firmadas. La base guarda `privado://...`. | `app/Services/Multimedia/SubidorArchivos.php:101-128,145-166`; `config/filesystems.php:33-39` |
| Correo | SMTP (`MAIL_MAILER=smtp` en `.env` local). Se usa en la bienvenida, el aviso de matrícula y la recuperación de contraseña de Breeze. | `.env` (solo el nombre del driver); `config/mail.php` |
| IA | `GroqProveedor` (por defecto, modelo `openai/gpt-oss-120b`) o `ClaudeBotService` (modelo `claude-sonnet-4-5`). HTTP con timeout de 20 s y `max_tokens` 512. Sin clave, modo simulado. | `config/services.php:51-68`; `app/Services/Bot/*.php` |
| PDF | `barryvdh/laravel-dompdf`, boleta en `reportes/boleta-pdf.blade.php` | `app/Http/Controllers/Reportes/BoletaPdfController.php` |
| Excel | Lector y escritor `.xlsx` propios sobre `ZipArchive` y `SimpleXML` | `app/Services/HojasCalculo/*` |
| Activos de front | Vite (FullCalendar y Chart.js en `window`); Flatpickr se carga en tiempo de ejecución desde cdnjs **sin SRI**; fuentes de fonts.bunny.net; avatares de reemplazo de ui-avatars.com (envía el nombre del usuario a un tercero) | `resources/js/app.js:18-42`; `resources/views/layouts/app.blade.php:66-67,130` |

## 5. Comparación con ARQUITECTURA.md § 0

| Afirmación de § 0 | Verificación | Diferencia |
|---|---|---|
| § 0.1: "PHPUnit 12.5, 475 pruebas" | La ejecución dio 482 casos | **Desactualizado**: son 482 |
| § 0.1: versiones de Laravel 13.30, Livewire 3.8, Volt 1.11, Spatie 8.3, dompdf 3.1, Cloudinary 1.20 | `composer show --direct`: 13.30.1, 3.8.7, 1.11.2, 8.3.0, 3.1.2, 1.20.2 | Coincide |
| § 0.2: diagrama de capas | Coincide con § 1 de este archivo | Coincide. El diagrama está en bloque de texto, lo que convierte bien a Word. |
| § 0.3: 19 módulos ubicados en el código | Las 19 entradas existen y los archivos citados existen | Coincide |
| § 0.4: "límite de solicitudes por IP" que no se puede falsear | Cierto que no se puede falsear, pero al no confiar en `X-Forwarded-For` detrás del proxy de Railway, `$request->ip()` devuelve la IP del proxy. **Todas** las instituciones comparten un único cupo de 60/min, y las IP que guardan la bitácora y el log de la API serían las del proxy. | **Omisión**. Por análisis; no verificado en Railway. Ver H-08. |
| § 0.4: bloqueo de 15 min tras 3 intentos | `config/auth.php` (`bloqueo_login`: 3 y 15); `User::registrarIntentoFallido` | Coincide. No hay límite por IP en el login (ver archivo 13). |
| § 0.5: procesos en `railway.json` y en `Procfile` | Ambos los definen | Coincide, pero si alguien despliega con el `Procfile` y además con `railway.json`, el programador correría dos veces (opinión) |
| § 0.6: no hay APM | Confirmado | Coincide |
| § 0.7: `php artisan serve` con 4 workers | `railway.json:8` | Coincide. Es el servidor de desarrollo de PHP en producción (riesgo, archivo 15). |
| § 0.8: "toda subida a Cloudinary fallaba (SDK 2.x)" y corregido | `SubidorArchivos` usa `\Cloudinary` y `Cloudinary\Uploader` (API 1.x) | Corregido en código, **sin commitear** y sin prueba contra una cuenta real |
| `config/services.php:38-40`: el comentario dice que sin `CLOUDINARY_URL` se usa el disco "public" | El código usa el disco privado `local` (`SubidorArchivos.php:181-186`) | **Comentario desactualizado** en la configuración |
| `ConstruyeContextoIA` y `GroqProveedor`: comentario "Llama 3.3 70B" | El modelo configurado por defecto es `openai/gpt-oss-120b` (`config/services.php:67`) | Comentario desactualizado (`GroqProveedor.php:16-18`) |
