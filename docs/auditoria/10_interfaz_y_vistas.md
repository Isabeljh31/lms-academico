# 10. Interfaz y vistas

Alcance: `resources/views` (83 vistas), `resources/js/app.js`, `resources/css/app.css`, `tailwind.config.js`, `lang/es.json` y la carpeta `DISEñO FIGMA/`. Se leyeron completas `layouts/app`, `dashboard`, `components/selector-fecha-hora`, `components/gestion/campo`, `pages/auth/login`, `pages/auth/reset-password`, `reportes/boleta-pdf` y `welcome/navigation`; del resto, fragmentos y búsquedas por patrón (archivo 01 § 7). No se abrió la aplicación en un navegador: lo visual y la responsividad real **no están verificados** en pantalla.

## 1. Estructura de vistas

| Carpeta | Contenido | Archivos |
|---|---|---|
| `layouts/` | `app` (163 líneas, menú lateral por rol, campana, perfil, widget del asistente) y `guest` (29) | 2 |
| `components/` | Breeze (`dropdown`, `modal`, `text-input`, `primary-button`, `nav-link`...) y propios (`icono`, `badge-estado`, `selector-fecha-hora`) | 16 |
| `components/gestion/` | Sistema de componentes de las pantallas de gestión: `alertas`, `boton`, `campo`, `control`, `encabezado`, `indicador`, `modal`, `tarjeta`, `vacio` | 9 |
| `livewire/` | Una vista por componente (40), 2 parciales y 7 Volt | 49 |
| `emails/` | `bienvenida` | 1 |
| `reportes/` | `boleta-pdf` | 1 |
| raíz | `dashboard`, `profile` | 2 |

`welcome.blade.php` fue eliminada (sin commitear): `/` redirige al login o al panel. Queda `livewire/welcome/navigation.blade.php` sin uso, con un enlace condicional a `register` (archivo 06 § 1.6).

## 2. Layout principal (`layouts/app.blade.php`)

| Aspecto | Hecho | Línea |
|---|---|---|
| Menú por rol | Arreglo `$menu` con `visible` por rol: Estudiante 6 entradas, Profesor 7, Supervisor 12 | 37-54 |
| Paleta por rol | Estudiante: azul corporativo (Figma); Profesor y Supervisor: menta, arena y terracota | 5-35 |
| Navegación | `wire:navigate` en todos los enlaces del menú | 91 |
| Encabezado | Campana de notificaciones (Livewire) y menú de perfil con cierre de sesión por `POST` con `@csrf` | 124-152 |
| Avatar | Foto privada vía `enlaceFoto()`; si no hay, `https://ui-avatars.com/api/?name=<nombre>` | 129-135 |
| Asistente | `<livewire:bot-sugerencias.widget />` para todos los roles | 161 |
| Fuentes | `fonts.bunny.net` (Figtree) | 66-67 |
| Título | `<title>{{ config('app.name', 'LearnUp') }}</title>` | 64 |

Problemas:
- Hecho: el `<title>` es fijo (`config('app.name')`, línea 64; igual en `layouts/guest.blade.php:8`). Las llamadas a `->title("...")` de 33 componentes no tienen efecto: todas las pestañas del navegador dicen "LearnUp". Afecta la usabilidad con varias pestañas y la accesibilidad (WCAG 2.4.2, título de página).
- Hecho: el botón que abre el menú en móvil (línea 112) no tiene `aria-label` ni texto; un lector de pantalla lo anuncia como "botón" sin nombre.
- Hecho: el avatar de reemplazo envía el nombre completo del usuario a `ui-avatars.com` (tercero) en cada carga de página de quien no tiene foto.
- Hecho: el rol se toma con `getRoleNames()->first()` (línea 2); un usuario sin rol ve el menú de estudiante y un distintivo vacío.

## 3. Panel de inicio (`dashboard.blade.php`)

- Estudiante: hasta 2 comunicados y el componente `Estudiante\PanelInicio`. Profesor y Supervisor: `Reportes\PanelGestion`. Sin rol: estado vacío.
- **H-24 (Alto). Hecho confirmado por prueba temporal** (`AnuncioAjenoTest`, scratchpad de esta sesión; pasa = el defecto existe): el bloque de comunicados del estudiante usa `Comunicado::activos()->paraRol('estudiante')` (líneas 16-20) sin `whereNull('curso_id')` ni filtro por matrícula. Como los anuncios de curso (RF-81) se guardan en la misma tabla con `destinatario = 'estudiantes'`, **todos los estudiantes ven en su panel los 2 últimos anuncios de cualquier curso**, incluidos cursos en los que no están matriculados. `PanelGestion` sí filtra `whereNull('curso_id')` (`Reportes/PanelGestion.php:101`).
- Hecho: la consulta se hace dentro de la vista (lógica en Blade).
- H-01 (archivo 07a § 9.1) también se ve en este panel.

## 4. Pantallas por rol (rutas del menú y principales)

| Rol | Pantallas |
|---|---|
| Estudiante | Panel (`PanelInicio`), Cursos, Detalle del curso (contenido por semanas, anuncios, tareas, quizzes, compañeros, grupo), Tarea (entrega), Quiz (resolver), Boleta, Mi asistencia, Mensajería, Calendario, Reportes (mi progreso + PDF), Propuestas del asistente, Perfil |
| Profesor | Panel (`PanelGestion`), Cursos, Detalle del curso (semanas, Prácticas de campo, anuncios), Lecciones de una semana, Tareas (crear, editar, revisar), Quizzes (crear, editar, estadísticas, banco de preguntas), Libro de calificaciones, Asistencia y sesiones, Grupos, Matrícula (bajas), Por calificar, Mensajería, Calendario, Reportes, Resumen del asistente, Perfil |
| Supervisor | Panel, Cursos (crear, editar, clonar, esquema, matrícula), Usuarios (alta, importar, roles, activar), Profesores, Comunicados, Dashboard institucional, Riesgo de abandono, Integración API, Configuración, Auditoría, Calendario, Reportes, vistas de solo lectura del libro, asistencia, grupos, tareas y quizzes, Perfil |

Prueba de carga de todas las pantallas por rol: `tests/Feature/RevisionPantallasTest.php` (cada ruta responde 200 con un texto esperado). No prueba la apariencia.

## 5. Contenido por semanas y Prácticas de campo (cómo está hoy)

Hechos (código de `Cursos\Show`, 07a § 7.7, y vista `livewire/cursos/show.blade.php`, 668 líneas, más el parcial `cursos/partials/elementos-seccion.blade.php`, 87):
- Cada semana es una fila de `modulos` con `semana = N`; Prácticas de campo es una fila con `tipo_seccion = practicas`, `semana = null` y `orden = 0` (archivo 05).
- El curso tiene `semanas_duracion` (16 por defecto) y `totalSemanas()` = máximo entre la duración y la última semana con contenido.
- **Vista del profesor**: lista las semanas 1..N, con o sin contenido; cada semana muestra el rango de fechas (lunes a domingo desde `fecha_inicio`) y la cantidad de sesiones de clase que caen en ese rango (líneas 219-221). Una semana vacía se "prepara" (crea el módulo) al editarla. Prácticas de campo aparece como bloque aparte solo si existe, y el profesor ve si es visible para los estudiantes (línea 150).
- **Vista del estudiante**: Prácticas de campo arriba solo si tiene contenido visible; luego las semanas 1..N. Una semana sin nada visible muestra "Aún no disponible" y **no revela el tema** (líneas 448-468). La semana en curso se marca como "Semana actual" y se abre sola.
- El tema de la semana se edita como "Tema" opcional ("Semana 3: La derivada", línea 335); `Modulo::tema()` quita prefijos heredados "Unidad N:".
- Los datos de la base local siguen con 27 de 28 módulos titulados "Unidad N: ..." y 37 de 58 lecciones con descripciones autogeneradas que mencionan "unidad" (archivo 04b § 7). En pantalla el prefijo se oculta, pero las descripciones no.
- Hecho: el rango de fechas usa `translatedFormat('d M')` (líneas 220, 465, 490) y no el formato de fecha configurado (RF-108). Con `APP_LOCALE=es`, los meses salen en español; el orden día-mes es fijo.

## 6. Componentes de gestión y formularios

- `x-gestion.campo` (usado 226 veces en vistas de Livewire): etiqueta con `for`, marca de requerido, ayuda y error visible (RNF-07).
  - Hecho: el error no se asocia al control con `aria-describedby` ni `aria-invalid`; el lector de pantalla no lo anuncia al enfocar.
- `x-selector-fecha-hora`: Flatpickr en español con formato visible "28 de septiembre de 2026, 06:00 PM" y valor `Y-m-d\TH:i`.
  - Hecho: conviven 11 campos `datetime-local` nativos en 6 vistas (`admin/comunicados`, `asistencia`, `cursos/show`, `lecciones`, `quizzes/formulario`, `tareas/formulario`) con este selector (3 usos). La experiencia de fecha es distinta según la pantalla.
  - Hecho: el selector muestra la hora en formato de 12 horas (`time_24hr: false`, `h:i K`), mientras el resto del sistema muestra 24 horas (`H:i`, `ConfiguracionPlataforma::formatoFechaHora`).
- Estados vacíos (RNF-08): componente `x-gestion.vacio` y mensajes "Aún no...". Se usa en el panel sin rol y en listados (búsqueda por patrón; no verificado pantalla por pantalla).

## 7. Confirmaciones de acciones destructivas (RNF-09)

Hecho (búsqueda de `wire:click` con acciones de borrar, revocar o quitar, y de `wire:confirm` en las 2 líneas anteriores o siguientes): 19 acciones destructivas tienen `wire:confirm` (eliminar tarea, quiz, lección, recurso, anuncio, grupo, sesión, pregunta del banco, comunicado, evento; vaciar semana; desasignar profesor; regenerar y revocar API Key; quitar rubro o integrante; desactivar asistencia). Sin confirmación:
- `toggleActivo` de comunicados (reversible).
- `rechazar` una propuesta del asistente (irreversible: la policy exige estado pendiente).
- **Cambio de rol de un usuario**: un `<select>` con `wire:change="actualizarRol(...)"` (`livewire/usuarios/index.blade.php:106-107`) aplica el cambio al instante, sin confirmación. Un error de selección convierte a un estudiante en supervisor (H-12).
- Quitar opciones dentro del formulario de quiz o banco (cambios locales antes de guardar; aceptable).
- Desactivar cuentas usa un modal propio (`mostrarModalDesactivar`), equivalente a una confirmación.

## 8. JavaScript, CSS y librerías de frontend

| Elemento | Hecho | Evidencia |
|---|---|---|
| `resources/js/app.js` (42 líneas) | Expone `window.FullCalendar` (core, daygrid, timegrid, list, interaction, locale es) y `window.Chart`; define `window.cargarFlatpickr()` | líneas 1-42 |
| FullCalendar 6.1.x | Solo en `livewire/evento-calendario/index.blade.php` (590 líneas) | búsqueda |
| Chart.js 4.5.x | En `reportes/dashboard-estudiante` y `reportes/dashboard-profesor` | búsqueda de `new Chart` |
| Flatpickr 4.6.13 | Se carga en tiempo de ejecución desde `cdnjs.cloudflare.com` **sin integridad (SRI)** y sin `crossorigin` | `app.js:24-38` |
| Bundle | Vite 8 con `laravel-vite-plugin` 3; FullCalendar y Chart.js se cargan en **todas** las páginas (un solo bundle) | `package.json`, `app.js` |
| `resources/css/app.css` (166 líneas) | Tailwind y estilos de FullCalendar; respeta `prefers-reduced-motion` en el resaltado del calendario | líneas 150-166 |
| `tailwind.config.js` (96 líneas) | Tailwind 3 con `@tailwindcss/forms`; `darkMode: 'class'`; paletas `menta`, `arena`, `terracota`, `cafe`, `exito`, `azul`, `celeste`, `imperial`, `cielo`, `lavanda`, `verde`, `coral`, `fondo`, `texto`; `gray` = `stone` | archivo completo |

Problemas:
- Hecho: el modo oscuro está configurado "por clase" y el comentario dice "nada la activa hoy" (`tailwind.config.js:13-17`); no hay ningún código que agregue la clase `dark` (búsqueda en `resources/`). Las vistas tienen 1.281 clases `dark:` que nunca se aplican: peso y mantenimiento sin efecto.
- Hecho: cargar FullCalendar y Chart.js en todas las páginas aumenta el JS de cada pantalla (el bundle compilado pesa 0,54 MB en `public/build`, archivo 01). Opinión: dividir por página ayudaría a RNF-11 (3 s).
- Hecho: Flatpickr desde un CDN externo sin SRI: si el CDN sirve un archivo alterado, se ejecuta en la sesión del usuario (ver archivo 13). Además, sin conexión al CDN el selector no funciona.
- Hecho: el JS se compila en el despliegue (`npm ci && npm run build`, `railway.json:5`). Las vulnerabilidades de `npm audit` se analizan en el archivo 13 § 12.

## 9. Accesibilidad (por búsqueda de patrones)

| Indicador | Valor | Observación |
|---|---|---|
| `lang` del documento | `app()->getLocale()` (`es` con `.env.example`) | Correcto |
| Imágenes | 8 `<img>` y 8 `alt=` | Todas tienen `alt` |
| `aria-label` | 19 usos | Faltan en botones solo con ícono (por ejemplo, el menú móvil) |
| `sr-only` | 12 archivos | Usado en selects de tablas (rol, filtros) |
| Roles ARIA | `alert` 8, `dialog` 3, `tablist`/`tab` 2, `progressbar` 1, `switch` 1, `status` 2 | Los modales de `x-gestion.modal` usan `role="dialog"` y `aria-modal` |
| Errores de formulario | Texto visible, sin `aria-describedby` | § 6 |
| Títulos de página | Siempre "LearnUp" | § 2 |
| Color | Estados con color e ícono (rojo bajo la nota mínima, insignias) | No se midió el contraste (no verificado) |
| Teclado | No verificado (requiere navegador) | — |
| Emojis en la interfaz | Ninguno en `resources/views` ni `app/` (búsqueda de rangos Unicode de emoji) | — |

## 10. Responsividad (RNF-20)

- Hecho: el layout cambia de menú lateral fijo (≥ `lg`) a menú deslizable con capa oscura (< `lg`) (`layouts/app.blade.php:72-105`).
- Hecho: prefijos responsivos en las vistas: `sm:` 189, `md:` 2, `lg:` 88, `xl:` 0.
- Hecho: 20 tablas y 19 contenedores `overflow-x-auto`: casi todas las tablas tienen desplazamiento horizontal en móvil. El libro de calificaciones con muchos rubros dependerá de ese desplazamiento.
- Hecho: `x-selector-fecha-hora` desactiva el selector nativo del móvil (`disableMobile: true`), así que en el teléfono se usa Flatpickr.
- No verificado: la apariencia real en un teléfono. `tests/Feature/RevisionPantallasTest.php` solo comprueba el HTML.

## 11. Textos e idioma

- Hecho: la interfaz propia está en español. Los textos que vienen del framework no: `auth.failed`, `passwords.*`, plantilla de correos y validaciones sin mensaje personalizado (solo existe `lang/es.json` con 9 claves de paginación; no hay `lang/es/validation.php`). Los mensajes de validación sin texto propio en el componente salen en inglés: verificado con `php artisan tinker` (solo lectura), `__("validation.email")` en `es` devuelve "The correo field must be a valid email address.". Ver H-17.
- Hecho: el nombre de la institución configurado (RF-108) no aparece en el layout; el encabezado muestra `config('app.name')` (línea 83).

## 12. Carpeta `DISEñO FIGMA/`

- Hecho: 27 archivos versionados (`git ls-files`), agregados en el commit `b690942` ("docs: referencia de diseño Figma").
- Contenido: un prototipo exportado de Figma Make: React 19, Vite 8, Tailwind 4, TypeScript (`package.json`, `pnpm-lock.yaml`, `src/App.tsx`, `src/index.css`, `src/main.tsx`), configuración de la herramienta (`.figma/make/*`, `.mise.toml`), sus propios `AGENTS.md` y `CLAUDE.md`, y 4 imágenes (`color-palette.png`, `screenshot1.png` y 2 capturas `WhatsApp_Image_2026-09-16...jpeg`).
- No es parte de la aplicación: Tailwind 4 frente a Tailwind 3 del proyecto; no se compila ni se despliega.
- Problemas:
  - Hecho: el nombre de la carpeta tiene una "ñ" y un espacio (`DISEñO FIGMA`), lo que complica comandos en algunas terminales y herramientas.
  - Hecho: incluye un `CLAUDE.md` y un `AGENTS.md` propios que las herramientas de IA pueden confundir con las instrucciones del proyecto.
  - No verificado: el contenido de las 2 imágenes de WhatsApp (pueden contener datos personales; no se abrieron).

## 13. Hallazgos nuevos de este archivo

| ID | Severidad | Resumen |
|---|---|---|
| H-24 | Alto | El panel del estudiante muestra anuncios de cursos en los que no está matriculado |
| H-25 | Bajo | El título de página es siempre "LearnUp": los `->title()` de los componentes no se usan |
