# 16. Documentación y repositorio

Alcance: `docs/` (6 archivos previos a esta auditoría, 1.984 líneas), documentos de la raíz (`ARQUITECTURA.md`, `README.md`, `INFORME_FINAL.md`, `AGENTS.md`, `CLAUDE.md`), comentarios del código que hacen afirmaciones verificables, `.gitignore`, ramas y estructura del repositorio. Todas las comprobaciones son de solo lectura.

## 1. Inventario de documentación

| Documento | Líneas | Estado en git | Propósito | Vigencia |
|---|---|---|---|---|
| `docs/AUDITORIA.md` | 598 | Modificado sin commitear | Auditoría interna del equipo contra el ERS v2 | Parcialmente desactualizado (§ 2) |
| `docs/TRAZABILIDAD.md` | 435 | Modificado sin commitear | Matriz RF/RNF/CU con estados | Desactualizado en estados (archivo 12b § 8) |
| `docs/CAMBIOS.md` | 596 | Modificado sin commitear | Registro de cambios por ronda | Histórico |
| `docs/PROGRESO.md` | 86 | Modificado sin commitear | Bitácora de trabajo para retomar | Histórico |
| `docs/PANTALLAS.md` | 122 | Versionado | Revisión de pantallas por rol | Vigente en lo general |
| `docs/BRIEFING_PROYECTO.md` | 147 | **Sin seguimiento** (`??`) | Resumen del proyecto y checklist de producción | Afirmaciones desactualizadas (§ 2) |
| `ARQUITECTURA.md` | 1.563 | Modificado sin commitear | Arquitectura y registro por módulo | § 0 parcialmente desactualizado (03 § 5) |
| `INFORME_FINAL.md` | 297 | Versionado | Informe del 15-16/09/2026 | **Obsoleto**: describe registro público, rol `administrador` y Claude como proveedor por defecto, que ya no existen o cambiaron |
| `README.md` | 58 | Versionado | Texto por defecto de Laravel ("About Laravel") | **No documenta el proyecto** |
| `AGENTS.md`, `CLAUDE.md` | 47, 186 | Versionados | Instrucciones para agentes de IA (Laravel Boost) | Herramientas, no documentación del producto |
| `docs/auditoria/` | esta auditoría | Sin seguimiento | — | — |

## 2. Contradicciones entre la documentación y el código o el ERS

| Afirmación | Dónde | Realidad verificada | Evidencia |
|---|---|---|---|
| "RF completos 106, parciales 0" | `docs/AUDITORIA.md:20-22`, § 7 | 93 Cumple, 12 Parcial, 3 No verificable | 12b § 1 |
| "RNF completos 19 de 21" | `docs/AUDITORIA.md:19`; `docs/TRAZABILIDAD.md:382` | 12 Cumple, 5 Parcial, 4 No verificable | 12b § 2 |
| "Matriz de permisos 17 de 17" | `docs/AUDITORIA.md:310,581`; `docs/BRIEFING_PROYECTO.md:54`; `docs/TRAZABILIDAD.md:383` | El ERS v2 tiene **18** acciones (las 18 cumplen) | 06 § 4 |
| "Reglas de negocio 10 de 10" | `docs/AUDITORIA.md` § 12.1 y § 12.2 (10 filas) | El ERS v2 tiene **12** reglas; la 8 no se cumple (H-03) | 12b § 3 |
| "Pasos de los flujos 28 de 28" | `docs/AUDITORIA.md` § 12.1 | Los 4 flujos del ERS v2 suman 25 pasos | 12b § 5 |
| "Hallazgos de seguridad abiertos: 0" | `docs/AUDITORIA.md:25` | 34 hallazgos abiertos en esta auditoría (H-01 a H-34), 10 de severidad Alta | archivo 17 |
| "`composer audit` / `npm audit`: 0 / 0" | `docs/AUDITORIA.md:26`; `docs/CAMBIOS.md:181` | `composer audit`: 2 avisos (`league/commonmark`); `npm audit`: 10 (solo desarrollo) | 13 § 12.2 |
| "Cumple el ERS en todo lo que depende del código" | `docs/BRIEFING_PROYECTO.md:63` | 7 defectos de código confirmados con pruebas temporales | archivo 11 § 1.1 |
| "PHPUnit 12.5, 475 pruebas" | `ARQUITECTURA.md:25,128` | 482 casos | 11 § 1 |
| "Límite por IP" de la API sin advertir el proxy | `ARQUITECTURA.md` § 0.4 | IP del proxy (H-08) | 03 § 5 |
| Regla 8 "Cumple: curso archivado bloquea..." | `docs/AUDITORIA.md:325` | El Supervisor reabre el curso | H-03 |
| Regla 9 "Cumple" | `docs/AUDITORIA.md:326` | El panel muestra títulos de contenido programado | H-01 |
| RF-108 "zona horaria aplicada en web, comandos programados y seeders" | `docs/TRAZABILIDAD.md:215` | Correcto, pero omite que el worker de colas no la aplica | H-21 |
| RNF-06 "Completo" | `docs/TRAZABILIDAD.md:226` | El mismo texto dice "Falta validar con la cuenta real de Cloudinary"; además está sin commitear | 12b § 2 |
| "Emojis en la interfaz: 0" | `docs/AUDITORIA.md:27` | Cierto (búsqueda de rangos Unicode de emoji) | 10 § 9 |
| "482 pruebas (482 pasan)" | `docs/AUDITORIA.md:18` | Cierto | 11 § 1 |

## 3. Comentarios del código desactualizados

| Comentario | Dónde | Realidad | Evidencia |
|---|---|---|---|
| "Sin CLOUDINARY_URL ... cae automáticamente al disco local 'public'" | `config/services.php:38-40` | Usa el disco **privado** `local` (`storage/app/private`) | `SubidorArchivos.php:181-186` |
| "modelos open-source (Llama 3.3 70B)" | `app/Services/Bot/GroqProveedor.php:16-18` | El modelo por defecto es `openai/gpt-oss-120b` | `config/services.php:67`; `.env.example` (comentario: "llama-3.3-70b-versatile dejó de existir en 2026-09") |
| "matrícula del curso (el Profesor solo consulta)" | `routes/web.php:72` | El Profesor también da de baja en sus cursos (RF-29) | 06 § 5 |
| "cursos cuyo titular es el usuario" | `app/Livewire/Docente/PorCalificar/Index.php:17-20` | Incluye profesores adicionales (`dictadosPor`) | 07a § 8.1 |
| "ERS RF-23: cursos donde es profesor adicional" sobre `enlaceFoto()` | `app/Models/User.php:111-112` | El docblock quedó encima del método equivocado | 05 § 1 |
| Comentarios con `[cite: 1]` | `app/Models/PropuestaBot.php:24,49`; `ConversacionBot` | Artefactos de una herramienta de IA | 05 § 1 |
| "Modo oscuro ... nada la activa hoy" | `tailwind.config.js:13-17` | Cierto, pero las vistas tienen 1.281 clases `dark:` | 10 § 8 |
| `ClaudeBotService`: "Proveedor de pago; el proveedor por defecto durante desarrollo es Groq" | `ClaudeBotService.php:18-20` | Correcto | `config/services.php:52` |

## 4. `.gitignore` y archivos versionados

- Hecho: `.gitignore` excluye `.env`, `.env.backup`, `.env.production`, `/vendor`, `/node_modules`, `/public/build`, `/public/storage`, logs y configuraciones de IDE. `database/.gitignore` excluye `*.sqlite*`; `storage/app/private/.gitignore` y `storage/logs/.gitignore` excluyen su contenido (`git check-ignore -v`).
- Hecho: 446 archivos versionados (`git ls-files`).
- Versionados que no deberían estarlo o merecen revisión:
  - `ERS_LearnUp_v2.docx:Zone.Identifier` (metadato de Windows con un carácter especial en el nombre), agregado en `60a231b`.
  - `.mcp.json` (configuración de MCP, sin secretos) y `boost.json`: herramientas de desarrollo.
  - `.claude/` (37 archivos de *skills* de agentes, sin secretos).
  - `DISEñO FIGMA/` (27 archivos, prototipo React; ver 10 § 12), con su propio `CLAUDE.md` y `AGENTS.md`.
  - `INFORME_FINAL.md` obsoleto.
- Faltan en `.gitignore` (opinión): `*:Zone.Identifier` y variantes, `storage/logs/scheduler.log`/`queue.log` ya cubiertos por `storage/logs/.gitignore`.
- Hecho: `.env.example` contiene el host y el usuario del pooler de Supabase (sin contraseña) (15 § 4.1).

## 5. Ramas y estado del trabajo (de 02, verificado de nuevo el 14/10/2026)

| Rama | Situación |
|---|---|
| `feature/auditoria-ers-contenido-por-semanas-y-mejoras-ux` (actual) | Sincronizada con `origin`; el PR no está fusionado en `main` |
| `main` local | 18 commits por detrás de `origin/main` |
| `feature/rediseno-estudiante-y-modulos` local | 9 commits por delante de su remota |
| Remotas fusionadas | `docente-visibilidad-y-estadisticas-quiz`, `rediseno-learnup-y-grupos`, `mejora-lms` siguen existiendo en `origin` |

- **H-07 (Alto):** 50 archivos modificados o eliminados y 14 nuevos sin commitear (`git status`): archivos privados (RNF-06), HTTPS (`ForzarHttps`), `RecursoLeccion`, 2 migraciones, 7 archivos de prueba, `railway.json` y `Procfile`. Existen solo en el disco local.
- Hecho: el commit `93fc323` agrupa 229 archivos (+20.236 / −7.087 líneas): difícil de revisar y revertir por partes (02 § 2).
- Hecho: no hay plantilla de PR, `CODEOWNERS`, CI (no existe `.github/workflows`; búsqueda en la raíz) ni protección de rama verificable desde el repositorio.

## 6. Estructura del repositorio

| Carpeta o archivo | Contenido | Observación |
|---|---|---|
| `app/` | 124 archivos | Estructura Laravel estándar; sin `Jobs`, `Observers`, `Rules`, `Actions` |
| `bootstrap/`, `config/`, `routes/`, `database/`, `resources/`, `tests/` | Estándar | — |
| `docs/` | 6 documentos del equipo + `auditoria/` | — |
| `lang/` | Solo `es.json` (9 claves) | Faltan traducciones (H-17) |
| `public/` | `index.php`, recursos; `build/` ignorado | — |
| `DISEñO FIGMA/` | Prototipo | Nombre con "ñ" y espacio |
| Raíz | `railway.json`, `Procfile`, `docker-compose.yml`, `ERS_LearnUp_v2.docx` y su `Zone.Identifier`, documentos | El ERS (binario) está versionado: bien para trazabilidad, pero los cambios no se pueden comparar en git |

## 7. Recomendaciones de documentación (opinión)

1. Reemplazar `README.md` por una guía real: requisitos, instalación local, variables de entorno (incluidas las de § 4.2 del archivo 15), cómo correr las pruebas y cómo desplegar.
2. Marcar `INFORME_FINAL.md` como histórico o moverlo a `docs/historico/`.
3. Corregir los conteos de `docs/AUDITORIA.md`, `docs/TRAZABILIDAD.md` y `docs/BRIEFING_PROYECTO.md` con los resultados de esta auditoría (18 acciones, 12 reglas, 25 pasos, estados reales).
4. Corregir los 6 comentarios de § 3.
5. Commitear `docs/BRIEFING_PROYECTO.md` o eliminarlo; hoy está sin seguimiento.

## 8. Hallazgo de este archivo

| ID | Severidad | Resumen |
|---|---|---|
| H-34 | Bajo | Documentación del equipo con cifras de cumplimiento y seguridad desactualizadas o incorrectas; `README.md` por defecto; `INFORME_FINAL.md` obsoleto; 6 comentarios del código desactualizados |
