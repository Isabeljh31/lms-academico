# 13. Seguridad (OWASP aplicado al código)

Método: lectura del código y la configuración, 2 pruebas temporales nuevas (H-22, H-24), `npm audit` y `composer audit` ejecutados el 14/10/2026 (solo lectura), y los resultados ya conocidos de la sesión anterior. No se hicieron pruebas de penetración contra un servidor desplegado. No se imprimió ningún secreto.

## 1. Resumen por categoría OWASP Top 10 (2021)

| Categoría | Estado | Hallazgos |
|---|---|---|
| A01 Control de acceso roto | Bueno en rutas y policies; fugas de datos por consultas sin filtro | H-01, H-24 (Alto), H-12, H-14, H-03 |
| A02 Fallas criptográficas | Bien: bcrypt, API Key con hash, cola cifrada, HTTPS y HSTS | H-30 (sesión sin cifrar con contraseña temporal) |
| A03 Inyección | Bien: Eloquent con parámetros, sin SQL crudo con datos del usuario, Excel sin fórmulas | — |
| A04 Diseño inseguro | Reglas que dependen del estado del componente o de la buena fe del Supervisor | H-13, H-19, H-03, H-15 |
| A05 Configuración insegura | Proxy de confianza para `Host`; sin cabeceras de seguridad; `.env.example` con depuración | H-22 (Alto, condicionado), H-27 |
| A06 Componentes vulnerables | 2 avisos de Composer en producción; 10 de npm solo en desarrollo | H-29 |
| A07 Fallas de identificación y autenticación | Bloqueo por cuenta, sin límite por IP; enumeración en recuperación; dos políticas de contraseña | H-26, H-17, H-18 |
| A08 Fallas de integridad | Flatpickr desde CDN sin SRI | H-28 |
| A09 Registro y monitoreo | Bitácora completa para lo exigido; IP del proxy; sin alertas | H-08, H-05 |
| A10 SSRF | No aplica: no hay peticiones a URL que escriba el usuario (las del asistente van a hosts fijos) | — |

## 2. Autenticación y bloqueo (RF-01, RF-05, RF-06)

| Control | Hecho | Evidencia |
|---|---|---|
| Login | Correo y contraseña; `Auth::validate` y luego `Auth::login`; regenera la sesión | `Forms/LoginForm.php:51-89`; Volt `login` regenera otra vez |
| Bloqueo | 3 intentos, 15 minutos, configurable (`LOGIN_MAX_INTENTOS`, `LOGIN_MINUTOS_BLOQUEO`); campos no asignables en masa | `config/auth.php:125-128`; `User.php:72-83`; `S/AutenticacionTest::test_los_campos_de_bloqueo_no_se_asignan_en_masa` |
| Cuenta desactivada | Mensaje solo tras validar la contraseña; sesiones abiertas se cierran en la siguiente petición | `LoginForm.php:79-83`; `Middleware/CuentaActiva` |
| Primer ingreso | Redirige todo a `primer-login` salvo `logout` y `livewire/*` | `Middleware/PrimerLogin` (archivo 06 § 2) |
| Recuperación | Broker de Laravel: token de 60 min, 60 s entre solicitudes por correo; desbloquea la cuenta | `config/auth.php:99-100`; Volt `reset-password` |
| Registro público | No existe | `Auth/RegistrationTest` (3) |

Problemas:
- **H-26 (Medio). Hecho:** no hay límite de intentos por IP en el login (no hay `RateLimiter` ni `throttle` en la ruta `login`; búsqueda en `app/`, `routes/` y `bootstrap/`). Consecuencias:
  - cualquiera que conozca un correo puede bloquear esa cuenta 15 minutos con 3 intentos, y repetirlo indefinidamente (denegación de servicio dirigida, por ejemplo contra un profesor en semana de notas);
  - un atacante puede probar una contraseña común contra muchas cuentas sin ser frenado (*password spraying*); el bloqueo por cuenta no lo detecta.
- H-17: la recuperación responde "We can't find a user with that email address." si el correo no existe (enumeración de cuentas), aunque el login evita revelar cuentas a propósito.
- H-18: después del primer ingreso, el restablecimiento y el perfil aceptan cualquier contraseña de 8 caracteres.
- Hecho: el cambio de contraseña (perfil y primer ingreso) no cierra las otras sesiones del usuario.

## 3. Contraseñas y secretos guardados (RNF-01, A02)

| Elemento | Hecho | Evidencia |
|---|---|---|
| Contraseñas | Cast `hashed`; bcrypt con 12 rondas | `User.php`; `.env.example:15` (`BCRYPT_ROUNDS=12`); `S/AutenticacionTest::test_las_contrasenas_se_guardan_con_hash_bcrypt` |
| API Keys | `LU_` + 40 aleatorios; se guarda el hash bcrypt y un prefijo de 8 | `IntegracionExterna::generarApiKey`; `S/ApiIntegracionTest::test_la_api_key_se_guarda_solo_como_hash` |
| Contraseña temporal en la cola | Trabajo cifrado (`ShouldBeEncrypted`) | `BienvenidaMail.php:23`; `UsuariosTest::test_el_correo_de_bienvenida_se_encola_cifrado_sin_la_contrasena_en_texto_plano` |
| Contraseña temporal en pantalla | Mensaje *flash* de sesión (`Usuarios\Index`, `Admin\Profesores`) y propiedad pública de Livewire (`ImportarCSV::$creados`) | 07a § 3.3, 07b § 9.1 |
| Sesiones | Driver `database`, `SESSION_ENCRYPT=false` | `config/session.php:21,50`; `.env.example:53-55` |

- **H-30 (Bajo). Hecho:** con sesiones en base de datos sin cifrar, la contraseña temporal que se muestra por *flash* queda legible (serializada) en `sessions.payload` hasta la siguiente petición del Supervisor. Cualquiera con lectura de la base (o de un respaldo) la ve. Activar `SESSION_ENCRYPT=true` lo evita.
- Hecho: la API Key en texto plano viaja en el *snapshot* de Livewire hasta que el Supervisor pulsa "ocultar" (07b § 5.3).

## 4. Sesiones y cookies

| Parámetro | Valor | Evidencia |
|---|---|---|
| Duración | 120 min, no expira al cerrar el navegador | `config/session.php:35-37` |
| `secure` | `true` si `APP_ENV=production` | `config/session.php:173` |
| `http_only` | `true` | línea 186 |
| `same_site` | `lax` | línea 203 |
| Regeneración | En login y logout | `LoginForm.php:88`, `Actions/Logout` |
| "Recuérdame" | Disponible (`remember_token`), sin límite propio | Volt `login` |

Sin problemas relevantes salvo H-30.

## 5. HTTPS, HSTS y proxy (RNF-02)

| Control | Hecho | Evidencia |
|---|---|---|
| Redirección a HTTPS | Solo en producción; 301 en GET y 308 en otros métodos | `Middleware/ForzarHttps`; `S/HttpsTest::test_en_produccion_una_solicitud_http_se_redirige_a_https` |
| HSTS | 1 año con `includeSubDomains`, sin `preload` | `S/HttpsTest::test_en_produccion_detras_del_proxy_https_responde_con_hsts` |
| URL generadas | `URL::forceScheme('https')` en producción | `AppServiceProvider.php:67-69` |
| Proxies | `at: '*'` con `Proto`, `Host` y `Port`; `X-Forwarded-For` no se usa | `bootstrap/app.php:24`; `S/HttpsTest::test_la_ip_reenviada_por_el_cliente_no_reemplaza_la_ip_real` |

- **H-22 (Alto, condicionado):** confiar en `X-Forwarded-Host` de cualquier origen permite que un cliente cambie el dominio de las URL generadas (verificado con prueba temporal, archivo 09 § 2). Riesgo de envenenamiento del enlace de restablecimiento de contraseña. Depende de si el proxy de Railway reemplaza ese encabezado (no verificado). Arreglo: `trustProxies` con `HEADER_X_FORWARDED_PROTO` solamente, o `URL::forceRootUrl(config('app.url'))` en producción, o `TrustHosts`.
- **H-08:** la IP real del cliente no llega a la aplicación detrás del proxy: el rate limit de la API es global y la bitácora y los logs guardan la IP del proxy.

## 6. Control de acceso e IDOR (RNF-03, A01)

| Control | Hecho | Evidencia |
|---|---|---|
| Rutas por rol | `auth`, `verified` y `role:` (Spatie) en las rutas propias que lo requieren | archivo 06 § 1 |
| Policies en cada acción | `Gate::authorize` en `mount` y en cada método que cambia datos | archivos 07a y 07b |
| Ids fijados por el servidor | `#[Locked]` en ids de modales y sesiones | 07a § 1 |
| Pertenencia | `scopeBindings` en tareas y quizzes; `findOrFail` sobre relaciones del curso | 06 § 1.2 |
| Pruebas | `S/AccesoPorRolTest` (82 casos), `S/IdorTest` (11) | archivo 11 |

Problemas (fugas por consultas, no por rutas):
- **H-24 (Alto, confirmado):** el panel del estudiante muestra anuncios de cursos ajenos (archivo 10 § 3).
- **H-01 (Alto, confirmado):** el panel muestra títulos de tareas programadas, en borrador o de cursos dados de baja (07a § 9.1).
- H-12: el Supervisor puede convertir a cualquier usuario en Supervisor, sin confirmación (06, 10 § 7).
- H-14: el titular de un curso clonado puede ser cualquier usuario.
- Hecho: `ArchivoController::foto` sirve la foto de cualquier usuario a cualquier autenticado (decisión documentada en el código).

## 7. Asignación masiva y validación en el servidor (A03, A04)

- Hecho: los 26 modelos declaran `$fillable`; no hay `$guarded = []` ni `Model::unguard` (búsqueda en `app/`). `User` excluye `bloqueado_hasta` e `intentos_fallidos`.
- Hecho: ningún componente asigna `request()->all()` a un modelo; todos validan sus propiedades con `$this->validate([...])` antes de guardar (archivos 07a y 07b). Prueba: `S/ValidacionEntradasTest::test_los_modelos_sensibles_no_aceptan_asignacion_masiva_de_campos_protegidos`.
- Hecho: búsquedas con `like` usan parámetros enlazados; `S/ValidacionEntradasTest::test_una_busqueda_con_sql_no_altera_ni_filtra_datos`.
- Hecho: la exportación a Excel escribe todo texto como `inlineStr`, nunca como fórmula (sin inyección de fórmulas, 08 § 7).
- Debilidades de diseño: el tiempo límite del quiz (H-13) y el bloqueo de estructura (H-19) dependen del estado del componente.

## 8. CSRF

- Hecho: el grupo `web` incluye la verificación de token de Laravel; Livewire envía el token en cada `livewire/update`; el cierre de sesión es `POST` con `@csrf` (`layouts/app.blade.php:145-146`). Prueba: `S/ValidacionEntradasTest::test_el_grupo_web_verifica_el_token_csrf`.
- Hecho: la API (`routes/api.php`) no usa sesión ni cookies; se autentica con `Bearer`, así que no necesita CSRF.

## 9. XSS

- Hecho: la única salida sin escapar en las vistas es `{!! $rutas[$nombre] ?? $rutas['libro'] !!}` en `components/icono.blade.php:65`, que imprime SVG fijos de un arreglo interno (no datos del usuario).
- Hecho: los datos que pasan a JavaScript usan `@js(...)` (serialización segura de Laravel), por ejemplo los eventos del calendario (`evento-calendario/index.blade.php:363-367`). No hay `x-html` ni `innerHTML` en `resources/`.
- Pruebas: `S/ValidacionEntradasTest::test_escapa_el_html_de_un_anuncio_del_curso_para_el_estudiante`, `::test_escapa_el_contenido_de_un_comunicado_institucional`, `::test_las_vistas_solo_imprimen_html_sin_escapar_en_puntos_revisados`.
- Hecho: no hay `Content-Security-Policy` en las páginas de la aplicación (ver § 14); una XSS futura no tendría una segunda barrera.

## 10. Subida de archivos

| Control | Hecho | Evidencia |
|---|---|---|
| Lista blanca de extensiones | 18 extensiones (`documentos`) y 5 de imágenes; nunca scripts | `config/archivos.php` |
| Tamaño | 10 MB (`ARCHIVOS_MAX_KB=10240`) en regla y en el servicio | `SubidorArchivos.php:49-59,109-111`; `S/SubidaArchivosTest::test_rechaza_archivos_de_mas_de_diez_megas_con_un_mensaje_claro` |
| Nombre | UUID generado por el servidor | `SubidorArchivos.php:183`; `S/SubidaArchivosTest::test_guarda_el_archivo_con_un_nombre_generado_por_el_servidor` |
| Ejecutables | Rechazados | `S/SubidaArchivosTest::test_rechaza_archivos_ejecutables_en_una_entrega` |
| Tipo real | **No se valida** el contenido (solo la extensión del cliente) | `SubidorArchivos.php:103` |
| Entrega desde el disco local | Ruta firmada `storage/{path}` de Laravel con `Content-Security-Policy: default-src 'none'; ...; sandbox` y sin caché | `vendor/laravel/framework/src/Illuminate/Filesystem/ServeFile.php` |

- Hecho (mitigación): aunque un `.pdf` con contenido HTML se sirviera como `text/html`, la cabecera `sandbox` de Laravel impide ejecutar scripts en el dominio de la aplicación. En Cloudinary, el archivo se descarga desde `api.cloudinary.com` (otro dominio).
- Hecho: la importación de usuarios limita a 2 MB y rechaza `<!DOCTYPE` en los XML del `.xlsx` (XXE) (08 § 7).

## 11. Archivos privados (RNF-06)

- Hecho: toda subida nueva se guarda privada (`privado://`); `ArchivoController` y `RecursoLeccionController` autorizan y redirigen a un enlace firmado de 10 minutos. Pruebas: `S/ArchivosPrivadosTest` (8).
- Hecho: `storage:link` se ejecuta en cada arranque (`railway.json:8`): los archivos antiguos del disco `public` siguen accesibles sin autenticación hasta ejecutar `archivos:privatizar --aplicar`. En la base local, 0 de 71 entregas tienen `privado://` (son URLs de ejemplo).
- Hecho: el código de archivos privados está **sin commitear** (H-07) y nunca se probó contra una cuenta real de Cloudinary (archivo 11 § 5).

## 12. API de integración y dependencias

### 12.1 API Keys, rate limit y log
| Control | Hecho | Evidencia |
|---|---|---|
| Autenticación | `Bearer`; búsqueda por prefijo y `Hash::check`; 401 si falla | `Middleware/ValidarApiKey` |
| Revocar y regenerar | Sí, con bitácora | `PanelAdmin/Integraciones` |
| Rate limit | 60/min por `$request->ip()` antes de validar la clave | `AppServiceProvider.php:73-75` |
| Log | Toda solicitud, sin `password` ni `api_key` en el payload guardado | `ValidarApiKey`; RF-106 |
| JSON | 415 si no es JSON | `MatriculaController.php:31-33` |

- H-08: detrás del proxy, el límite de 60/min es compartido por todas las instituciones (y por un atacante): un atacante puede agotarlo y bloquear la sincronización legítima.
- Hecho: el log de la API guarda nombre y correo de estudiantes sin plazo de retención (04b § 3).

### 12.2 Dependencias (ejecutado el 14/10/2026)
| Herramienta | Resultado | ¿Producción o desarrollo? |
|---|---|---|
| `npm audit` | 10 vulnerabilidades: 2 críticas (`concurrently` vía `shell-quote`), 6 altas (`braces`, `chokidar`, `fast-glob`, `micromatch`, `source-map-js`, `tailwindcss`), 2 moderadas (`postcss-nested`, `postcss-selector-parser`) | **Todas de desarrollo o de compilación.** `npm audit --omit=dev` = 0. Afectan al equipo de desarrollo y al paso de *build* de Railway (`npm ci && npm run build`), no al JS que recibe el navegador (FullCalendar y Chart.js sin avisos). |
| `composer audit` | `league/commonmark` 2.10.0: 2 avisos (1 alto, DoS cuadrático en tablas GFM; 1 medio, evasión de `DisallowedRawHtml`) | **Producción** (lo requiere `laravel/framework`). La aplicación no procesa Markdown del usuario (búsqueda de `markdown` en `app/` y `resources/`: sin resultados); solo lo usan las plantillas de correo de Laravel con textos propios. Exposición baja. |
| Paquetes abandonados | Ninguno | `composer audit` |

- **H-29 (Bajo):** actualizar `league/commonmark` (versión corregida posterior a 2.10.1) y las herramientas de desarrollo (`concurrently`, `tailwindcss` 3 → corrección disponible solo en Tailwind 4, que es un cambio mayor).
- Contradicción: `docs/AUDITORIA.md` § 1 afirma "`composer audit` / `npm audit`: 0 vulnerabilidades / 0 vulnerabilidades". Los avisos son posteriores (publicados el 30/09/2026 en Composer) o no se ejecutó `npm audit` completo (archivo 16).

## 13. Cola cifrada y correo

- Hecho: solo `BienvenidaMail` va cifrado (lleva la contraseña). `MatriculadoEnCurso` no lleva secretos.
- Hecho: si `APP_KEY` rota, los trabajos cifrados pendientes fallan (09 § 4).
- Hecho: el correo de bienvenida lleva la contraseña en claro y no caduca (09 § 6). Es lo que pide RF-04.

## 14. Cabeceras de seguridad

- **H-27 (Bajo). Hecho:** las páginas de la aplicación no envían `X-Frame-Options` ni `Content-Security-Policy` (`frame-ancestors`), `X-Content-Type-Options` ni `Referrer-Policy` (búsqueda en `app/`, `bootstrap/` y `config/`: solo HSTS en `ForzarHttps`). La aplicación se puede cargar dentro de un `iframe` de otro sitio (*clickjacking*), por ejemplo sobre el botón de cambio de rol o de revocar una API Key. No verificado si Railway agrega alguna de estas cabeceras.
- **H-28 (Bajo). Hecho:** Flatpickr se carga desde `cdnjs.cloudflare.com` sin `integrity` (SRI) (`resources/js/app.js:24-38`).
- Hecho: las fuentes se cargan de `fonts.bunny.net` y los avatares de reemplazo de `ui-avatars.com`, que recibe el nombre del usuario (privacidad, archivo 10 § 2).

## 15. Bitácora y registro (RF-95 a RF-99, RNF-05)

| Aspecto | Hecho | Evidencia |
|---|---|---|
| Qué se registra | Cambios de nota (rubro, tarea, quiz), cuentas, roles, matrículas, bajas, esquema, cursos, configuración, API Keys, comunicados | 19 tipos (`BitacoraAuditoria::TIPOS`) |
| Inmutabilidad | Eventos del modelo y trigger en PostgreSQL | 04b § 2; H-05 |
| IP | `request()->ip()`, que detrás del proxy es la del proxy | H-08 |
| Log de aplicación | Login fallido (correo e IP), errores de IA, de subida y de correo, sin contraseñas | `S/AutenticacionTest::test_el_cambio_inicial_no_escribe_la_contrasena_en_el_log` |
| Monitoreo | No hay APM ni alertas; logs del programador y del worker en archivos del contenedor | archivo 15 |

- Hecho: pérdidas de nota por H-04 no quedan en la bitácora.
- Hecho: `.env.example` trae `LOG_LEVEL=debug` y `APP_DEBUG=true` (archivo 15).

## 16. Asistente de IA

- Hecho: el contexto solo incluye datos del propio usuario y del curso que puede ver; el curso no se cambia desde el navegador (`#[Locked]`); el *prompt* ordena ignorar instrucciones inyectadas. Pruebas: `S/AsistenteIaTest` (6).
- H-15: sin límite por usuario; cada mensaje ocupa un worker hasta 20 s y consume cuota.
- Hecho: los datos del estudiante (nombre del curso, tareas, notas propias, eventos) se envían a un proveedor externo (Groq o Anthropic). El ERS lo prevé (§ 2.5); no hay aviso de privacidad al usuario en la interfaz (búsqueda de "privacidad" en `resources/views`: sin resultados).

## 17. Secretos y repositorio (resultados de la sesión anterior)

- `.env` está en `.gitignore` y no se versionó; no hay secretos en el historial de git (búsqueda con `git log -G` de patrones de claves, archivo 01 § 3).
- `.mcp.json` está versionado y no contiene secretos.
- `.claude/` (37 archivos) no contiene secretos.
- `phpunit.xml` no contiene secretos reales.
- No se imprimieron valores de `.env` en esta auditoría.

## 18. Hallazgos nuevos de este archivo

| ID | Severidad | Resumen |
|---|---|---|
| H-26 | Medio | Login sin límite por IP: bloqueo dirigido de cuentas ajenas y *password spraying* |
| H-27 | Bajo | Sin cabeceras `X-Frame-Options`/CSP, `X-Content-Type-Options` ni `Referrer-Policy` (clickjacking) |
| H-28 | Bajo | Flatpickr desde CDN sin SRI |
| H-29 | Bajo | `league/commonmark` con 2 avisos (producción); 10 avisos de npm en dependencias de desarrollo |
| H-30 | Bajo | Contraseña temporal en sesión de base de datos sin cifrar |
