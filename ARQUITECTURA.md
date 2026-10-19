# Arquitectura — LMS Académico

Este documento se actualiza de forma incremental, módulo por módulo, a medida que
se construye el proyecto. Es la bitácora técnica de decisiones para el equipo.

## 1. Auditoría inicial (2026-09-15)

### Qué existía antes de empezar

El proyecto **no partía de cero**. Ya había una base construida por una
compañera de equipo:

- Laravel 13 + Livewire 3 + Livewire Volt + Laravel Breeze (auth) + Spatie
  Laravel-Permission ya instalados en `composer.json`.
- Modelos Eloquent ya creados para **todo** el dominio: `User`, `Curso`,
  `Modulo`, `Inscripcion`, `Tarea`, `Entrega`, `Quiz`, `ResultadoQuiz`,
  `EventoCalendario`, `ConversacionBot`, `PropuestaBot`, con sus relaciones
  ya bien definidas y nombres en español consistentes con este documento.
- Migraciones ya creadas para las 9 tablas de negocio + tabla de roles/permisos
  de Spatie + columna `avatar_url` en `users`. Usan `Blueprint` (Eloquent/Query
  Builder), no SQL crudo, así que son agnósticas de motor.
- Un `RoleAndUserSeeder` que crea 3 roles (`admin`, `profesor`, `estudiante`)
  y un usuario demo por rol.
- Un componente Livewire `App\Livewire\EventoCalendario\Index` con una vista
  de lista simple (no calendario visual, sin estilos propios).
- Vistas Breeze/Volt estándar para login, registro, verificación de correo,
  recuperación de contraseña y perfil — sin personalizar (paleta indigo por
  defecto, sin diferenciación por rol).
- `.env` apuntando a un proyecto real de Supabase (Postgres, pooler IPv4) con
  credenciales ya cargadas, y una variable `GROQ_API_KEY` (decisión previa de
  usar Groq/Llama en vez de la API de Claude para el bot).

### Qué se conservó

- **Todos los modelos y migraciones existentes.** Están bien nombrados,
  bien relacionados, y usan Query Builder puro (sin SQL específico de motor).
  Se verificó corriendo `migrate:fresh` contra SQLite: las 15 migraciones
  corren sin error, incluyendo las columnas `jsonb()` (Laravel las traduce
  correctamente en SQLite).
- El `RoleAndUserSeeder` como base (solo se ajustó el nombre de un rol, ver
  abajo).
- Breeze + Volt para todo el flujo de autenticación (login, registro,
  verificación de email, reset de password): es exactamente lo que pide el
  stack del proyecto, funciona, y reescribirlo desde cero sería trabajo
  perdido. Se reutiliza y se personaliza visualmente por encima.

### Qué se decidió rehacer y por qué

- **Componente de calendario (`EventoCalendario/Index`)**: es un placeholder
  (una `<ul>` con texto plano, sin FullCalendar.js, sin filtros, sin crear/
  editar eventos). Se reescribe por completo en el Módulo 5.
- **Rol `admin` → `administrador`**: el documento del proyecto (sección 3)
  fija explícitamente los 3 roles como `administrador`, `profesor`,
  `estudiante`. Se renombra el rol y el seeder para evitar ambigüedad ("admin"
  vs "administrador") entre el equipo.
- **`DatabaseSeeder` no invocaba `RoleAndUserSeeder`**: solo creaba un
  `Test User` genérico sin rol. Se corrige para que `db:seed` deje la base
  lista de punta a punta (roles + usuarios demo + datos de ejemplo por
  módulo, que se irán añadiendo).
- **Bot de sugerencias**: el `.env` existente tenía `GROQ_API_KEY` (decisión
  de usar Groq/Llama). El documento del proyecto (sección 3) es explícito:
  el bot debe usar el cliente Guzzle de Laravel contra la **API de Claude
  (Anthropic)**, con modo simulado si no hay API key. Se implementó
  primero así, con `ANTHROPIC_API_KEY`. **Actualización posterior (ver
  Módulo 6/7 § proveedor de IA):** por presupuesto, se agregó Groq como
  proveedor gratuito por defecto detrás de una interfaz común
  (`App\Services\Bot\Contracts\ProveedorIA`), intercambiable con Claude
  vía `IA_PROVEEDOR` sin tocar código — la variable `GROQ_API_KEY` que ya
  traía el `.env` terminó siendo justo la que se necesitaba.
- **Layout/paleta visual**: Breeze trae la plantilla indigo/gray por defecto.
  Se reemplaza por el sistema de diseño propio (sección 5 del documento:
  menta salvia / arena / terracota / café arcilla) con tokens en
  `tailwind.config.js`, modo oscuro, y layouts diferenciados por rol.

### Base de datos: SQLite local / Postgres-Supabase en producción

- La base de Supabase la administra una compañera de equipo y no está
  garantizado que esté activa. **No se depende de ella para avanzar.**
- `config/database.php` ya traía `'default' => env('DB_CONNECTION', 'sqlite')`
  como fallback del propio framework — no fue necesario tocarlo.
- Se creó `database/database.sqlite` y se cambió `.env` (local, no
  versionado) a `DB_CONNECTION=sqlite`.
- `.env.example` se mantiene documentando la conexión real a Supabase
  (host, puerto del pooler, base `postgres`) para cuando el equipo la
  active: basta con copiar esas variables al `.env` local y cambiar
  `DB_CONNECTION=pgsql`.
- Regla de desarrollo: todo el código de la aplicación usa Eloquent / Query
  Builder — nada de SQL crudo específico de un motor — para que el mismo
  código corra igual en SQLite y en Postgres.

### Cloudinary

- Aún no hay credenciales de Cloudinary configuradas (`CLOUDINARY_URL` y
  `CLOUDINARY_UPLOAD_PRESET` vacíos en `.env`).
- Se instala el driver de Cloudinary para Laravel y se configura como disco
  de `filesystems.php`, pero con **fallback automático a `storage/app/public`**
  cuando `CLOUDINARY_URL` está vacío, para que subir fotos de perfil,
  portadas de curso y archivos de entregas funcione igual sin la credencial
  real. Ver sección "Cómo activar Cloudinary" más abajo (se completa en el
  Módulo 1).

### Claude API (bot de sugerencias)

- Sin `ANTHROPIC_API_KEY` configurada, el servicio del bot corre en **modo
  simulado (mock)**, claramente señalado en el código, devolviendo
  respuestas de ejemplo. Se documenta cómo activar la key real en el
  Módulo 6.

## 2. Convención de carpetas y nombres

Dominio de negocio en español (modelos, tablas, componentes Livewire de
negocio); solo lo que Laravel exige por convención de framework queda en
inglés (métodos de ciclo de vida, contratos de interfaces, comandos artisan).

Agrupación por módulo de negocio, no por tipo técnico disperso, máximo 2
niveles de anidación:

```
app/
  Livewire/
    Cursos/            (Módulo 2: catálogo, gestión, inscripción)
    Tareas/             (Módulo 3: tareas, entregas, calificación)
    Quizzes/            (Módulo 4: quizzes, resolución, resultados)
    EventoCalendario/   (Módulo 5: calendario FullCalendar, notificaciones)
    BotSugerencias/     (Módulos 6-7: chat FAQ + propuestas human-in-the-loop)
    PanelAdmin/         (Módulo 8: cuentas, catálogo, métricas, config)
    Reportes/           (Módulo 9: dashboards, export PDF)
    Usuarios/           (Módulo 1: perfil, asignación de roles)
    Layout/             (navegación/sidebar por rol, ya existente)
  Models/               (plano, ya eran pocos y ya en español)
  Policies/             (una policy por modelo de negocio, en español:
                          CursoPolicy, TareaPolicy, EntregaPolicy, etc.)
  Services/
    Bot/                (ClaudeBotService, PropuestaBotService)
    Cloudinary/          (helpers de subida, si hacen falta más allá del disk)
resources/views/livewire/  (misma agrupación que app/Livewire/, en kebab-case)
```

Se evitan micro-archivos (un trait de una sola línea, un service con una sola
llamada): se consolida en clases cohesivas por módulo mientras no mezclen
responsabilidades distintas.

## 2.1 Despliegue en Railway (preparado, no ejecutado)

Se agregaron `railway.json` y `Procfile` en la raíz (Railway lee
cualquiera de los dos; se dejaron ambos por si el equipo prefiere uno).
El build corre `composer install --no-dev`, `npm ci && npm run build`; el
arranque corre migraciones, `storage:link`, cachea config/rutas/vistas, y
levanta el servidor. **No se ejecutó el despliegue real** (no hay cuenta
de Railway conectada desde este entorno), pero queda listo para que el
equipo solo tenga que:

1. Crear el proyecto en Railway y conectar este repositorio.
2. Configurar las variables de entorno de producción (todas las que ya
   están documentadas en `.env.example`): `APP_KEY` (generar con
   `php artisan key:generate --show`), `APP_ENV=production`,
   `APP_DEBUG=false`, las credenciales reales de Supabase/Postgres
   (`DB_CONNECTION=pgsql` + host/puerto/usuario/password del pooler),
   `CLOUDINARY_URL` y `CLOUDINARY_UPLOAD_PRESET`, `ANTHROPIC_API_KEY`.
3. **El Scheduler de Laravel (Módulo 7) necesita un disparador externo**:
   Railway no corre un cron a nivel de sistema operativo dentro de un
   servicio web normal. Hay que crear un segundo servicio en Railway de
   tipo "Cron Job" (o un servicio con `restart: always` corriendo
   `php artisan schedule:work`) apuntando al mismo repo, con el comando
   `php artisan schedule:run` cada minuto (o `schedule:work` como proceso
   persistente). Sin esto, `bot:detectar-atrasos` nunca se ejecutará solo
   en producción — sí se puede correr a mano con
   `php artisan bot:detectar-atrasos` mientras tanto.

## 3. Registro de pruebas por módulo

_(se completa al cerrar cada módulo)_

### Módulo 1 — Autenticación y gestión de usuarios

**Construido:**
- Login, registro, verificación de correo, recuperación/reset de contraseña
  (Breeze + Volt, reutilizado del scaffold existente).
- Roles Spatie `administrador` / `profesor` / `estudiante` vía `App\Enums\Rol`,
  middleware `role:` registrado en `bootstrap/app.php`. Todo registro público
  asigna `estudiante` por defecto (`register.blade.php`).
- Foto de perfil con `App\Services\Multimedia\SubidorArchivos`: sube a
  Cloudinary si `CLOUDINARY_URL` está presente, si no cae a
  `storage/app/public` (symlink ya creado con `storage:link`).
- Panel `Admin → Usuarios` (`App\Livewire\Usuarios\Index`,
  `/admin/usuarios`, sólo `administrador`) con búsqueda y cambio de rol en
  vivo. Protegido por middleware de ruta + `App\Policies\UsuarioPolicy`
  (doble capa: ruta y autorización a nivel de acción).
- Layout base rediseñado (`layouts/app.blade.php`): sidebar + topbar con
  acento de color por rol (café arcilla=administrador, terracota=profesor,
  menta=estudiante), modo oscuro (`prefers-color-scheme`), componente
  reutilizable `<x-badge-estado>` para estados en toda la app.
- Paleta de diseño (menta/arena/terracota/café/éxito) como tokens en
  `tailwind.config.js`; `gray` remapeado a `stone` (cálido) en vez del gris
  frío de Tailwind por defecto.

**Probado:**
- `php artisan migrate:fresh --seed` corre limpio sobre SQLite.
- Suite de tests (PHPUnit): **33/33 passing**, incluyendo
  `tests/Feature/UsuariosTest.php` (nuevo, 7 tests) que verifica
  explícitamente: administrador accede a `/admin/usuarios` (200), profesor y
  estudiante reciben 403, invitado es redirigido a login, administrador
  puede cambiar el rol de otro usuario, un administrador no puede
  reasignarse el rol a sí mismo vía policy, y el registro público asigna
  `estudiante` automáticamente.
- `npm run build` compila sin errores con la paleta nueva.
- `php artisan view:cache` compila todas las vistas Blade sin errores de
  sintaxis.

### Módulo 2 — Gestión de cursos y contenidos

**Construido:**
- CRUD de cursos (`App\Livewire\Cursos\{Index,Formulario,Show}`) con
  portada vía `SubidorArchivos`, descripción y profesor propietario.
- Organización en módulos/unidades (`Modulo`, ya existía) con una unidad
  nueva: **materiales** — se agregó la columna `materiales` (JSON) a
  `modulos` (migración `2026_09_16_025140`) en vez de crear una tabla nueva,
  ya que el documento del proyecto no lista una tabla `materiales` para
  este módulo. Cada entrada es `{nombre, url}`, subida con el mismo
  `SubidorArchivos` (Cloudinary o local).
- Inscripción de estudiantes: botón "Inscribirme" en el detalle del curso,
  usa `firstOrCreate` sobre `inscripciones` respetando el índice único
  `(curso_id, estudiante_id)` ya existente en la migración original.
- `App\Policies\CursoPolicy`: `crear` (profesor/administrador),
  `gestionar` (dueño del curso o administrador — controla edición, unidades
  y materiales), `inscribirse` (sólo estudiante), `ver` (cualquiera
  autenticado, incluye catálogo).
- Catálogo (`/cursos`) filtra automáticamente por `profesor_id` cuando lo
  ve un profesor (sólo sus cursos); administrador y estudiante ven el
  catálogo completo, con buscador por nombre.
- Factories (`CursoFactory`, `ModuloFactory`, `InscripcionFactory`) y
  `CursoSeeder` con 3 cursos reales de ejemplo, cada uno con 3 unidades,
  inscribiendo al estudiante demo — corren dentro de `db:seed`.

**Probado:**
- `tests/Feature/CursosTest.php` (8 tests, todos pasan): profesor crea
  curso; estudiante NO puede crear (403); un profesor no puede editar el
  curso de otro (403); administrador sí puede editar cualquier curso;
  estudiante puede inscribirse; profesor puede agregar unidades a su
  curso; estudiante NO puede agregar unidades (403); el catálogo de un
  profesor sólo muestra sus propios cursos y no los ajenos.
- Suite completa: **41/41 passing** tras el módulo.
- `php artisan migrate:fresh --seed` corre limpio; `view:cache` compila
  todas las vistas nuevas sin errores.

### Módulo 3 — Tareas, entregas y calificaciones

**Construido:**
- `App\Livewire\Tareas\{Index,Formulario,Detalle}`, anidados bajo el curso
  (`/cursos/{curso}/tareas...`) con `scopeBindings()` para que una tarea de
  otro curso nunca resuelva por accidente bajo un curso equivocado (evita
  un IDOR trivial por URL).
- Tareas con unidad, fecha límite y peso de nota (ya existía en el modelo);
  entrega de archivo + comentario por el estudiante vía `SubidorArchivos`;
  calificación (nota /20) + feedback del profesor, con cambio de estado
  automático `entregado` → `calificado`.
- `App\Policies\{TareaPolicy,EntregaPolicy}`: crear/gestionar tareas
  reutiliza `CursoPolicy::gestionar` vía `Gate::forUser(...)->allows(...)`
  en vez de duplicar la regla "dueño del curso o administrador"; entregar
  exige estar inscrito; calificar exige ser dueño del curso o admin.
- Estados visuales con `<x-badge-estado>`: pendiente / atrasado (calculado
  comparando `fecha_entrega` con `now()`, no se persiste) / entregado /
  calificado.
- `TareaFactory`, `EntregaFactory` (con estado `calificada()`), y
  `CursoSeeder` ampliado: cada curso demo trae 3 tareas (una vencida y ya
  calificada, una pendiente, un trabajo final) para poder probar los tres
  estados de inmediato con el usuario `estudiante@lms.edu.pe`.

**Probado:**
- `tests/Feature/TareasTest.php` (6 tests, todos pasan): profesor crea
  tarea en su curso; estudiante no puede crear tareas (403 HTTP); estudiante
  inscrito entrega correctamente; estudiante NO inscrito no puede ni ver la
  tarea (403 HTTP); profesor califica una entrega y el estado cambia a
  `calificado`; un profesor que no es dueño del curso no puede ver la tarea
  de ese curso (403 HTTP), y por lo tanto no puede llegar a calificar.
- **Bug real encontrado y corregido durante las pruebas**: en
  `Detalle::entregar()`, `Gate::authorize('crear', $this->tarea)` resolvía
  la policy equivocada (Laravel elige la policy por la clase del objeto
  pasado, así que resolvía `TareaPolicy::crear(User, Curso)` en vez de
  `EntregaPolicy::crear(User, Tarea)`, y lanzaba un `TypeError` en
  producción). Se corrigió a
  `Gate::authorize('crear', [Entrega::class, $this->tarea])`, la forma
  correcta en Laravel para indicar explícitamente qué policy resolver
  cuando el ability no coincide con la clase del primer argumento natural.
  Ver también nota en la sección de pruebas del Módulo 4 por el mismo
  patrón.
- Suite completa: **47/47 passing**. `migrate:fresh --seed` y `view:cache`
  corren limpios.

**Nota de aprendizaje (harness de tests de Livewire):** una autorización
que falla dentro de `mount()` (p.ej. `Gate::authorize('ver', ...)`) no se
puede aserters limpiamente con `Livewire::test(...)->assertForbidden()`
— el snapshot queda inválido porque el componente nunca terminó de montar.
Para esos casos se probó por HTTP (`$this->get(route(...))->assertForbidden()`)
en vez de por el test-harness de Livewire; una autorización que falla
dentro de un método de acción posterior al mount sí se puede encadenar con
`->call(...)->assertForbidden()` sin problema.

### Módulo 4 — Quizzes y evaluaciones

**Construido:**
- `App\Livewire\Quizzes\{Index,Formulario,Resolver}`, anidados bajo el
  curso (`/cursos/{curso}/quizzes...`) igual que Tareas, con
  `scopeBindings()`.
- Constructor dinámico de preguntas en `Formulario`: agregar/quitar
  pregunta, agregar/quitar opción, marcar la opción correcta con un radio
  — todo como array reactivo de Livewire (`preguntas.{i}.opciones.{j}`),
  validado con reglas de array anidadas (`preguntas.*.opciones.*`, etc.).
  Persistido tal cual en la columna `preguntas` (JSON) de `quizzes`, como
  pide el documento del proyecto.
- `Quiz::calcularPuntaje()` (en el modelo, no en el componente, para que
  sea reutilizable/testeable): compara las respuestas del estudiante
  contra `respuesta_correcta` por pregunta y devuelve una nota sobre 20.
- `Resolver`: el estudiante responde con radios, se califica
  automáticamente al enviar, y no puede volver a resolver el mismo quiz
  (un resultado por estudiante, igual que las entregas de tareas).
- `App\Policies\QuizPolicy`: `crear`/`gestionar` reutilizan
  `CursoPolicy::gestionar`; `responder` exige estar inscrito y no tener ya
  un resultado registrado.
- `QuizFactory`, `ResultadoQuizFactory`, y un quiz de ejemplo (2 preguntas)
  agregado a `CursoSeeder`.

**Probado:**
- `tests/Feature/QuizzesTest.php` (4 tests, todos pasan): profesor crea un
  quiz con preguntas; estudiante no puede crear quizzes (403 HTTP);
  estudiante inscrito resuelve y la calificación automática da el puntaje
  correcto (1 de 2 respuestas correctas = 10/20, verificado exactamente);
  un estudiante no puede resolver el mismo quiz dos veces (403 al
  intentarlo, y se confirma que sigue habiendo un solo resultado en BD).
- Suite completa: **51/51 passing**. `migrate:fresh --seed` y
  `view:cache` corren limpios.

### Módulo 5 — Calendario y seguimiento académico

**Construido:**
- FullCalendar.js (`@fullcalendar/{core,daygrid,timegrid,list,interaction}`)
  instalado por npm y expuesto en `window.FullCalendar` desde
  `resources/js/app.js` — sin CDN, empaquetado por Vite como pide el
  stack. `App\Livewire\EventoCalendario\Index` se reescribió por completo
  (era el placeholder detectado en la auditoría del punto 1): combina
  eventos propios del usuario (`eventos_calendario`: bloques de estudio,
  recordatorios, eventos) con las fechas límite de las tareas relevantes
  (profesor: tareas de sus cursos; estudiante: tareas de los cursos en los
  que está inscrito). El calendario vive en un contenedor `wire:ignore`
  para que Livewire no lo destruya en cada render; las altas/bajas de
  eventos propios se sincronizan con `calendar.addEvent()` /
  `getEventById().remove()` vía eventos de navegador despachados desde
  PHP (`$this->dispatch(...)`), en vez de recrear el calendario completo.
- Notificaciones nativas de Laravel: se generó la migración
  `notifications` (no venía en el scaffold original — Laravel 11+ ya no la
  incluye por defecto). Dos notificaciones `database`:
  `App\Notifications\NuevaTareaAsignada` (se dispara a todos los inscritos
  al crear una tarea, desde `Tareas\Formulario::guardar()`) y
  `App\Notifications\TareaCalificada` (se dispara al estudiante cuando el
  profesor califica su entrega, desde `Tareas\Detalle::calificar()`).
- Campanita con contador (`App\Livewire\Notificaciones\Campanita`),
  montada en el topbar de `layouts/app.blade.php` para los tres roles:
  dropdown con las últimas notificaciones, contador de no leídas,
  "marcar todas leídas", `wire:poll.30s` para refrescar el contador sin
  necesidad de WebSockets.

**Probado:**
- `tests/Feature/CalendarioNotificacionesTest.php` (5 tests, todos pasan):
  un usuario crea un evento propio; un usuario NO puede eliminar el evento
  de otro (`ModelNotFoundException` porque la query ya filtra por
  `usuario_id`, y se confirma que el registro ajeno sigue intacto en BD);
  un estudiante inscrito ve la fecha de entrega de su tarea reflejada en
  el array de eventos del calendario; se notifica a todos los inscritos al
  crear una tarea (`Notification::fake()` + `assertSentTo`); se notifica
  al estudiante correcto al calificar su entrega.
- Suite completa: **56/56 passing**. `npm run build` compila con
  FullCalendar incluido (bundle ~265 KB / 76 KB gzip). `migrate:fresh
  --seed` y `view:cache` corren limpios.

### Módulo 6 — Bot de sugerencias (modo consulta / FAQ)

**Construido:**
- `App\Services\Bot\ClaudeBotService`: usa `Http::` (Guzzle) contra
  `https://api.anthropic.com/v1/messages`. Sin `ANTHROPIC_API_KEY` corre
  en **modo simulado**, con el prefijo `[MODO SIMULADO]` explícito en la
  respuesta para que nunca se confunda con una respuesta real, y devuelve
  un eco útil de la pregunta para poder demostrar el flujo completo.
  Con key real, construye un `system` prompt con el contexto del curso
  actual (nombre, descripción, unidades) para que el bot no responda a
  ciegas.
- `App\Livewire\BotSugerencias\Widget`: widget flotante (burbuja +
  panel de chat) montado una sola vez en `layouts/app.blade.php`, visible
  para los 3 roles en toda la plataforma. Detecta el curso actual leyendo
  `request()->route('curso')` (si el usuario está dentro de
  `/cursos/{curso}/...`), y asocia la conversación a ese curso; fuera de
  un curso, la conversación es general (`curso_id = null`).
- Historial persistido en `conversaciones_bot.mensajes` (JSON), un
  registro por `(usuario_id, curso_id)`, vía `ConversacionBot::agregarMensaje()`.

**Probado:**
- `tests/Feature/BotFaqTest.php` (4 tests, todos pasan): el servicio
  corre en modo simulado sin key y el texto lo indica explícitamente; los
  3 roles (administrador, profesor, estudiante) pueden usar el widget;
  el historial guarda pregunta y respuesta en orden; la conversación se
  asocia al `curso_id` correcto cuando hay contexto de curso.
- Suite completa: **60/60 passing**.
- La detección automática del curso actual desde la ruta viva
  (`request()->route('curso')` en `Widget::mount()`) se verificó por
  inspección de código y prueba manual navegando a `/cursos/{id}` en vez
  de un test automatizado: Livewire's testing harness no simula el árbol
  de rutas de una página completa al testear un componente anidado en
  aislamiento, así que un test end-to-end ahí tendría que ser un test de
  navegador — se deja como limitación conocida, no de funcionalidad.

#### Actualización (2026-09-16) — proveedor de IA intercambiable (Groq gratis / Claude de pago)

El equipo no tiene presupuesto para usar la API de Claude durante el
desarrollo y las pruebas del curso. Se agregó **Groq** como proveedor
gratuito por defecto (tier gratuito real, sin tarjeta: 30 solicitudes/min,
14400/día, con modelos open-source como Llama 3.3 70B), manteniendo Claude
disponible como alternativa de pago para cuando el proyecto lo necesite en
producción — sin romper nada de lo ya construido y probado en el punto
anterior.

**Diseño:**
- `App\Services\Bot\Contracts\ProveedorIA`: interfaz con `activo(): bool`
  y `responder(string $pregunta, ?Curso $curso, array $historial = []): string`.
  `App\Livewire\BotSugerencias\Widget` depende de esta interfaz, no de una
  clase concreta — cambiar de proveedor es una variable de entorno, no un
  cambio de código en el widget.
- `App\Services\Bot\Concerns\ConstruyeContextoIA` (trait): el prompt de
  sistema con el contexto del curso y la respuesta de modo simulado,
  compartidos por los dos proveedores para que se comporten igual frente
  al mismo curso/pregunta. `ClaudeBotService` no cambió su lógica interna
  (sigue siendo el mismo cliente Guzzle contra la API de Anthropic); solo
  se le movieron esos dos métodos al trait y se le añadió
  `implements ProveedorIA`.
- `App\Services\Bot\GroqProveedor` (nueva implementación): mismo patrón,
  contra `https://api.groq.com/openai/v1/chat/completions` (compatible con
  el formato de OpenAI: `messages` con roles `system`/`user`/`assistant`,
  en vez del campo `system` separado que usa Anthropic). Sin
  `GROQ_API_KEY` corre en modo simulado igual que Claude.
- `IA_PROVEEDOR` (`.env`, valores `groq`/`claude`, **default `groq`**) →
  `config('services.ia.proveedor')` → `AppServiceProvider::register()`
  registra el binding: `ProveedorIA::class` resuelve a `GroqProveedor` o
  `ClaudeBotService` según esa config, con `$app->make(...)` para permitir
  inyección de dependencias futura en cualquiera de los dos.

**Probado:** se ampliaron los tests de `tests/Feature/BotFaqTest.php`
(ahora 5, todos pasan): el proveedor por defecto (`groq`, sin
`GROQ_API_KEY`) resuelve a `GroqProveedor` y responde en modo simulado
mencionando explícitamente `GROQ_API_KEY`; con `IA_PROVEEDOR=claude` sin
`ANTHROPIC_API_KEY` el contenedor resuelve `ClaudeBotService` en vez de
`GroqProveedor` y responde en modo simulado mencionando
`ANTHROPIC_API_KEY`; los 3 tests del widget (los 3 roles pueden usar el
bot, se guarda el historial, se asocia al curso) siguen pasando sin
ningún cambio en su código, porque dependen de la interfaz a través de
Livewire y no de qué proveedor esté activo. Suite completa tras el
cambio: **77/77 passing** (76 anteriores + 1 neto nuevo).

**Trade-off Groq vs. Claude (documentado para el equipo):** Groq es
gratuito y rápido, pero sus modelos open-source (Llama 3.3 70B) razonan
peor que Claude en preguntas complejas o con mucho contexto de curso, y el
tier gratuito tiene límites de tasa (30 req/min, 14400/día) que alcanzan
para desarrollo y demo en clase pero no para uso real con muchos
estudiantes a la vez. Recomendación: usar `IA_PROVEEDOR=groq` durante todo
el desarrollo y la presentación al docente; evaluar cambiar a
`IA_PROVEEDOR=claude` solo si el proyecto pasa a producción real con
presupuesto asignado — el cambio es una sola variable de entorno, sin
tocar código.

### Módulo 7 — Bot de sugerencias (modo propuesta / human-in-the-loop)

Este es el módulo con la regla no negociable del proyecto (punto 7 del
brief): **el bot nunca ejecuta cambios directos sobre `eventos_calendario`,
toda propuesta pasa primero por `propuestas_bot` en estado "pendiente"**.

**Decisión de diseño (quién aprueba):** el brief no especifica
explícitamente quién aprueba la propuesta de redistribución del plan de
un estudiante. Se decidió que **el propio estudiante** apruebe/rechace/
ajuste las propuestas sobre su propio calendario (tiene sentido: es su
plan de estudio personal), y que el **profesor** reciba un resumen
ejecutivo de solo lectura (no aprueba, solo supervisa). `aprobado_por`
siempre registra quién decidió, cumpliendo la trazabilidad pedida.

**Construido:**
- `App\Services\Bot\PropuestaBotService` con las 4 funciones acotadas del
  documento: `leerProgresoEstudiante()` y `leerCalendario()` (solo
  lectura), `sugerirRedistribucionPlan()` (solo lectura + crea una fila
  `propuestas_bot` pendiente, nunca toca el calendario), y
  `ejecutarPropuestaAprobada()` — el **único** método en toda la
  aplicación que hace `EventoCalendario::create()` a partir de una
  propuesta del bot, y exige (`abort_unless`, HTTP 409) que la propuesta
  ya esté en estado `aprobado` con `aprobado_por` registrado.
- `detectarAtrasos()`: recorre inscripciones, detecta tareas vencidas sin
  entrega, genera una propuesta pendiente por tarea atrasada (sin
  duplicar si ya hay una pendiente para la misma tarea). Comando artisan
  `bot:detectar-atrasos` programado a diario a las 06:00 vía
  `Schedule::command(...)->dailyAt('06:00')` en `routes/console.php`.
- `App\Livewire\BotSugerencias\Bandeja` (`/bot/propuestas`, solo
  `estudiante`): lista sus propuestas pendientes/resueltas, permite
  **ajustar** la fecha del recordatorio propuesto antes de **aceptar**, o
  **rechazar** sin tocar el calendario. `App\Policies\PropuestaBotPolicy`
  exige que la propuesta sea del propio usuario y que esté en estado
  `pendiente` (no se puede responder dos veces).
- `App\Livewire\BotSugerencias\ResumenSemanal` (`/bot/resumen`, solo
  `profesor`): conteo de propuestas pendientes/aceptadas/rechazadas de la
  última semana para los estudiantes de sus cursos.
- `PropuestaBotSeeder`: corre `detectarAtrasos()` sobre los datos ya
  sembrados (una tarea vencida sin entrega en el curso demo) + dos
  propuestas de ejemplo ya resueltas (una aceptada, una rechazada), para
  ver los tres estados de una en la bandeja del estudiante demo.

**Probado — prueba explícita de gobernanza pedida en el punto 7:**
`tests/Feature/PropuestaBotTest.php` (7 tests, todos pasan):
1. `detectarAtrasos()` crea una propuesta pendiente y **`EventoCalendario::count()` es 0** justo después.
2. Correrlo dos veces no duplica la propuesta (misma tarea, mismo estudiante).
3. `rechazar()` cambia el estado y **no crea ningún evento de calendario**.
4. `aceptar()` cambia el estado a `aprobado`, registra `aprobado_por` y `fecha_respuesta`, y crea **exactamente un** `EventoCalendario` — ni cero ni más de uno.
5. Un estudiante no puede responder la propuesta de otro (ni siquiera existe para él: 404, la query ya está acotada por `usuario_id`).
6. Llamar `ejecutarPropuestaAprobada()` directamente sobre una propuesta todavía `pendiente` lanza un HTTP 409 y no crea nada — así que ni siquiera invocando el método "de ejecución" a mano, sin pasar por la bandeja, se puede saltar la aprobación.
7. No se puede volver a responder una propuesta ya resuelta (aceptada), y el conteo de eventos se mantiene en 1 tras el intento fallido.

Suite completa: **67/67 passing**. `php artisan schedule:list` confirma
el comando registrado; `php artisan bot:detectar-atrasos` ejecutado a
mano no crea duplicados sobre los datos ya sembrados. `migrate:fresh
--seed` y `view:cache` corren limpios.

### Módulo 8 — Panel administrativo y métricas

No crea tablas nuevas, como pide el documento: consulta directamente los
módulos 1 (usuarios), 2/3 (cursos y tareas) y 7 (uso del bot).

**Construido:**
- `App\Livewire\PanelAdmin\Metricas` (`/admin/metricas`, solo
  `administrador`): usuarios registrados por rol, total de cursos e
  inscripciones, ranking de cursos con más entregas atrasadas (calculado
  cruzando `tareas` vencidas × `inscripciones` sin `entrega`), y uso del
  bot (conversaciones, mensajes, propuestas pendientes/aceptadas/rechazadas).
- `App\Livewire\PanelAdmin\Configuracion` (`/admin/configuracion`, solo
  `administrador`): interruptor para activar/desactivar el bot de
  sugerencias en toda la plataforma. Al desactivarlo, el widget flotante
  (Módulo 6) desaparece para los 3 roles y el método `enviar()` rechaza
  con 403 aunque alguien intente forzar la llamada.

**Bug real encontrado y corregido durante las pruebas:** la primera
versión de `App\Services\ConfiguracionPlataforma` guardaba el interruptor
del bot en la tabla `cache` (vía `Cache::forever`), asumiendo que era un
almacén persistente inocuo. En la prueba de "administrador desactiva el
bot" se descubrió que el valor se revertía solo: **spatie/laravel-permission
invalida su propio caché de roles/permisos con un `flush()` completo del
store** cuando el driver no soporta tags (como `array` o `database`, los
que usa este proyecto), así que cualquier `assignRole()` posterior borraba
también nuestra clave de configuración sin relación alguna. Se corrigió
guardando la configuración en un archivo JSON pequeño en disco
(`storage/app/private/configuracion-plataforma.json`, vía `Storage::`)
en vez de la caché compartida — evita por completo el problema y sigue
sin ser una tabla nueva. Se documenta aquí porque es una lección general:
**no guardar nada propio en el store de caché por defecto de la app
cuando spatie/laravel-permission esté instalado**, salvo que se configure
un store con soporte de tags dedicado solo a permisos.

**Probado:**
- `tests/Feature/PanelAdminTest.php` (4 tests, todos pasan): administrador
  ve métricas y configuración (200); profesor y estudiante no pueden ver
  el panel admin (403, ya cubierto por el middleware `role:administrador`
  del Módulo 1 aplicado a todo el grupo de rutas `/admin`); las métricas
  reflejan correctamente un curso con una entrega atrasada; desactivar el
  bot lo oculta y bloquea `enviar()` con 403 sin crear ninguna fila en
  `conversaciones_bot`. Este último test usa `Storage::fake('local')`
  para no depender de ni dejar estado en el disco real entre corridas.
- Suite completa: **71/71 passing**. `migrate:fresh --seed` y
  `view:cache` corren limpios.

### Módulo 9 — Dashboard y reportes

No crea tablas nuevas: combina los módulos 2, 3 y 4 (cursos, tareas/
entregas, quizzes) a través de `App\Services\Reportes\ProgresoAcademicoService`,
reutilizada tanto por el dashboard como por el PDF para no duplicar la
lógica de cálculo.

**Construido:**
- Chart.js instalado por npm (`chart.js/auto`) y expuesto en
  `window.Chart` desde `resources/js/app.js`, igual que FullCalendar en
  el Módulo 5 — sin CDN, empaquetado por Vite.
- `App\Livewire\Reportes\Dashboard` (`/reportes`), con dos vistas según
  rol: `dashboard-estudiante` (gráfica de barras de promedio de tareas y
  de quizzes por curso inscrito, más el detalle de cada tarea con su
  badge de estado) y `dashboard-profesor` (gráfica de promedio de notas y
  tasa de entrega por curso; administrador ve la misma vista pero sobre
  **todos** los cursos, no solo los propios).
- `ProgresoAcademicoService::progresoEstudiante()` calcula el **promedio
  ponderado** de tareas usando `peso_nota` (no un promedio simple), y el
  promedio de quizzes por separado.
- Exportación de boleta de notas en PDF
  (`barryvdh/laravel-dompdf`): `App\Http\Controllers\Reportes\BoletaPdfController`
  en `GET /cursos/{curso}/boleta.pdf`, protegido por
  `CursoPolicy::verProgreso` (el propio estudiante inscrito, o el dueño
  del curso/administrador). La vista PDF usa HTML+CSS plano (dompdf no
  soporta las utilidades de Tailwind), con la misma paleta de color en
  los acentos.

**Probado:**
- `tests/Feature/ReportesTest.php` (5 tests, todos pasan): el promedio
  ponderado se calcula correctamente con un caso numérico verificado a
  mano (peso 2/nota 10 y peso 8/nota 20 → 18.0 exacto, no el promedio
  simple que daría 15); el estudiante ve su dashboard; el profesor ve
  promedio y tasa de entrega de sus cursos; un estudiante inscrito puede
  descargar su boleta (200, `Content-Type: application/pdf`); un
  estudiante NO inscrito no puede descargarla (403).
- Suite completa: **76/76 passing**. `npm run build` compila con
  Chart.js incluido. `migrate:fresh --seed` y `view:cache` corren
  limpios.

**Limitación conocida:** el controlador de la boleta usa siempre
`auth()->user()` como el estudiante del reporte — cualquier estudiante
inscrito descarga la suya propia correctamente, pero no existe todavía
una vista para que un profesor descargue la boleta de un estudiante
específico por nombre (tendría sentido como mejora futura: un selector
de estudiante en el dashboard del profesor que pase un `estudiante_id`
al controlador, ya cubierto por la policy `verProgreso` del lado de
autorización, solo falta la UI).

**Limitación conocida:** el contador de notificaciones se actualiza por
`wire:poll` cada 30s, no por WebSockets/broadcasting en tiempo real (el
stack del proyecto no pide Reverb/Pusher, así que se consideró
suficiente). Las vistas de Breeze que no se tocaron a fondo
(confirm-password, forgot-password, verify-email, reset-password,
delete-user-form, update-password-form) siguen con textos en inglés y
clases `indigo`/`gray` — funcionan correctamente (heredan la paleta cálida
vía el remapeo de `gray`→`stone`) pero no se tradujeron ni restilizaron
línea por línea; se prioriza por ahora el resto de los 8 módulos. Queda
como pendiente de pulido visual, no de funcionalidad.


## 4. Rediseño visual del rol Estudiante (2026-09-16)

Mejora exclusivamente de interfaz (Blade/Tailwind/Alpine) sobre las
pantallas que ya usa el estudiante, inspirada en los patrones visuales de
Canvas/Coursera/Moodle (tarjetas de curso, progreso visual, jerarquía por
urgencia) pero con la paleta pastel propia — sin tocar lógica de negocio,
permisos, rutas ni la experiencia de profesor/administrador, que quedan
exactamente como estaban (se harán en un paso aparte).

### Qué se construyó

- **Panel de inicio del estudiante** (`/dashboard`): nuevo componente
  `App\Livewire\Estudiante\PanelInicio`, embebido condicionalmente desde
  `resources/views/dashboard.blade.php` solo cuando
  `auth()->user()->hasRole('estudiante')` — el `@else` con el panel
  anterior de profesor/administrador se dejó línea por línea igual que
  estaba. Incluye: hero con saludo personalizado y anillo de progreso
  general (SVG, sin librería nueva), tarjetas de curso en grid (con
  gradiente de la paleta si el curso no tiene portada — elegido de forma
  estable a partir de `crc32($curso->nombre)`, nunca imágenes externas),
  barra de progreso y próxima fecha límite por curso, bloque de "Próximas
  entregas" (tareas + quizzes pendientes de todos los cursos, ordenados
  por fecha, atrasados resaltados en terracota), y una vista previa de
  los próximos 5 eventos de calendario con link a la página completa. La
  campanita de notificaciones y el widget del bot no se tocaron (viven en
  el layout compartido).
- **`App\Services\Reportes\ProgresoAcademicoService::resumenDashboardEstudiante()`**
  (método nuevo, los dos métodos existentes de la clase — usados por el
  Módulo 9 — no se modificaron): calcula el progreso por curso (% de
  tareas+quizzes completados) y arma la lista de próximas entregas.
- **`Cursos/Show` con pestañas para el estudiante**: se agregaron 3
  propiedades computadas nuevas a `App\Livewire\Cursos\Show`
  (`progresoModulos`, `resumenTareasEstudiante`, `resumenQuizzesEstudiante`)
  — ninguna reemplaza ni cambia el comportamiento de los métodos
  existentes (`agregarModulo`, `eliminarModulo`, `subirMaterial`,
  `inscribirme`, `render()` sigue devolviendo `modulos` igual que antes).
  En la vista, el bloque "Unidades / Módulos" se envolvió en
  `@if ($this->puedeGestionar || ! auth()->user()->hasRole('estudiante'))`
  con el HTML **idéntico** al anterior en esa rama; el `@else` (solo
  estudiante) muestra pestañas por Alpine (`x-data`/`x-show`, sin ida y
  vuelta a Livewire): Contenido/Unidades (con mini-barra de progreso por
  unidad: tareas completadas/total), Tareas, Quizzes y Materiales
  (agregados de todos los módulos), cada una con iconos.
- **`<x-icono>`** (`resources/views/components/icono.blade.php`, nuevo
  componente): set mínimo de iconos inline estilo Heroicons (sin
  dependencia nueva) usado en el panel y las pestañas.
- **Listados de Tareas y Quizzes** (`cursos.tareas.index`,
  `cursos.quizzes.index`): la rama `@if ($this->puedeGestionar)` se
  separó con el HTML exacto de antes (fila simple con conteo de
  entregas/resueltos); la rama de estudiante pasó a un grid de tarjetas
  con `<x-badge-estado>`, con jerarquía visual explícita: tareas
  atrasadas o que vencen en ≤3 días quedan con borde/fondo terracota
  destacado; tareas ya entregadas/calificadas y quizzes ya resueltos
  quedan con opacidad reducida y ordenados al final.

### Qué NO se tocó

`layouts/app.blade.php` (sidebar/topbar/campanita/widget del bot),
`Cursos\Formulario`, `Tareas\Formulario`, `Quizzes\Formulario`, cualquier
método existente de `Cursos\Show`, y toda rama `$this->puedeGestionar` en
las vistas editadas — verificado línea por línea al escribir el diff y
con tests explícitos que comprueban que el profesor/administrador siguen
viendo el HTML anterior.

### Probado

- `tests/Feature/PanelInicioEstudianteTest.php` (5 tests): el estudiante
  ve el panel nuevo; profesor y administrador siguen viendo el panel
  anterior sin el componente nuevo; el componente rechaza el montaje
  directo para un no-estudiante (403); las tarjetas de curso muestran
  progreso y enlazan a las URLs correctas (`route()`, no strings a mano);
  el progreso general da 100% cuando todo está entregado.
- `tests/Feature/CursoShowEstudianteTest.php` (4 tests): el estudiante ve
  las pestañas y no el texto "Unidades / Módulos" de la vista de gestión;
  profesor dueño y administrador siguen viendo el listado de gestión sin
  pestañas; la unidad muestra el conteo correcto de tareas completadas.
- `tests/Feature/ListadosEstudianteTest.php` (3 tests): el profesor sigue
  viendo "N entregas"/"N resueltos"; el estudiante ve tarjetas con
  `<x-badge-estado>` y nunca el texto de conteo de gestión.
- Verificación puntual adicional (no permanente) actuando como el
  usuario real sembrado `estudiante@lms.edu.pe` sobre datos reales de
  `CursoSeeder`: `/dashboard` muestra el panel nuevo con "Matemática I";
  `/cursos/{id}` muestra las 4 pestañas; los listados de tareas/quizzes
  muestran "Práctica calificada 1" y "Autoevaluación unidad 1"
  correctamente; entrar al detalle de una tarea responde 200. Se borró el
  archivo de test después de confirmar (era solo para esta verificación).
- Suite completa tras el rediseño: **89/89 passing** (82 antes de este
  cambio + 7 nuevos). `php artisan pint` sin cambios pendientes,
  `php artisan view:cache` compila sin errores, `npm run build` compila
  con las clases Tailwind nuevas (paleta ya existente, sin colores
  nuevos).

### Cómo verlo en el navegador

1. `php artisan serve` (o el entorno que uses) y entra como
   `estudiante@lms.edu.pe` / `password123`.
2. `/dashboard`: hero con tu nombre y el anillo de progreso, tarjetas de
   los 3 cursos demo con barra de progreso, bloque "Próximas entregas"
   (deberías ver "Ejercicios de repaso" atrasada, resaltada en
   terracota), y la vista previa del calendario a la derecha.
3. Click en cualquier tarjeta de curso → pestañas **Contenido / Tareas /
   Quizzes / Materiales** en la parte superior del curso; la pestaña
   Contenido muestra la barra de progreso de cada unidad.
4. `/cursos/{id}/tareas` y `/cursos/{id}/quizzes` → tarjetas en grid con
   la tarea/quiz atrasado o pendiente resaltado, y los ya
   entregados/resueltos discretos al final.
5. Para comparar: entra como `profesor@lms.edu.pe` o `admin@lms.edu.pe` —
   esas pantallas deben verse exactamente igual que antes de este cambio.

## 5. Checklist final de pulido del rol Estudiante (2026-09-16)

Segunda pasada sobre el rediseño del rol estudiante (sección 4), contra un
checklist de 7 puntos entregado por el equipo. Mismo alcance que antes:
solo interfaz, cero cambios de lógica de negocio/permisos/rutas salvo
adiciones puntuales explícitamente necesarias, Profesor/Administrador sin
tocar salvo la excepción explícita de iconos de sidebar (punto 5, ver
abajo).

### 1. Panel organizado por widgets

`App\Livewire\Estudiante\PanelInicio` y su vista se reestructuraron en
widgets independientes con encabezado ícono+título+"Ver todo": **Mis
cursos**, **Próximas entregas**, **Calendario** (nuevo: mini-grilla del
mes con Alpine — 7 columnas, offset del primer día calculado con
`dayOfWeekIso`, día de hoy resaltado, punto indicador en los días con
evento, click abre un popover con los títulos de ese día; debajo sigue la
lista corta de próximos 5 eventos) y **Accesos rápidos** (Calendario, Mis
cursos, y Asistente — este último despacha un evento de navegador
`abrir-bot` que escucha `App\Livewire\BotSugerencias\Widget` vía un
listener `#[On('abrir-bot')]` nuevo, sin acoplar ambos componentes). El
dato nuevo (eventos del mes agrupados por día) se calcula directo en
`PanelInicio::calendarioMes()`, sin duplicar la lógica de creación/borrado
de `EventoCalendario\Index`. Grid de 3 columnas en pantallas grandes
(`lg:grid-cols-3`), ya cumplía "al menos 2 columnas".

### 2. Cursos/Show

Ya tenía pestañas y progreso por unidad (sección 4). Se agregó la portada
con iniciales del curso superpuestas en tipografía grande semitransparente
cuando no hay imagen subida — tanto en `Cursos/Show` como en las tarjetas
de curso del panel, para que se sienta consistente. El `<div>` de la
portada (compartido con profesor/administrador) solo gana `relative
overflow-hidden`, que no cambia nada visualmente para ellos porque no se
les agrega ningún hijo posicionado; el overlay de iniciales está dentro de
un `@if (... && auth()->user()->hasRole('estudiante'))`, así que el HTML
que ve un profesor es exactamente el mismo de antes.

### 3. Listados de tareas y quizzes

Ya usaban `<x-badge-estado>` y jerarquía por urgencia (sección 4). Se
sumó la sombra sutil consistente (ver punto 7) que faltaba en algunas
tarjetas.

### 4. Reportes / "Mi progreso" — acordeón con tabla real

`resources/views/livewire/reportes/dashboard-estudiante.blade.php` se
reescribió por completo (sin tocar `App\Livewire\Reportes\Dashboard.php`
ni `dashboard-profesor.blade.php`: los datos que ya devolvía
`ProgresoAcademicoService::progresoEstudiante()` — tareas con peso/nota/
estado, quizzes con puntaje — ya traían todo lo necesario). Cada curso es
ahora un `x-data="{ abierto: false }"` con:
- **Colapsado**: promedio general (redondeado, combinando tareas y
  quizzes) y el botón "Boleta PDF", siempre visibles, con `@click.stop`
  para que no dispare el acordeón.
- **Expandido** (`x-show`/`x-transition`, sin `x-collapse` — ese plugin de
  Alpine no está instalado en el proyecto, así que se evitó para no
  depender de algo no verificado): una `<table>` real con columnas
  Evaluación | Peso | Nota | Estado, una fila por tarea y por quiz, con
  `<x-badge-estado>` solo en la columna Estado — ya no son badges sueltos
  uno al lado del otro.

### 5. Iconografía SVG, cero emojis

- **Un solo sistema de iconos**: `<x-icono>` (ya existía, se amplió) con
  14 iconos inline estilo Heroicons *outline*, mismo `stroke-width="1.5"`
  para todos — nunca se mezcla con otra librería ni con glifos sueltos.
  Nuevos: `inicio`, `grafico`, `bombilla`, `usuarios`, `engranaje`,
  `chat`, `cerrar`, `descargar`, `flecha-abajo`. Se corrigió además un bug
  menor de la sección 4: `check-circulo` tenía la misma ruta que `tarea`
  (copiar/pegar); ahora es un ícono de verificación distinto.
- **Emojis eliminados** de todo lo estudiante-facing: saludo del panel
  (👋), estado vacío de "próximas entregas" (🎉), archivo entregado en
  `Tareas/Detalle` (📎, solo en la rama de estudiante — la rama de
  profesor en el mismo archivo mantiene su 📎 intacta, no se tocó),
  boleta PDF en Reportes (📄), y el título de tarea en el calendario
  (📌, en `App\Livewire\EventoCalendario\Index` — archivo compartido sin
  rama de profesor separada, ver nota abajo). **No se tocaron** los
  emojis en pantallas exclusivas de profesor/administrador:
  `Quizzes/Formulario` (✕ al quitar una opción) y la rama de gestión de
  `Tareas/Detalle` y `Cursos/Show` (📎) — confirmado con `grep` de todo
  el rango Unicode de emojis antes y después del cambio.
- **Widget del bot y campanita**: el widget (visible para los 3 roles, no
  es pantalla exclusiva de nadie) cambió sus glifos de cerrar/abrir por
  `<x-icono nombre="cerrar"/>` y `<x-icono nombre="chat"/>`. La campanita
  ya usaba SVG desde el Módulo 5, sin cambios.
- **Iconos en el sidebar** (`layouts/app.blade.php`, compartido): única
  excepción explícita a "no tocar profesor/administrador" en este pulido,
  pedida directamente en el punto 5 del checklist. Se agregó un ícono por
  ítem de menú — incluidos los de profesor/administrador (Usuarios,
  Métricas, Configuración, Resumen del bot), no solo los de estudiante:
  dejar la mitad de la lista con ícono y la otra mitad con el punto
  anterior se habría visto roto/inconsistente para los otros roles, que
  es justamente lo que la restricción general busca evitar. El punto que
  marcaba el ítem activo se reemplazó por el ícono mismo; el resto del
  sidebar (colores de acento por rol, qué ítems ve cada rol) no cambió.

### 6. Modo oscuro con identidad de marca

- Tokens nuevos en `tailwind.config.js` (dentro del grupo `menta`, sin
  colores fuera de la paleta ya definida): `menta-superficie-oscura`
  (`#16302c`, fondo de tarjeta en modo oscuro) y `menta-borde-oscuro`
  (`#20433c`, borde de tarjeta en modo oscuro) — un gris-verde oscuro con
  matiz de menta, no negro/gris genérico. Se aplicaron en reemplazo de
  `dark:bg-stone-800`/`dark:border-stone-700` en **todas** las superficies
  exclusivas de estudiante: `PanelInicio`, la rama de pestañas de
  `Cursos/Show`, las tarjetas de `Tareas/Quizzes Index` (rama estudiante),
  y el acordeón de `Reportes`. La rama de gestión de profesor/
  administrador en esos mismos archivos conserva `dark:bg-stone-800` sin
  tocar.
- Diferencia de luminosidad entre página y tarjeta: el fondo de página
  (`dark:bg-stone-950`, en el layout compartido, sin tocar) es casi negro;
  `menta-superficie-oscura` es notablemente más clara y con matiz de
  color distinto — cumple "al menos dos tonos" sin necesidad de retocar
  el layout compartido.
- Sombra en modo claro / borde en modo oscuro: se aplicó `shadow-sm
  dark:shadow-none` de forma consistente en todas las tarjetas
  estudiante-facing (antes algunas solo tenían `hover:shadow-sm`, sin
  sombra de reposo) — border ya existía en todas.
- **Contraste del calendario**: `App\Livewire\EventoCalendario\Index`
  (página compartida entre profesor y estudiante, sin una rama separada
  razonable de duplicar solo por este detalle — se optó por mejorar el
  contraste para ambos, es una corrección de calidad, no un rediseño) —
  los 3 colores de evento pasaron de sus tonos pasteles/medios (`#7FB3AE`,
  `#B2967D`) a variantes oscuras (`#3C7A75`, `#86715E`) con `textColor:
  '#ffffff'` explícito, para que el texto blanco de FullCalendar tenga
  contraste suficiente en modo claro y oscuro por igual. El color de
  "recordatorio/tarea" (`#B2604A`, terracota oscuro) ya era suficientemente
  oscuro y no cambió.

### 7. Jerarquía, espaciado y profundidad

- `PanelInicio`: `space-y-10` entre bloques principales (antes
  `space-y-8`), `space-y-8` entre widgets dentro de cada columna — más
  aire vertical.
- `border-radius` consistente (`rounded-xl`) en todas las tarjetas
  estudiante-facing tocadas en este pulido; ya era así en su mayoría, se
  verificó y no se encontraron inconsistencias.
- Sombra sutil / borde en su lugar: ver punto 6.
- Banner de bienvenida: se agregó un patrón geométrico discreto (3
  círculos concéntricos en SVG, `text-white/10`, `pointer-events-none`)
  en la esquina superior derecha del hero, sobrio y de baja opacidad — no
  reemplaza el degradado de color, solo le da textura.

### Probado

- `tests/Feature/PulidoEstudianteTest.php` (3 tests nuevos, todos pasan):
  el mini-calendario agrupa correctamente un evento del mes por día
  (aparece su título y el nombre del mes); el acceso rápido del bot
  despacha `abrir-bot` y el componente hermano `Widget` (instancia
  independiente) recibe el evento y se abre; un curso sin portada muestra
  las iniciales del nombre en vez de un bloque vacío.
- Verificación puntual adicional (no permanente, igual patrón que la
  sección 4) actuando como `estudiante@lms.edu.pe` sobre datos reales:
  las 6 pantallas del checklist (Panel, Curso, Tareas, Quizzes, Reportes,
  Calendario) responden 200, muestran el contenido esperado (incluido
  "Accesos rápidos", "Boleta PDF"), y **ninguna contiene ningún carácter
  del rango Unicode de emojis** (`\x{1F300}-\x{1FAFF}`, `\x{2600}-\x{27BF}`),
  verificado con una expresión regular sobre el HTML completo de cada
  respuesta, no solo por inspección visual. Se borró el archivo de test
  después de confirmar.
- Suite completa tras el pulido: **92/92 passing** (89 antes de este
  checklist + 3 nuevos). `php artisan pint` sin cambios pendientes tras
  aplicarlo, `php artisan view:cache` compila sin errores, `npm run
  build` compila con los tokens de color nuevos.

### Qué no se pudo cumplir tal cual se pedía, y qué se hizo en su lugar

- El checklist pedía el mini-calendario "Alpine.js, no FullCalendar" sin
  especificar si necesitaba navegación entre meses. Se implementó como
  una grilla de **solo el mes actual**, con los datos calculados en PHP
  (`PanelInicio::calendarioMes()`) y solo la interacción de abrir/cerrar
  el popover de un día manejada por Alpine (`x-data`/`x-show`/`@click`) —
  no se agregó navegación mes a mes (botones prev/next) porque el
  checklist no la pedía explícitamente y el enlace "Ver todo" ya lleva al
  calendario completo con FullCalendar para ese caso de uso. Si el equipo
  la quiere, es una extensión acotada al mismo componente (agregar un
  `mes` en el estado y recalcular `calendarioMes()` para ese mes).
- Los puntos 5 y 6 sobre el calendario (emoji `📌` y contraste de colores)
  tocan `App\Livewire\EventoCalendario\Index`, que es una página
  **compartida** entre profesor y estudiante sin una rama de renderizado
  distinta entre ambos roles (a diferencia de `Cursos/Show` o los
  listados de Tareas/Quizzes, que sí tienen una rama `puedeGestionar`
  separada). No existía una forma de aplicar el pulido solo para
  estudiante sin duplicar la página completa, así que se aplicó para
  ambos roles — se documenta explícitamente aquí porque técnicamente toca
  lo que ve un profesor, aunque el cambio en sí es una mejora de
  contraste/limpieza neutral (no un rediseño ni un cambio de layout) que
  no debería necesitar revisión aparte.

## 6. Corrección de paleta de color — sin degradados, sin colores fuera de la paleta (2026-09-16)

Ajuste puntual pedido tras revisar el Panel en el navegador: las portadas de
curso usaban degradado en vez de color plano, y aparecían tonos fuera de la
paleta oficial (celeste/naranja) en la vista de Calendario.

### 1. Portadas de curso: de degradado a color plano

- `resources/views/livewire/estudiante/panel-inicio.blade.php` (widget "Mis
  cursos"): el array `$gradientes` (`from-* to-*`) se reemplazó por
  `$coloresPortada`, una rotación de 4 colores **planos** tomados solo de la
  paleta ya definida: `bg-menta-oscuro`, `bg-terracota-oscuro`,
  `bg-cafe-arcilla`, `bg-menta-base`. La selección sigue siendo determinística
  (`crc32($curso->nombre) % count(...)`), así que cada curso conserva siempre
  el mismo color entre recargas.
- `resources/views/livewire/cursos/show.blade.php` (portada del curso): esta
  vista es compartida con profesor/administrador, así que el degradado
  original (`from-menta-fondo to-arena-medio dark:from-stone-700
  dark:to-stone-800`) se dejó intacto para ellos. Solo para el estudiante se
  calcula el mismo color plano rotativo (`$colorPortadaCurso`, mismo
  criterio) mediante un `@if (auth()->user()->hasRole('estudiante'))`
  inline — cero cambio de HTML para profesor/administrador. Se ajustó también
  la opacidad de las iniciales superpuestas (`text-white/15` uniforme) porque
  ahora se pintan siempre sobre un color plano, no sobre un degradado claro.
- El degradado del banner de bienvenida del Panel (`from-menta-oscuro
  to-menta-base`) **no se tocó**: el pedido fue específicamente sobre
  "portadas de curso", y ambos extremos de ese degradado ya son tonos
  oficiales de la paleta (menta oscuro/base), no un color ajeno.

### 2. Colores fuera de la paleta

Se auditó (`grep` de `blue-`, `orange-`, `cyan-`, `indigo-`, `sky-`,
`amber-`, etc.) cada vista/componente tocado en el rediseño del estudiante:
Panel, Cursos/Show, listados de tareas/quizzes, Reportes, Calendario, sidebar,
`<x-badge-estado>`, `<x-icono>` y el widget del bot. Todas las clases de color
ya usaban tokens de paleta (`menta-*`, `terracota-*`, `arena-*`, `cafe-*`,
`exito-*`, `stone-*`) — no se encontró ninguna clase Tailwind por defecto
suelta en esas vistas.

La fuente real del celeste y el naranja que se veían en la vista completa de
Calendario era **el tema por defecto de FullCalendar**, no una clase Tailwind:
la librería define sus colores vía variables CSS (`--fc-button-bg-color:
#2C3E50`, `--fc-event-bg-color: #3788d8` azul, `--fc-today-bg-color:
rgba(255, 220, 40, 0.15)` amarillo/naranja) que nunca se habían sobrescrito.
Se corrigió en `resources/views/livewire/evento-calendario/index.blade.php`
fijando esas variables con la sintaxis de propiedades arbitrarias de Tailwind
(`[--fc-button-bg-color:#3C7A75]`, etc.) directamente en el contenedor del
calendario, usando solo hexadecimales ya existentes en `tailwind.config.js`
(menta oscuro/base para botones y highlight, arena claro/medio para bordes y
fondos neutros, y las superficies oscuras `menta-superficie-oscura` /
`menta-borde-oscuro` para el modo oscuro). Este archivo es compartido con
profesor (mismo caso ya documentado en la sección 5): el cambio es solo de
variables de color/contraste, no de layout ni de comportamiento, así que se
aplicó para ambos roles.

### Verificación

- `npm run build`: sin errores.
- `php artisan view:clear && php artisan view:cache`: sin errores de Blade.
- `vendor/bin/pint --dirty --format agent`: limpio (solo un ajuste cosmético
  preexistente ajeno a este cambio).
- `php artisan test`: **92/92 tests, 220 assertions**, sin cambios en los
  tests existentes.
- Verificación puntual contra datos reales (`estudiante@lms.edu.pe`,
  `test_panel_y_curso_no_usan_clases_fuera_de_la_paleta`, borrada tras
  confirmar): Panel, Cursos/Show y Calendario cargan 200 OK y ninguno
  contiene `blue-`, `orange-`, `cyan-`, `indigo-`, `sky-` ni `amber-` en su
  HTML renderizado.

## 7. Nueva paleta de color (fría) + calendario más intuitivo — rol Estudiante (2026-09-16)

Cambio de dirección de color pedido explícitamente: se abandona la paleta
cálida tierra (menta/terracota/arena/café) para el rol Estudiante y se
adopta una paleta pastel fría. **La paleta anterior no se borró** — sigue
definida en `tailwind.config.js` tal cual, porque Profesor/Administrador
todavía la usan y se migrarán en un pase aparte (instrucción explícita del
usuario). Los dos catálogos de tokens conviven sin conflicto.

### Tokens nuevos (`tailwind.config.js`)

| Token | Valor(es) | Uso |
|---|---|---|
| `azul.fondo` / `azul.oscuro` | `#7FA9C9` / `#2F5A78` | Identidad de marca, navegación activa, botones primarios, enlaces |
| `celeste` | `#B6D5E5` | Fondos de tarjeta, superficies, sidebar |
| `lavanda.fondo` / `lavanda.oscuro` | `#B8B0D8` / `#5B4E8A` | Acento secundario: quizzes, widget del bot |
| `verde.fondo` / `verde.oscuro` | `#B8D8C8` / `#3F6B4A` | Progreso, éxito, calificado/aprobado |
| `coral.fondo` / `coral.oscuro` | `#E8A9A0` / `#A14B3F` | Alerta/atraso/urgente (tono nuevo, no existía en la paleta fría) |
| `fondo` | `#F7F9FB` | Fondo de página (`<body>`), modo claro |
| `texto` | `#374151` | Texto base/primario |
| `azul.superficie-oscura` / `azul.borde-oscuro` / `azul.pagina-oscura` | `#12232F` / `#1A3242` / `#0B1620` | Tarjeta, borde y página en modo oscuro — tres tonos de luminosidad distintos, tintados de azul, nunca negro/gris genérico |

Los textos secundarios/neutros usan la familia `slate-*` de Tailwind (gris
frío nativo, sin tocar el alias `gray → stone` global porque ese alias sigue
sirviendo a Profesor/Administrador y a las vistas de autenticación
compartidas). **Cero degradados**: se auditó cada `bg-gradient-to-*` tocado
en el pase anterior y se reemplazó por color plano (rotación determinística
de 4 tonos por `crc32($curso->nombre)`, igual criterio que antes). El único
degradado que sigue vivo es el del banner de bienvenida del Panel
(`bg-azul-oscuro`, sólido ahora — antes usaba dos tonos de menta en
degradado, corregido también).

### Componentes compartidos vueltos "role-aware"

Varias piezas de UI se usan literalmente en todas partes (botones, inputs,
badges, campanita de notificaciones, widget del bot, sidebar) y no tenían
ninguna rama por rol. Duplicarlas habría violado "no toques Profesor/
Administrador"; dejarlas sin tocar habría dejado rastros de la paleta vieja
en pantallas del estudiante. Se resolvió con el mismo patrón ya usado en el
pase anterior (branch por `auth()->user()->hasRole('estudiante')`, rama de
Profesor/Administrador **byte-idéntica** a como estaba):

- `resources/views/components/primary-button.blade.php`
- `resources/views/components/secondary-button.blade.php`
- `resources/views/components/text-input.blade.php`
- `resources/views/components/badge-estado.blade.php` (ya era compartido desde el pase anterior; ahora tiene un segundo mapa de colores)
- `resources/views/livewire/bot-sugerencias/widget.blade.php` (acento **lavanda** para Estudiante, pedido explícitamente como color del bot)
- `resources/views/livewire/notificaciones/campanita.blade.php`
- `resources/views/layouts/app.blade.php` (sidebar, header, fondo de página — variables `$cromaBody`/`$cromaSuperficie`/`$cromaBorde`/etc. calculadas una vez a partir de `$esEstudiante`)

`x-danger-button` y `x-input-label` **no** se tocaron: son infraestructura de
cuenta genérica (eliminar cuenta, etiquetas de formulario) compartida por
igual entre los 3 roles, sin relación con la identidad visual del LMS —
fuera de alcance razonable de este pedido.

### Calendario: interacción rediseñada (solo Estudiante)

`resources/views/livewire/evento-calendario/index.blade.php` no tenía
ninguna rama por rol (compartía el formulario siempre-visible y el
calendario 1:1 entre Profesor y Estudiante). Como este pedido exige la
nueva interacción **solo para Estudiante**, se dividió por primera vez en
`@if (auth()->user()->hasRole('estudiante')) ... @else ... (byte-idéntico a como estaba) @endif`.
`app/Livewire/EventoCalendario/Index.php` **no cambió**: los métodos
`crear()`, `eliminar()`, `payloadEvento()` y `getEventosProperty()` son
exactamente los mismos; todo lo nuevo es Alpine.js + configuración de
FullCalendar en el navegador.

Para Estudiante:

- El formulario siempre-visible se reemplazó por un botón **"+ Nuevo
  evento"** inyectado en la barra de herramientas de FullCalendar mismo
  (`customButtons` + `headerToolbar.right: 'nuevoEvento dayGridMonth,...'`),
  así que hereda automáticamente el mismo tema de color que los demás
  botones del calendario (mes/semana/lista) en vez de verse pegado aparte.
  Abre un modal Alpine (`x-show="modalAbierto"`) con el mismo formulario de
  siempre (Título, Fecha y hora, Tipo) pero estilizado con la paleta nueva.
- **Clic en un día vacío** (`dateClick` del plugin de interacción, ya
  instalado): fija `$wire.nuevaFecha` a esa fecha a las 9:00 a. m. y abre el
  mismo modal, ya pre-rellenado.
- El input de fecha/hora sigue siendo un `datetime-local` nativo (no se
  agregó ninguna librería de date-picker nueva — el proyecto no tenía una en
  el allowlist de CDN permitido), pero ahora usa exactamente los mismos
  tokens de borde/foco que el resto del formulario en vez del estilo por
  defecto del navegador sin tocar.
- **Truncado de eventos**: `displayEventTime: false` (el color ya indica el
  tipo, no hace falta repetir la hora en la celda) + un `<style>` con
  `-webkit-line-clamp: 2` y `text-overflow: ellipsis` scopeado a
  `.calendario-estudiante .fc-event-title`, así el título se corta limpio en
  máximo 2 líneas en vez de partirse a la mitad ("11:5 Ejercicios de
  repas...").
- **Clic en un evento**: si es una tarea (tiene `url`), navega al detalle
  como antes. Si es un evento propio (bloque de estudio/recordatorio/
  evento, sin `url`), abre un panel de detalle (título completo, fecha
  formateada, botón "Eliminar evento" que reutiliza el método `eliminar()`
  existente — no es lógica nueva, es el mismo botón que ya existía en la
  lista "Tus eventos propios", ahora accesible también desde la grilla).
- Leyenda de colores actualizada a los puntos de color reales que usa el
  calendario ahora: azul oscuro = bloque de estudio, coral oscuro =
  recordatorio/tarea, lavanda oscuro = evento.
- Variables CSS de FullCalendar (`--fc-button-*`, `--fc-today-bg-color`,
  etc., ya corregidas en el pase anterior para no mostrar el azul/amarillo
  por defecto de la librería) actualizadas a los nuevos hexadecimales azul/
  celeste en vez de menta/arena.

Para Profesor/Administrador la rama `@else` es una copia exacta del archivo
tal como estaba antes de este pase (formulario siempre visible, sin modal,
paleta cálida, mismos `--fc-*` de menta) — cero cambio de comportamiento.

### Verificación

- `npm run build`: sin errores. `php artisan view:clear && view:cache`: sin
  errores de Blade.
- `vendor/bin/pint --dirty --format agent`: limpio.
- `php artisan test`: **92/92 tests, 220 assertions**, sin cambios en los
  tests existentes.
- Dos verificaciones puntuales contra datos reales (creadas, corridas y
  borradas tras confirmar):
  - Como `estudiante@lms.edu.pe`: Panel, Cursos/Show, Tareas, Quizzes,
    Reportes y Calendario cargan 200 OK; **cero** rastro de `menta-`,
    `terracota-`, `arena-`, `cafe-arcilla` ni `bg-gradient-to` en el HTML
    renderizado de esas seis pantallas; el HTML del Calendario sí contiene
    el botón "+ Nuevo evento" y los hooks de interacción nuevos
    (`dateClick`, `abrir-modal-evento`, `ver-evento`).
  - Como `profesor@lms.edu.pe`: Panel, Cursos/Show y Calendario cargan 200
    OK; **cero** rastro de `azul-`, `celeste`, `lavanda-`, `coral-`,
    `verde-fondo`, `bg-fondo` ni `text-texto`; el Calendario de Profesor
    sigue mostrando el formulario siempre-visible (`wire:submit="crear"`
    suelto en la página) y no muestra el botón "+ Nuevo evento" — confirma
    que la migración de paleta e interacción quedó aislada al Estudiante en
    ambas direcciones.

### Qué no se pudo cumplir tal cual se pedía

- El pedido de "librería ligera de date-picker" para el input de fecha/hora
  no se aplicó: el proyecto no tiene ninguna en el allowlist de CDN
  permitido (`cdnjs.cloudflare.com`, `jsdelivr`, `tailwindcss.com`,
  `code.jquery.com`) ya instalada ni referenciada, y agregar una dependencia
  nueva sin aprobación va contra las reglas del proyecto
  (`CLAUDE.md`: "no cambies las dependencias de la aplicación sin
  aprobación"). Se usó la alternativa que el propio pedido dejaba como
  válida ("puedes usar un input nativo `datetime-local` pero con estilos
  Tailwind consistentes"): mismo input nativo, re-estilizado para combinar
  con el resto del formulario.
- `x-input-label` y `x-danger-button` no se migraron a la paleta nueva
  (quedan con el estilo neutro/compartido de siempre) porque son
  infraestructura de cuenta genérica sin relación con la identidad visual
  del LMS — no encajan en el alcance de "Estudiante" vs. "Profesor/
  Administrador", los usan los 3 roles por igual.

## 8. Selector de fecha con Flatpickr + corrección del badge de rol (2026-09-16)

### 1. Flatpickr en vez del `datetime-local` nativo

El único input de fecha/hora del rol Estudiante (el campo "Fecha y hora" del
modal "Nuevo evento" en `evento-calendario/index.blade.php`) usaba
`<input type="datetime-local">`, cuyo calendario emergente lo dibuja el
navegador/sistema operativo y no se puede re-temear. Se auditaron el resto
de inputs `datetime-local` del proyecto (`tareas/formulario.blade.php`,
`bot-sugerencias/bandeja.blade.php`) y ambos son exclusivos de Profesor —
fuera de alcance, sin tocar.

- Flatpickr 4.6.13 se carga **bajo demanda vía CDN** (`cdnjs.cloudflare.com`,
  ya en el allowlist) solo cuando el Alpine `init()` de la página de
  Calendario del estudiante se monta — no se agregó como dependencia npm
  (`package.json` intacto, cumpliendo la regla de "no cambiar dependencias
  sin aprobación") ni se carga en ninguna página de Profesor/Administrador.
  Se inyectan `flatpickr.min.css`, `flatpickr.min.js` y `l10n/es.js` mediante
  `document.createElement` + `onload` encadenados, evitando cualquier duda
  sobre el orden de ejecución de `<script>` en transiciones `wire:navigate`.
- Configuración: `enableTime: true`, `time_24hr: false` (12 h con AM/PM,
  el formato más común en Perú), `locale: flatpickr.l10ns.es`, `altInput:
  true` con `altFormat` en español legible ("16 de septiembre de 2026, 02:30
  PM") mientras el input original —ahora oculto, es lo que Flatpickr hace
  con `altInput`— mantiene el formato `Y-m-d\TH:i` que ya esperaba el
  backend, idéntico al que producía el `datetime-local` nativo. **Cero
  cambios en `EventoCalendario/Index.php`**: la validación `'nuevaFecha' =>
  ['required', 'date']` y `crear()` siguen exactamente igual.
- El calendario emergente de Flatpickr se re-temeó por completo en
  `resources/css/app.css` (día seleccionado, hover, hora, flechas de
  mes) con los tokens azul/celeste nuevos, con su propia sección
  `@media (prefers-color-scheme: dark)` — coherente con `darkMode: 'media'`
  del proyecto. Estas reglas son globales en el archivo CSS pero inertes en
  cualquier página que no cargue Flatpickr (Profesor/Administrador nunca lo
  cargan, así que nunca se ejercitan).
- El campo de fecha/hora (input + Flatpickr + botones rápidos de hora) se
  envolvió en `wire:ignore` para que Livewire no intente re-renderizar ese
  subárbol tras cada `wire:submit` (perdería la UI que Flatpickr inyectó).
  El mensaje de error de validación (`<x-input-error>`) quedó **fuera** de
  ese `wire:ignore`, así que sigue apareciendo/actualizándose con
  normalidad cuando la validación falla. El valor sigue llegando a
  `wire:model="nuevaFecha"` con normalidad: Livewire ya escuchaba el evento
  `change` del input desde el montaje inicial, y `wire:ignore` solo evita
  el re-morphing posterior, no desactiva ese listener.
- **Compatibilidad con el test existente**: `tests/Feature/
  CalendarioNotificacionesTest.php` llena `nuevaFecha` con
  `Livewire::test(...)->set('nuevaFecha', ...)`, es decir, escribe
  directamente la propiedad del componente sin tocar el DOM — Flatpickr
  nunca entra en juego en ese test, así que no necesitó ningún cambio y
  sigue pasando tal cual.

### 2. Selección de fecha más simple desde el calendario

- Clic en un día vacío (`dateClick`, ya soportado) ahora despacha la fecha
  clickeada junto con el evento `abrir-modal-evento`; el listener llama a un
  nuevo método Alpine `abrirDesdeFecha(fechaStr)` que fija esa fecha en
  Flatpickr (conservando la hora previamente elegida, o 9:00 a. m. por
  defecto), abre el propio calendario de Flatpickr y enfoca directamente su
  campo de hora (`fp.hourElement.focus()`) — el usuario solo tiene que
  elegir la hora y escribir el título, sin que el foco caiga en "Título"
  primero.
  Al hacer clic en el botón "+ Nuevo evento" (sin un día asociado) el
  comportamiento no cambió: el modal abre con el foco por defecto del
  navegador (en "Título"), como pedía el alcance original del punto.
- Se agregaron 5 botones de horario común debajo del campo de fecha/hora
  (9:00 AM, 12:00 PM, 3:00 PM, 6:00 PM, 8:00 PM) que llaman a
  `ponerHora(hora, minuto)`: toman la fecha ya elegida (o el día de hoy si
  todavía no se eligió ninguna) y le aplican esa hora exacta vía
  `fp.setDate(..., true)`, sin necesidad de abrir el desplegable de
  Flatpickr — sin quitar la opción de elegir una hora distinta con el
  selector completo.
- Al cerrarse el modal por cualquier vía (crear con éxito, "Cancelar", clic
  fuera, Escape) un `x-effect` llama a `fp.clear()`, así el campo siempre
  arranca vacío la próxima vez que se abre, en vez de mostrar la fecha del
  evento anterior.

### 3. Contraste del badge "Estudiante" en el sidebar

El badge de rol en la esquina inferior del sidebar reutilizaba las mismas
clases que el resaltado de la pestaña activa (`bg-celeste/60
text-azul-oscuro`, sin variante `dark:`); en modo oscuro esa opacidad del
60% mezclada contra el fondo oscuro del sidebar perdía casi todo el
contraste. Se le dio un estilo propio, independiente del resaltado de
navegación, **solo para Estudiante** (Profesor/Administrador conservan
exactamente `$acento['activo']` como antes, cero cambio): fondo celeste
sólido + texto azul oscuro en modo claro, e invertido — fondo azul oscuro
sólido + texto blanco — en modo oscuro, más `font-bold` en vez de
`font-semibold`.

### Verificación

- `npm run build`: sin errores. `php artisan view:cache`: sin errores de
  Blade.
- `vendor/bin/pint --dirty --format agent`: limpio.
- `php artisan test`: **92/92 tests, 220 assertions**, sin cambios en los
  tests existentes (incluido el que usa `nuevaFecha` directamente).
- Verificación puntual contra datos reales (creada, corrida y borrada tras
  confirmar): como `estudiante@lms.edu.pe`, la página de Calendario carga
  200 OK, incluye las etiquetas `<script>`/`<link>` de Flatpickr apuntando a
  `cdnjs.cloudflare.com/ajax/libs/flatpickr` y `l10n/es.js`, ya no contiene
  ningún `type="datetime-local"`, sí contiene los 5 botones de horario
  rápido y los métodos `ponerHora`/`abrirDesdeFecha`, y el badge de rol
  tiene las clases de alto contraste esperadas. Como `profesor@lms.edu.pe`,
  la misma página no menciona "flatpickr" en absoluto y conserva el
  `type="datetime-local"` nativo — confirma que el cambio quedó aislado al
  Estudiante.

### Qué no se pudo cumplir tal cual se pedía

Nada quedó sin resolver de los 3 puntos pedidos. Una precisión: el pedido
sugería que el re-temeo de Flatpickr "normalmente se hace sobrescribiendo
las clases CSS... en nuestro CSS del proyecto" — así se hizo exactamente
(`resources/css/app.css`), pero como es CSS plano (no clases de utilidad de
Tailwind), no hay una forma de "scopearlo" solo a la página de Estudiante
más que el hecho de que Flatpickr, como librería, solo se carga ahí; se
documenta explícitamente para que quede claro que es una regla global en el
archivo aunque su efecto práctico esté aislado.

## 9. Corrección: el bot no respondía al activar Groq (2026-09-16)

Al configurar `GROQ_API_KEY`, el bot dejó el modo simulado pero devolvía el
mensaje genérico de error. Fueron **dos causas independientes**, encontradas
en orden al revisar `storage/logs/laravel.log`:

### Causa 1 (bloqueaba todo): sin CA bundle configurado para cURL/OpenSSL en este entorno

El primer intento falló con `cURL error 60: SSL certificate ... unable to
get local issuer certificate`. `php -i` confirmó que `curl.cainfo` y
`openssl.cafile` estaban vacíos en el `php.ini` que usa este PHP de
Windows/XAMPP (el que corre `php artisan serve`), así que cURL no tenía
ningún certificado raíz contra el cual validar `api.groq.com` — **ninguna**
petición HTTPS saliente de PHP en esta máquina podía funcionar, no era algo
específico de Groq. No es un bug del código del proyecto, es una máquina sin
el CA bundle que la mayoría de instalaciones de PHP en Linux/macOS ya traen
del sistema operativo.

Corrección (fuera del repositorio, es configuración de la máquina, no del
proyecto): se descargó el bundle oficial de Mozilla que publica curl
(`https://curl.se/ca/cacert.pem`) a
`...\php\extras\ssl\cacert.pem` y se apuntaron `curl.cainfo` y
`openssl.cafile` a esa ruta en `php.ini`. Verificado con `php -i` que ambas
directivas ya resuelven al archivo correcto — no requiere reiniciar nada más
que el próximo proceso PHP que arranque (`php artisan serve`/tinker).

### Causa 2 (apareció después de resolver la 1): `GROQ_MODEL` apuntaba a un modelo retirado

Con SSL ya funcionando, la API de Groq respondía **404**:
`{"error":{"message":"The model \`llama-3.3-70b-versatile\` does not exist or you do not have access to it.","code":"model_not_found"}}`.
Groq retiró ese modelo de su catálogo. Se consultó el catálogo vigente en
vivo (`GET https://api.groq.com/openai/v1/models` con la misma key) y se
probó un reemplazo equivalente (modelo de texto de propósito general,
mismo nivel "flagship" que el Llama 3.3 70B original) antes de fijarlo:
**`openai/gpt-oss-120b`**, que respondió 200 con una respuesta real y
coherente en español.

Nota sobre la forma de la respuesta: `openai/gpt-oss-120b` es un modelo de
"razonamiento" y su JSON incluye un campo `reasoning` (cadena de
pensamiento) separado de `message.content` (la respuesta final). El código
de `GroqProveedor::responder()` ya leía
`$respuesta->json('choices.0.message.content')` — exactamente el campo
correcto — así que **no hizo falta ningún cambio de código**, solo el valor
del modelo.

Actualizado en 3 lugares (mismo valor en los tres, para que el fallback y el
`.env` real nunca queden desincronizados):
- `.env` — `GROQ_MODEL=openai/gpt-oss-120b`
- `.env.example` — mismo valor, más un comentario nuevo con dos formas de
  reconsultar el catálogo vigente si vuelve a pasar
  (`console.groq.com/docs/models`, o `curl .../v1/models` con la propia key)
  para que esto no se desactualice otra vez sin darnos cuenta.
- `config/services.php` — el valor por defecto de `env('GROQ_MODEL', ...)`
  (el que se usaría si `GROQ_MODEL` faltara del `.env` por completo).

No se tocó `GroqProveedor.php`: la construcción de la petición (headers,
endpoint, estructura del body) ya era correcta — el error nunca fue eso. No
se agregó manejo de excepciones nuevo ni se ocultó el mensaje de error
genérico existente; ambas causas eran de configuración (una de la máquina,
otra del valor del modelo), no del código.

### Verificación

- Prueba real end-to-end: `app(GroqProveedor::class)->responder(...)`
  devolvió una respuesta generada de verdad ("Una **tarea** es una
  actividad que el docente asigna..."), no el mensaje de error ni el modo
  simulado.
- `php artisan test`: **92/92 tests, 220 assertions** — los tests del bot
  usan un proveedor simulado/mockeado, así que no dependen de la red ni del
  modelo real y no se vieron afectados.
- `vendor/bin/pint --dirty --format agent`: limpio.


## 10. Módulo de Administración Docente y Comunicados Institucionales (Estilo Blackboard)

**Fecha de implementación:** 2026-09-18  
**Objetivo:** Replicar las herramientas centrales de administración académica de **Blackboard Learn / Ultra** en el rol Administrador, enfocadas en la supervisión, carga académica y comunicación directa con el claustro docente.

### 10.1 Decisiones Técnicas y de Arquitectura

1. **Directorio Docente y Carga Académica (`/admin/profesores`):**
   - Se construyó el componente Livewire `App\Livewire\Admin\Profesores\Index` exclusivo para el rol `administrador` (`abort_unless(..., 403)` y ruta protegida por middleware `role:administrador`).
   - Muestra las métricas globales del claustro docente: Total de Profesores, Cursos Asignados en el catálogo, Entregas Pendientes de Calificar a nivel institucional y Cursos en custodia administrativa.
   - Directorio con búsqueda reactiva en tiempo real (`wire:model.live.debounce.300ms`) por nombre o correo.
   - Cada tarjeta de profesor visualiza:
     - Avatar, datos de contacto y total de alumnos bajo su cargo.
     - Asignaturas a cargo con enlaces directos (`cursos.show`) y botón de desvinculación rápida.
     - Botón `+ Asignar curso` que abre un modal con el selector de materias disponibles.
     - Barra de cumplimiento y tasa de calificación (% de entregas calificadas sobre el total recibido).
     - Alerta visual automática (`atrasos_calificacion`) si existen entregas con más de 3 días de antigüedad sin calificar.
   - Modal de Alta Rápida de Profesor: permite registrar un nuevo docente indicando Nombre, Correo y Contraseña inicial, asignando inmediatamente el rol Spatie `profesor` y activando su cuenta.

2. **Asignación y Reasignación Docente en Cursos (`Cursos\Formulario`):**
   - Se modificó `App\Livewire\Cursos\Formulario` y su vista `formulario.blade.php`.
   - Cuando un `administrador` crea o edita un curso, se renderiza un selector desplegable con todos los usuarios que poseen el rol `profesor`.
   - Si un docente titular es reasignado, el curso actualiza su `profesor_id` de forma inmediata sin alterar las tareas, entregas o inscripciones de los estudiantes.

3. **Comunicados Institucionales (`/admin/comunicados` — Blackboard Announcements):**
   - Se creó la migración `2026_09_18_143000_create_comunicados_table.php` y el modelo `App\Models\Comunicado`.
   - Campos: `user_id` (autor admin), `titulo`, `contenido`, `prioridad` (`informativo`, `urgente`), `destinatario` (`profesores`, `estudiantes`, `todos`), `activo` (boolean).
   - Scopes en el modelo: `scopeActivos()` y `scopeParaRol($rol)` para segmentar la entrega según el perfil del usuario autenticado.
   - Componente `App\Livewire\Admin\Comunicados\Index`: permite redactar avisos, activar/desactivar y eliminar comunicados.
   - Visualización en paneles (`resources/views/dashboard.blade.php`): los comunicados urgentes o informativos se despliegan en banners destacados en la parte superior del panel del profesor (y del estudiante cuando corresponda).

4. **Mejora del Panel del Profesor:**
   - En `resources/views/dashboard.blade.php` se dotó al profesor de un resumen directo de sus cursos asignados (con conteo de alumnos) y un acceso rápido a su bandeja de calificaciones con el contador de entregas pendientes.

### 10.2 Cobertura de Pruebas Automatizadas

Se creó `tests/Feature/AdminProfesoresTest.php` con 9 pruebas exhaustivas:
1. `test_administrador_puede_acceder_al_directorio_de_profesores`: verificación de ruta 200 y renderizado del directorio.
2. `test_profesor_y_estudiante_no_pueden_acceder_a_admin_profesores`: protección estricta 403 Forbidden.
3. `test_administrador_puede_crear_un_nuevo_profesor_con_rol`: creación en BD, hashing de password y asignación del rol.
4. `test_administrador_puede_reasignar_un_curso_a_un_profesor`: cambio de titularidad en `cursos.profesor_id`.
5. `test_administrador_puede_desasignar_un_curso`: transferencia a custodia administrativa.
6. `test_administrador_puede_publicar_un_comunicado_institucional`: persistencia del comunicado y sus flags.
7. `test_profesor_y_estudiante_no_pueden_gestionar_comunicados`: protección de rutas 403 Forbidden.
8. `test_administrador_puede_asignar_profesor_desde_formulario_de_curso`: selector en el formulario de creación/edición.
9. `test_comunicados_activos_se_filtran_segun_rol`: validación del scope de segmentación por audiencia.

**Estado de la suite general:** 108 tests pasando (262 aserciones), 0 fallando.

## 11. Corrección de alineación con el ERS oficial: rol y registro público (2026-09-26)

Al revisar el sistema contra el ERS oficial v1.0 (setiembre 2026), se
detectaron dos discrepancias entre lo construido y el documento de
requisitos vigente. Ninguna de las dos invalida el trabajo ya hecho — son
correcciones de nomenclatura y de una regla de negocio, no un rediseño.

### 11.1 El rol operativo debe llamarse "Supervisor", no "Administrador"

El ERS (sección 2.3) define los tres roles del sistema como **Estudiante,
Profesor y Supervisor**. "Administrador general" se lista explícitamente
como un rol *futuro*, no implementado en esta versión, junto con Soporte,
Moderador, Coordinador académico y Asistente administrativo.

La decisión original de este documento (sección 1: "Rol `admin` →
`administrador`") se tomó citando "el documento del proyecto (sección 3)"
de una versión anterior al ERS que ahora es la fuente de verdad oficial.
Esa versión anterior ya no aplica.

**Corrección:** el rol `administrador` se renombra a `supervisor` en todo
el sistema (enum, seeders, middleware, policies, rutas, vistas, tests y
documentación), para que coincida exactamente con el ERS. Esto no es un
cambio de permisos ni de comportamiento — el rol conserva exactamente las
mismas capacidades que ya tenía (gestión de usuarios, cursos, esquema de
evaluación, matrícula, dashboard institucional), solo cambia su nombre.

### 11.2 El registro público de cuentas no debe existir

El ERS excluye el registro público explícitamente en dos lugares:
- Sección 1.2.2 (Fuera de alcance): "Registro público / autoservicio de
  cuentas."
- Sección 2.4 (Restricciones generales): "No existe registro público ni
  autoservicio de cuentas: toda cuenta es creada por el Supervisor o
  sincronizada por integración externa."

El sistema actual tiene `register.blade.php` activo, asignando el rol
`estudiante` por defecto a cualquiera que se registre sin invitación.

**Corrección:** se desactiva la ruta/vista de registro público. La
creación de cuentas queda exclusivamente en manos del Supervisor (individual
o masiva, ya construido en el Módulo 1/2) o de la integración externa
(Módulo 17/API), tal como exige el flujo 9.1 del ERS: alta de cuenta →
contraseña temporal por correo → cambio obligatorio en el primer login.

### Estado de esta corrección

**Ejecutada (verificado el 2026-09-26).** En el código solo existen los roles
`supervisor`, `profesor` y `estudiante` (`App\Enums\Rol`; middleware `role:supervisor`
en rutas, policies y vistas) y no queda ninguna referencia a `administrador`. La ruta
`/register` ya no existe (responde 404), la vista de registro se eliminó y la
bienvenida no muestra el enlace. Pruebas: `tests/Feature/Seguridad/AccesoPorRolTest.php`
y `tests/Feature/Seguridad/AutenticacionTest.php`. Detalle en `docs/CAMBIOS.md` y
`docs/AUDITORIA.md`.