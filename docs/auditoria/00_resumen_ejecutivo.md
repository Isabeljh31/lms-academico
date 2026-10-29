# 00. Resumen ejecutivo

Auditoría técnica de solo lectura de LearnUp (Laravel 13.30, Livewire 3.8, Volt 1.11) contra el ERS v2 (IEEE 830), sobre la rama `feature/auditoria-ers-contenido-por-semanas-y-mejoras-ux` (commit `93fc323` más 64 archivos sin commitear), realizada el 13 y 14 de octubre de 2026. Detalle en los archivos 01 a 17.

## 1. Veredicto

**¿Listo para producción? No, todavía.**

Pasa a **"Sí, con condiciones"** cuando se completen las tandas 0 y 1 del plan (archivo 17 § 5; unos 2 a 3 días de trabajo más las respuestas de Railway y Supabase):
1. Commitear y fusionar el trabajo pendiente (H-07).
2. Corregir las dos fugas de datos confirmadas en el panel del estudiante (H-01, H-24).
3. Resolver la configuración del proxy y del healthcheck (H-22, H-32) y proteger los seeders (H-33).
4. Decidir y aplicar las reglas de reapertura de cursos y cambio de rol (H-03, H-12).

Motivos (hechos):
- 7 defectos confirmados con pruebas temporales que la suite actual (482/482 en verde) no detecta.
- 10 hallazgos de severidad Alta, 5 de ellos de seguridad o exposición de datos (H-01, H-12, H-22, H-24, H-33).
- Ninguna integración externa real (Supabase, Cloudinary, SMTP, IA, proxy de Railway) está probada, y el código que las configura está sin commitear.

## 2. Avance

Criterio: por cada categoría del ERS, avance = (Cumple + 0,5 × Parcial) ÷ (total − No verificable). "No cumple" cuenta 0. Es avance **funcional contra el ERS**, no preparación para producción.

| Categoría | Cumple | Parcial | No cumple | No verificable | Avance |
|---|---|---|---|---|---|
| RF (108) | 93 | 12 | 0 | 3 | 94 % |
| RNF (21) | 12 | 5 | 0 | 4 | 85 % |
| Reglas de negocio (12) | 9 | 2 | 1 | 0 | 83 % |
| Casos de uso (25) | 22 | 3 | 0 | 0 | 94 % |
| **Global (promedio de las 4)** | | | | | **89 %** |

Avance por módulo (secciones 3.1 a 3.18 del ERS, mismo criterio sobre sus RF):

| Módulo | RF | Avance | Hallazgos Altos que lo afectan |
|---|---|---|---|
| 3.1 Autenticación | RF-01 a 09 | 100 % (2 trabajo futuro) | H-22, H-33 |
| 3.2 Usuarios | RF-10 a 17 | 100 % | H-12 |
| 3.3 Cursos y clonación | RF-18 a 25 | 100 % | H-03 |
| 3.4 Matrícula | RF-26 a 33 | 100 % | — |
| 3.5 Contenido | RF-34 a 39 | 92 % | H-01 |
| 3.6 Tareas y quizzes (incl. 3.6.1) | RF-40 a 53 | 96 % | H-04 |
| 3.7 Evaluación y calificaciones | RF-54 a 65 | 88 % | H-03 |
| 3.8 Progreso | RF-66 a 69 | 88 % | H-02 |
| 3.9 Asistencia | RF-70 a 73 | 100 % | — |
| 3.10 Grupos | RF-74 a 77 | 88 % | H-04, H-02 |
| 3.11 Calendario | RF-78 y 79 | 100 % | — |
| 3.12 Mensajería | RF-80 a 83 | 75 % | H-24 |
| 3.13 Notificaciones y comunicados | RF-84 a 87 | 88 % | — |
| 3.14 Reportes | RF-88 a 91 | 100 % | H-02 (avance) |
| 3.15 Asistente de IA | RF-92 a 94 | 100 % (1 no verificable) | — |
| 3.16 Auditoría | RF-95 a 99 | 90 % | H-04 |
| 3.17 Integración API | RF-100 a 106 | 100 % | — |
| 3.18 Configuración | RF-107 y 108 | 75 % | — |

`docs/AUDITORIA.md` declara 106 de 108 RF completos, 19 de 21 RNF y 0 hallazgos de seguridad abiertos; esta auditoría no confirma esas cifras (12b § 8, 16 § 2).

## 3. Los 10 hallazgos principales

| # | ID | Severidad | Hallazgo | Evidencia |
|---|---|---|---|---|
| 1 | H-24 | Alto | El panel del estudiante muestra anuncios de cursos en los que no está matriculado | `dashboard.blade.php:16-20`; prueba temporal |
| 2 | H-01 | Alto | El panel del estudiante muestra tareas programadas, en borrador, de cursos no publicados o dados de baja | `PanelInicio.php:47-51`; 2 pruebas temporales |
| 3 | H-04 | Alto | Una entrega grupal de un integrante nuevo borra las notas del grupo, sin bitácora | `Tareas/Detalle.php:114-170`; prueba temporal |
| 4 | H-03 | Alto | El Supervisor reabre cursos archivados y se pueden cambiar notas del periodo cerrado | `Cursos/Formulario.php:190-213`; prueba temporal |
| 5 | H-22 | Alto (condicionado) | Se confía en `X-Forwarded-Host` de cualquier origen: posible envenenamiento del enlace de restablecimiento | `bootstrap/app.php:24`; prueba temporal |
| 6 | H-32 | Alto (condicionado) | El healthcheck `/up` responde 301 en producción si llega por HTTP | `ForzarHttps.php`; prueba temporal |
| 7 | H-07 | Alto | Archivos privados, HTTPS, 2 migraciones, 7 pruebas y despliegue sin commitear | `git status` |
| 8 | H-12 | Alto | El Supervisor convierte a cualquiera en Supervisor con un `<select>`, sin confirmación | `Usuarios/Index.php:122-146` |
| 9 | H-33 | Alto (si se ejecuta) | Seeders sin guarda de entorno con `password123` y sin cambio obligatorio | `RoleAndUserSeeder.php:28,39,50` |
| 10 | H-02 | Alto | El avance no llega a 100 % con tareas grupales de otros grupos | `ProgresoAcademicoService.php:90`; prueba temporal |

Otros relevantes (Medio): login sin límite por IP (H-26), tiempo de quiz evadible recargando (H-13), asistente de IA sin límite (H-15), nota mínima retroactiva sobre cursos archivados (H-20), boleta PDF sin rubros (H-23), `php artisan serve` en producción (H-11), IP del proxy en rate limit y bitácora (H-08).

## 4. Lo que está bien (hechos)

- Autorización consistente: rutas por rol, policies en cada acción, `#[Locked]` en ids sensibles; 82 pruebas de acceso por rol y 11 de IDOR.
- Reglas críticas en el backend: pesos al 100 %, matrícula, baja lógica, bitácora con valores antes y después y motivo.
- Archivos privados con enlaces firmados de 10 minutos; la entrega local de archivos de Laravel agrega `CSP sandbox`.
- API de integración con API Key en hash, revocación, log y límite de solicitudes.
- Suite de 482 pruebas en verde con escenarios de punta a punta y pruebas de N+1 en 7 pantallas.
- Matriz de permisos del ERS (18 acciones): 18 cumplen.

## 5. Lo no verificado

| Tema | Por qué |
|---|---|
| Comportamiento del proxy de Railway (`X-Forwarded-Host`, `X-Forwarded-For`, healthcheck) | Sin acceso a Railway (H-08, H-22, H-32) |
| Estado de Supabase: migraciones aplicadas, cuentas existentes, respaldos, pooler | Sin acceso; no se ejecutaron migraciones ni consultas remotas |
| Subida real a Cloudinary privado y migración de archivos antiguos | Sin cuenta; nunca probado |
| Entrega de correos SMTP y funcionamiento del worker en producción | Sin acceso |
| Respuestas reales del proveedor de IA (Groq o Anthropic) | Solo modo simulado y `Http::fake` |
| Trigger de inmutabilidad en PostgreSQL | Las pruebas usan SQLite (H-05) |
| Tiempos de carga en producción (RNF-11) y disponibilidad (RNF-13) | Requieren el entorno real |
| Apariencia, responsividad real, teclado y contraste | No se abrió la aplicación en un navegador |
| Variables de entorno de producción (`APP_ENV`, `APP_DEBUG`, `APP_URL`, `CLOUDINARY_URL`) y volumen persistente | Sin acceso a Railway |
| Contenido de las 2 imágenes de WhatsApp en `DISEñO FIGMA/` | No se abrieron |

## 6. Cómo se hizo

- Lectura completa de `app/`, `routes/`, `config/` relevante, migraciones y las vistas clave; búsquedas por patrón en el resto (01 § 7, 17 § 7).
- Suite completa ejecutada una vez en SQLite en memoria (482/482, 1.468 aserciones, 106 s) y 7 pruebas temporales fuera del repositorio.
- `composer audit`, `npm audit`, `git` y `php artisan tinker` solo en modo lectura. No se modificó código ni datos; no se imprimieron secretos.
