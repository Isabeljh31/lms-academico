# Progreso de la auditoría (solo lectura)

Inicio: 13/10/2026. Retomada y terminada: 14/10/2026. ERS: `ERS_LearnUp_v2.docx` (raíz del repositorio).

| Archivo | Estado |
|---|---|
| 00_resumen_ejecutivo.md | Terminado |
| 01_metodologia_e_inventario.md | Terminado (§ 7 actualizado el 14/10) |
| 02_avance_historico.md | Terminado |
| 03_arquitectura.md | Terminado |
| 04a_base_de_datos_tablas.md / 04b_base_de_datos_analisis.md | Terminado |
| 05_modelos.md | Terminado |
| 06_rutas_y_permisos.md | Terminado |
| 07a_livewire.md / 07b_livewire.md | Terminado |
| 08_servicios_y_logica.md | Terminado |
| 09_http_consola_notificaciones.md | Terminado |
| 10_interfaz_y_vistas.md | Terminado |
| 11_pruebas.md | Terminado |
| 12a_trazabilidad_ers.md / 12b_trazabilidad_ers.md | Terminado |
| 13_seguridad.md | Terminado |
| 14_calidad_y_rendimiento.md | Terminado |
| 15_despliegue_y_operacion.md | Terminado |
| 16_documentacion_y_repositorio.md | Terminado |
| 17_hallazgos_y_plan.md | Terminado |

## Registro de hallazgos (numeración fija)

Los archivos 01 a 06 citaban de forma explícita solo H-03, H-08 y H-12. El resto de H-01 a H-12 se reconstruyó el 14/10/2026 a partir de los hallazgos ya documentados en 02, 04b, 05 y 06, respetando esos tres números. Desde H-13 se numeró en el orden de aparición en los archivos 07 en adelante. Detalle, arreglo y esfuerzo en el archivo 17.

| ID | Severidad | Hallazgo | Detalle |
|---|---|---|---|
| H-01 | Alto | Panel de inicio del estudiante muestra tareas programadas y de cursos dados de baja (confirmado) | 07a § 9.1 |
| H-02 | Alto | El avance no llega a 100 % con tareas grupales de otro grupo (confirmado) | 08 § 11 |
| H-03 | Alto | El Supervisor reabre cursos archivados (confirmado) | 04b § 5, 07a § 7.3 |
| H-04 | Alto | La entrega grupal de un integrante nuevo borra las notas del grupo (confirmado) | 07b § 8.1 |
| H-05 | Medio | Trigger de la bitácora sin pruebas, sin TRUNCATE y con FK anonimizables | 04b § 2 |
| H-06 | Bajo | El commit `3f413c0` modificó 11 migraciones existentes (viola "solo aditivas") | 02 § 2, 04b § 1 |
| H-07 | Alto | Trabajo de la auditoría final sin commitear | 02 § 4, 16 § 5 |
| H-08 | Medio | IP del proxy en rate limit, bitácora y logs | 03 § 5, 13 § 5 |
| H-09 | Bajo | Preguntas de quiz sin ID estable | 04b § 3 |
| H-10 | Medio | `Tarea::estadoPara` usa `updated_at` como momento de entrega | 05 § 1 |
| H-11 | Medio | `php artisan serve` como servidor de producción | 03 § 5, 15 § 1 |
| H-12 | Alto | Cambio de rol a `supervisor` sin restricción ni confirmación | 06 § 4, 07b § 9.2 |
| H-13 | Medio | Tiempo límite del quiz se reinicia al recargar | 07b § 6.5 |
| H-14 | Medio | Clonar curso acepta cualquier usuario como titular | 07a § 7.7 |
| H-15 | Medio | Asistente de IA sin límite de solicitudes por usuario | 07a § 6.3 |
| H-16 | Bajo | Libro de calificaciones: nota de tarea sin notificación y con motivo fijo | 07a § 7.6 |
| H-17 | Bajo | Mensajes de autenticación en inglés; recuperación revela si el correo existe | 07b § 10 |
| H-18 | Bajo | Dos políticas de contraseña | 07b § 10 |
| H-19 | Bajo | Bloqueo de estructura del quiz evitable desde el navegador | 07b § 6.3 |
| H-20 | Medio | Cambiar la nota mínima altera la condición de cursos archivados | 07b § 5.2 |
| H-21 | Medio | El worker de colas no aplica la zona horaria | 08 § 1 |
| H-22 | Alto (condicionado) | Confianza en X-Forwarded-Host de cualquier origen (confirmado en local) | 09 § 2 |
| H-23 | Medio | Boleta PDF sin rubros ni nota final | 09 § 1 |
| H-24 | Alto | Panel del estudiante muestra anuncios de cursos ajenos (confirmado) | 10 § 3 |
| H-25 | Bajo | Título de página fijo | 10 § 2 |
| H-26 | Medio | Login sin límite por IP | 13 § 2 |
| H-27 | Bajo | Sin cabeceras de seguridad (clickjacking) | 13 § 14 |
| H-28 | Bajo | Flatpickr desde CDN sin SRI | 13 § 14 |
| H-29 | Bajo | Dependencias con avisos (commonmark en producción; npm solo desarrollo) | 13 § 12 |
| H-30 | Bajo | Contraseña temporal en sesión sin cifrar | 13 § 3 |
| H-31 | Bajo | N+1 fuera de `RendimientoTest`; 3 copias de la calificación | 14 § 1, § 4 |
| H-32 | Alto (condicionado) | `ForzarHttps` redirige `/up` con 301 en producción (confirmado en local) | 15 § 3 |
| H-33 | Alto (si se ejecuta) | Seeders sin guarda de entorno con `password123` | 15 § 8 |
| H-34 | Bajo | Documentación con cifras desactualizadas y comentarios obsoletos | 16 § 2-3 |

Totales: 0 Crítico, 10 Alto, 11 Medio, 13 Bajo.

## Pruebas temporales (fuera del repositorio; "pasa" = el defecto existe)

- Sesión anterior (`99279c8c-...`/scratchpad/VerificacionAuditoriaTest.php): 5/5 pasan (H-01 ×2, H-02, H-03, H-04).
- Esta sesión (`2c0ab3fe-...`/scratchpad): `HostReenviadoTest` (H-22), `AnuncioAjenoTest` (H-24), `HealthcheckHttpTest` (H-32): 3/3 pasan.
- Suite del repositorio: 482/482, 1.468 aserciones, 106 s (SQLite en memoria, 13/10/2026). No se repitió.
