# 12b. Trazabilidad con el ERS v2 (parte 2)

Continúa 12a (mismos criterios de estado y abreviaturas). Cubre RF-66 a RF-108, los 21 RNF, las 12 reglas de negocio, los 25 casos de uso, los 4 flujos, la matriz de permisos, las fases, el conteo frente a `docs/AUDITORIA.md` y los defectos del propio ERS.

## 1. Requisitos funcionales RF-66 a RF-108

### 3.8 Progreso académico
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-66 | Porcentaje de avance automático | Parcial | `ProgresoAcademicoService::avance`; `F/CasosDeUsoTest::test_marcar_una_leccion_como_completada_suma_al_avance` | **H-02**: cuenta tareas grupales de otros grupos; el avance no llega a 100 % (prueba temporal) |
| RF-67 | El Profesor define cuándo se completa una lección o módulo | Cumple | `Modulo::CONDICIONES`, `Leccion::CONDICIONES`, `estadoModulos`; `F/CasosDeUsoTest::test_el_profesor_define_la_condicion_de_completitud_del_modulo_y_la_leccion` | — |
| RF-68 | Progreso por curso para el estudiante | Cumple | `Reportes/Dashboard`, `PanelInicio`; `ReportesTest::test_estudiante_ve_su_dashboard_de_progreso` | Hereda H-02 |
| RF-69 | Progreso promedio para el Profesor | Cumple | `ProgresoAcademicoService::resumenCurso` (`progreso_promedio`); `ReportesTest::test_profesor_ve_promedio_y_tasa_de_entrega_de_sus_cursos` | Hereda H-02; N+1 por estudiante |

### 3.9 Asistencia
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-70 | Asistencia por sesión con 4 estados | Cumple | `Asistencia/Index::guardarAsistencia`; `F/CasosDeUsoTest::test_el_profesor_pasa_lista_y_el_estudiante_ve_su_porcentaje` | Sin bitácora de cambios |
| RF-71 | Porcentaje acumulado automático | Cumple | `CalculadoraAsistencia::porcentaje`; `Unit/.../CalculadoraAsistenciaTest` (4) | — |
| RF-72 | El estudiante consulta su historial | Cumple | `Asistencia/MiHistorial`; `F/CasosDeUsoTest::test_el_profesor_pasa_lista_y_el_estudiante_ve_su_porcentaje` | — |
| RF-73 | Omitir asistencia por curso | Cumple | `Asistencia/Index::alternarUsoAsistencia`; `F/CasosDeUsoTest::test_el_profesor_omite_y_reactiva_la_asistencia_y_el_supervisor_solo_consulta` | — |

### 3.10 Grupos
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-74 | Crear, renombrar y eliminar grupos | Cumple | `Grupos/Index`; `F/CasosDeUsoTest::test_el_profesor_crea_renombra_y_asigna_grupos_solo_con_matriculados` | Eliminar un grupo vuelve "general" su tarea asignada (07b § 1.1) |
| RF-75 | Agregar y mover estudiantes | Cumple | `Grupos/Index::asignar`; `GruposTest::test_asignar_a_otro_grupo_del_mismo_curso_lo_quita_del_anterior` | — |
| RF-76 | Tarea grupal asignada a un grupo específico | Parcial | `tareas.grupo_asignado_id`, `TareaPolicy::ver`; `GruposTest::test_al_entregar_una_tarea_grupal_se_crea_una_entrega_para_cada_integrante` | **H-04** (entrega de un integrante nuevo borra notas) y **H-02** (avance) |
| RF-77 | Consultar trabajos entregados por grupo | Cumple | `tareas/detalle.blade.php:147` (entregas agrupadas por `grupo_id`); `GruposTest::test_calificar_una_entrega_grupal_propaga_la_nota_a_todo_el_grupo` | — |

### 3.11 Calendario
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-78 | Calendario con tareas, quizzes, evaluaciones, sesiones y fechas límite | Cumple | `EventoCalendario/Index::eventos`; `F/CasosDeUsoTest::test_el_calendario_del_estudiante_incluye_tareas_quizzes_sesiones_y_evaluaciones` | Sesiones solo de cursos con asistencia; rubros sin semana no aparecen |
| RF-79 | Vista propia de estudiante y profesor | Cumple | `ColoresCursos::consultaCursosDe`; `CalendarioPorRolTest` (6) | — |

### 3.12 Mensajería
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-80 | Mensajes privados Estudiante-Profesor y entre estudiantes del curso | Cumple | `Mensajeria/Bandeja::contactosDe`; `F/CasosDeUsoPrincipalesTest::test_cu08_conversacion_entre_estudiante_y_profesor`, `S/IdorTest::test_un_estudiante_no_puede_escribir_a_profesores_de_otros_cursos` | — |
| RF-81 | Avisos del Profesor visibles para todos los estudiantes de su curso | Parcial | `Cursos/Show::publicarAnuncio`, envío "a todos" en mensajería; `BlackboardDocenteAlumnoTest::test_docente_puede_publicar_y_eliminar_anuncio_del_curso` | **H-24**: el anuncio también se muestra en el panel de estudiantes de **otros** cursos (prueba temporal) |
| RF-82 | Bandeja con conversaciones y adjuntos | Parcial | `Mensajeria/Bandeja` (recibidos, enviados, responder; adjunto privado ≤ 10 MB) | No agrupa los mensajes en conversaciones o hilos: son dos listas planas |
| RF-83 | Notificar mensaje nuevo | Cumple | `NuevoMensaje`; `F/CasosDeUsoPrincipalesTest::test_cu08_conversacion_entre_estudiante_y_profesor` | — |

### 3.13 Notificaciones y comunicados
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-84 | Notificar nueva tarea, quiz, calificación, mensaje, contenido, cambio de fecha, comunicado y matrícula | Parcial | 12 notificaciones (archivo 09 § 5); `CalendarioNotificacionesTest`, `F/CasosDeUsoTest::test_el_contenido_programado_notifica_al_publicarse_una_sola_vez` | **H-16**: calificar una tarea desde el libro no notifica |
| RF-85 | Contador de no leídas en todas las pantallas | Cumple | `Notificaciones/Campanita` en `layouts/app.blade.php:124` | Sin prueba de la clase |
| RF-86 | Marcar como leídas | Cumple | `Campanita::marcarLeida`, `::marcarTodasLeidas` | Sin prueba |
| RF-87 | Comunicados del Supervisor a estudiantes, profesores o ambos, con fecha y hora | Cumple | `Admin/Comunicados/Index`, comando `comunicados:publicar`; `F/CasosDeUsoTest::test_un_comunicado_programado_se_publica_y_notifica_a_su_hora` | Sin `withoutOverlapping` (09 § 3) |

### 3.14 Reportes y dashboards
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-88 | Dashboard del Profesor | Cumple | `Reportes/PanelGestion::datosProfesor` | Sin prueba de los indicadores |
| RF-89 | Reportes por curso del Profesor | Cumple | `ProgresoAcademicoService::resumenCurso`; `F/CasosDeUsoTest::test_el_bajo_rendimiento_no_marca_a_quien_va_bien_aunque_falte_contenido_futuro` | Hereda H-02 en avance |
| RF-90 | Dashboard del Supervisor | Cumple | `Reportes/PanelGestion::datosSupervisor` | "Tasa de finalización" definida por el código (07b § 7.2) |
| RF-91 | Reportes administrativos | Cumple | `PanelAdmin/Metricas`; `PanelAdminTest::test_las_metricas_reflejan_los_cursos_con_atraso` | El abandono incluye bloqueos locales de API |

### 3.15 Asistente de IA
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-92 | Analizar calendario y pendientes y sugerir un plan | Cumple | `ConstruyeContextoIA::calendarioDelEstudiante`, `PropuestaBotService`; `S/AsistenteIaTest::test_el_contexto_incluye_el_calendario_propio_del_estudiante`, `PropuestaBotTest` (10) | — |
| RF-93 | Explicar, generar preguntas, resumir o sugerir repaso | No verificable | Instrucciones en `ConstruyeContextoIA.php:30-38` | Depende de la respuesta del modelo; solo se probó el modo simulado y un `Http::fake` |
| RF-94 | Basarse solo en datos del propio curso del estudiante | Cumple | `ConstruyeContextoIA::contexto`; `S/AsistenteIaTest::test_no_usa_como_contexto_un_curso_en_el_que_el_estudiante_no_esta_matriculado`, `::test_las_reglas_se_mantienen_ante_instrucciones_inyectadas` | Widget disponible para todos los roles; sin límite (H-15) |

### 3.16 Auditoría
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-95 | Bitácora de toda edición de nota (tarea, quiz, rubro) con motivo | Parcial | `BitacoraAuditoria::registrarCambioNota` en 5 puntos; `F/CasosDeUsoTest::test_recalificar_una_entrega_queda_en_la_auditoria` | **H-04** borra notas sin registro; motivo fijo en el libro (H-16) |
| RF-96 | Bitácora de acciones administrativas del Supervisor | Cumple | `registrarAccionAdmin` en cuentas, matrículas, bajas y esquema; `F/FlujosPrincipalesTest::test_el_profesor_da_de_baja_en_su_propio_curso_con_historial_y_auditoria` | — |
| RF-97 | Solo el Supervisor ve la bitácora | Cumple | Ruta `role:supervisor` y `mount`; `S/AuditoriaTest::test_profesor_y_estudiante_no_pueden_consultar_la_bitacora` | — |
| RF-98 | Filtros por fechas, usuario, tipo y curso | Cumple | `PanelAdmin/Auditoria::consulta`; `S/AuditoriaTest::test_el_supervisor_filtra_por_tipo_usuario_curso_y_fechas` | — |
| RF-99 | Registros inmutables desde la interfaz | Cumple | Eventos `updating`/`deleting` del modelo; trigger en PostgreSQL; `S/AuditoriaTest::test_un_registro_de_auditoria_no_se_puede_modificar`, `::test_un_registro_de_auditoria_no_se_puede_eliminar` | El trigger no tiene prueba (H-05) |

### 3.17 Integración externa (API)
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-100 | Endpoint REST de matrícula | Cumple | `POST api/v1/matriculas`; `S/ApiIntegracionTest` (14) | — |
| RF-101 | API Key única por institución | Cumple | `IntegracionExterna::generarApiKey` (hash bcrypt); `S/ApiIntegracionTest::test_la_api_key_se_guarda_solo_como_hash` | Rate limit compartido detrás del proxy (H-08) |
| RF-102 | Rechazar y registrar claves inválidas o ausentes | Cumple | `ValidarApiKey`; `S/ApiIntegracionTest::test_rechaza_con_401_y_registra_una_solicitud_sin_api_key` | — |
| RF-103 | Payload mínimo: usuario, curso, estado | Cumple | `MatriculaController.php:35-48`; `S/ApiIntegracionTest::test_un_payload_incompleto_devuelve_422_y_queda_registrado` | `rol` solo admite `estudiante` |
| RF-104 | Crear la cuenta si no existe | Cumple | `MatriculaController.php:70-79`; `S/ApiIntegracionTest::test_una_solicitud_valida_crea_la_cuenta_la_matricula_y_notifica` | — |
| RF-105 | Sistema externo como fuente de verdad | Cumple | `GestorMatriculas::sincronizarDesdeApi`; `S/ApiIntegracionTest::test_el_estado_inactivo_del_sistema_externo_da_de_baja_sin_borrar` | Una baja por API desactiva también matrículas manuales (08 § 8) |
| RF-106 | Log de todas las solicitudes, con errores | Cumple | `logs_integracion_api`, `ValidarApiKey`; `S/ApiIntegracionTest::test_un_payload_incompleto_devuelve_422_y_queda_registrado` | Guarda datos personales sin plazo de retención (04b § 3) |

### 3.18 Configuración general
| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-107 | Nota mínima aprobatoria general (0-20) | Cumple | `PanelAdmin/Configuracion::guardar`; `F/FlujosPrincipalesTest::test_la_nota_minima_configurable_decide_la_aprobacion` | Afecta retroactivamente a cursos archivados (H-20) |
| RF-108 | Nombre de la institución, zona horaria y formato de fecha | Parcial | `ConfiguracionPlataforma`; `ConfiguracionRegionalTest` (4) | El nombre de la institución solo se usa en el Excel; la zona no se aplica en el worker de colas (H-21) |

### Total de los 108 RF

| Estado | RF-01 a RF-65 (12a) | RF-66 a RF-108 | Total |
|---|---|---|---|
| Cumple | 58 | 35 | **93** |
| Parcial | 5 | 7 | **12** |
| No cumple | 0 | 0 | **0** |
| No verificable | 2 | 1 | **3** |

RF en Parcial: RF-38, RF-45, RF-55, RF-62, RF-65, RF-66, RF-76, RF-81, RF-82, RF-84, RF-95, RF-108. No verificables: RF-08, RF-09 (trabajo futuro) y RF-93 (depende del modelo de IA).

## 2. Requisitos no funcionales (21)

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RNF-01 | Hash seguro de contraseñas | Cumple | Cast `hashed` en `User`; `S/AutenticacionTest::test_las_contrasenas_se_guardan_con_hash_bcrypt` | — |
| RNF-02 | HTTPS | Cumple | `ForzarHttps` (301/308 y HSTS), `URL::forceScheme`, cookie segura; `S/HttpsTest` (4) | Confianza en `X-Forwarded-Host` de cualquier origen (H-22) |
| RNF-03 | Acceso validado por rol | Cumple | Rutas con `role:`, policies; `S/AccesoPorRolTest` (82), `S/IdorTest` (11) | Fugas de contenido por consulta, no por rol: H-01, H-24 |
| RNF-04 | Revocar y regenerar API Keys | Cumple | `PanelAdmin/Integraciones`; `S/ApiIntegracionTest::test_una_api_key_revocada_deja_de_funcionar`, `::test_al_regenerar_la_key_la_anterior_se_invalida_y_la_nueva_funciona` | — |
| RNF-05 | Log de logins fallidos y acciones sensibles | Cumple | `LoginForm.php:64-68`, bitácora; `S/AutenticacionTest::test_registra_en_el_log_los_intentos_fallidos` | La IP registrada sería la del proxy (H-08) |
| RNF-06 | Supabase Storage con buckets privados y enlaces temporales | Parcial | `SubidorArchivos` (`privado://`), `ArchivoController`; `S/ArchivosPrivadosTest` (8) | Usa Cloudinary o disco local, no Supabase; Cloudinary real nunca probado; sin commitear (H-07); archivos existentes sin migrar |
| RNF-07 | Errores claros y accionables | Parcial | `x-gestion.campo`, mensajes propios | Mensajes del framework en inglés (H-17) |
| RNF-08 | Estados vacíos informativos | Cumple | `x-gestion.vacio` | No revisado pantalla por pantalla |
| RNF-09 | Confirmar acciones destructivas | Parcial | 19 `wire:confirm` (10 § 7) | El cambio de rol se aplica sin confirmación; rechazar propuesta tampoco |
| RNF-10 | Usable sin capacitación | No verificable | — | Requiere pruebas con usuarios |
| RNF-11 | Carga ≤ 3 s | No verificable | `RendimientoTest` (sin N+1 en 7 páginas) | No medido en producción; N+1 en `Reportes\Dashboard`, `PanelAdmin\Metricas` (archivo 14) |
| RNF-12 | Nota final y progreso inmediatos | Cumple | Cálculo en lectura (`CalculadoraNotas`) | — |
| RNF-13 | Disponibilidad 99 % | No verificable | `railway.json` (healthcheck, reinicio) | Depende del hosting; `php artisan serve` (H-11) |
| RNF-14 | Sin alterar históricos de ciclos finalizados | Parcial | Bloqueos por `estaFinalizado()` (04b § 5) | Reapertura (H-03) y nota mínima retroactiva (H-20) |
| RNF-15 | Nuevas instituciones vía API sin cambios estructurales | Cumple | `integraciones_externas` | — |
| RNF-16 | Múltiples cursos sin límite fijo | Cumple | Modelo de datos (04a) | — |
| RNF-17 | Código por módulos funcionales | Cumple | `app/Livewire/<Módulo>`, `app/Services/<Módulo>` | Lógica de negocio también en componentes (03 § 1) |
| RNF-18 | Reglas críticas en el backend | Parcial | Pesos, matrícula, visibilidad en servidor | Tiempo del quiz depende del estado del componente (H-13); bloqueo de estructura evitable (H-19) |
| RNF-19 | Chrome, Edge y Firefox recientes | No verificable | — | No se probó en navegadores en esta auditoría |
| RNF-20 | Responsiva | Cumple | Layout con menú deslizable y 19 `overflow-x-auto` (10 § 10) | Verificado por código, no en un dispositivo |
| RNF-21 | Payload JSON | Cumple | `MatriculaController.php:31-33` (415); `S/ApiIntegracionTest::test_un_cuerpo_que_no_es_json_devuelve_415` | — |

Total RNF: Cumple 12, Parcial 5, No cumple 0, No verificable 4.

## 3. Reglas de negocio (ERS § 7, 12 reglas)

| # | Regla | Estado | Evidencia | Observación |
|---|---|---|---|---|
| 1 | Pesos = 100 % | Cumple | RF-56 | — |
| 2 | Cambio de contraseña obligatorio al primer ingreso | Cumple | RF-05 | Las cuentas de demostración entran sin cambio (`DatosDeEjemploTest::test_las_cuentas_de_demostracion_ingresan_sin_cambio_obligatorio`; archivo 15) |
| 3 | Baja lógica, nunca física | Cumple | RF-30 | — |
| 4 | Clonar no copia estudiantes, notas ni entregas | Cumple | RF-21 | — |
| 5 | Tareas y quizzes no se promedian en un rubro | Cumple | RF-48 | — |
| 6 | Sistema externo = fuente de verdad | Cumple | RF-105 | — |
| 7 | Sesiones y semanas independientes | Cumple | RF-19, RF-39 | — |
| 8 | Ningún curso finalizado se modifica retroactivamente | No cumple | `Cursos/Formulario::guardarSoloEstado`; prueba temporal `test_h_supervisor_reabre_curso_archivado` | **H-03**: la reapertura es una función explícita; además H-20 |
| 9 | Borrador o Programado nunca visible | Parcial | RF-38 | **H-01** |
| 10 | Toda matrícula notifica in-app y por correo | Cumple | RF-33 | Correo real no verificado |
| 11 | Toda edición de nota o acción crítica en bitácora inmutable | Parcial | RF-95, RF-96, RF-99 | **H-04** (pérdida de nota sin registro) y H-05 |
| 12 | Nota mínima general para todos los cursos | Cumple | RF-107 | — |

Total reglas: Cumple 9, Parcial 2, No cumple 1.

## 4. Casos de uso (25)

| CU | Caso de uso | Estado | Evidencia | Observación |
|---|---|---|---|---|
| CU-01 | Iniciar sesión y cambiar contraseña inicial | Cumple | `RolesYFuncionalidadTest::test_flujo_primer_login` | — |
| CU-02 | Cursos matriculados y progreso | Parcial | `F/CasosDeUsoPrincipalesTest::test_cu02_cu03_el_estudiante_consume_una_leccion_y_ve_subir_su_progreso` | H-02, H-01 |
| CU-03 | Consumir contenido de un módulo | Cumple | idem | — |
| CU-04 | Entregar una tarea | Parcial | `F/CasosDeUsoPrincipalesTest::test_cu04_cu13_la_entrega_llega_al_profesor_y_la_nota_vuelve_al_estudiante` | H-04 en grupales |
| CU-05 | Rendir un quiz | Parcial | `F/CasosDeUsoPrincipalesTest::test_cu05_el_estudiante_rinde_un_quiz_y_el_resultado_llega_al_profesor` | H-13 |
| CU-06 | Consultar el desglose de calificaciones | Cumple | `Cursos/BoletaEstudiante` | PDF incompleto (H-23) |
| CU-07 | Consultar su asistencia | Cumple | `F/CasosDeUsoTest::test_el_profesor_pasa_lista_y_el_estudiante_ve_su_porcentaje` | — |
| CU-08 | Enviar y recibir mensajes | Cumple | `F/CasosDeUsoPrincipalesTest::test_cu08_conversacion_entre_estudiante_y_profesor` | Sin hilos (RF-82) |
| CU-09 | Consultar su calendario | Cumple | `F/CasosDeUsoTest::test_el_calendario_del_estudiante_incluye_tareas_quizzes_sesiones_y_evaluaciones` | — |
| CU-10 | Pedir apoyo al asistente de IA | Cumple | `BotFaqTest`, `S/AsistenteIaTest` | Proveedor real no probado |
| CU-11 | Gestionar módulos y lecciones | Cumple | `F/CasosDeUsoPrincipalesTest::test_cu11_el_profesor_publica_contenido_semanal_y_el_estudiante_lo_ve` | — |
| CU-12 | Crear tareas y quizzes | Cumple | `F/CasosDeUsoPrincipalesTest::test_cu12_las_actividades_que_crea_el_profesor_llegan_al_estudiante` | — |
| CU-13 | Calificar entregas y registrar notas | Cumple | `test_cu04_cu13_...`, `F/FlujosPrincipalesTest::test_registrar_una_nota_de_rubro_notifica_al_estudiante` | H-16 |
| CU-14 | Editar pregunta y decidir recálculo | Cumple | `F/CasosDeUsoTest::test_corregir_una_pregunta_y_recalcular_actualiza_notas_audita_y_notifica` | H-19 |
| CU-15 | Consultar reportes de su curso | Cumple | `ReportesTest::test_profesor_ve_promedio_y_tasa_de_entrega_de_sus_cursos` | — |
| CU-16 | Gestionar grupos | Cumple | `F/CasosDeUsoPrincipalesTest::test_cu16_el_estudiante_ve_el_grupo_que_le_asigno_el_profesor` | — |
| CU-17 | Registrar asistencia | Cumple | `F/CasosDeUsoTest::test_el_profesor_pasa_lista_y_el_estudiante_ve_su_porcentaje` | No está en la matriz § 5 |
| CU-18 | Crear y dar de baja cuentas (individual y masiva) | Cumple | `UsuariosTest`, `F/CasosDeUsoTest::test_la_importacion_crea_las_filas_validas_y_reporta_los_errores` | "Dar de baja" una cuenta = desactivar |
| CU-19 | Matricular y dar de baja en un curso | Cumple | `F/FlujosPrincipalesTest::test_flujo_alta_y_matricula_manual`, `::test_la_baja_es_logica_y_conserva_el_historial` | — |
| CU-20 | Crear curso y definir esquema | Cumple | `CursosTest::test_supervisor_crea_un_curso_asignando_un_profesor`, `F/FlujosPrincipalesTest::test_flujo_de_evaluacion_con_nota_final_ponderada` | — |
| CU-21 | Clonar un curso | Cumple | `F/FlujosPrincipalesTest::test_flujo_de_clonacion_de_curso` | H-14 |
| CU-22 | Emitir comunicados | Cumple | `F/CasosDeUsoPrincipalesTest::test_cu22_el_comunicado_del_supervisor_llega_al_estudiante` | — |
| CU-23 | Dashboard institucional | Cumple | `PanelAdminTest::test_administrador_puede_ver_las_metricas_y_la_configuracion` | — |
| CU-24 | Bitácora de auditoría | Cumple | `S/AuditoriaTest` (6) | — |
| CU-25 | Nota mínima e integración (API Key) | Cumple | `F/FlujosPrincipalesTest::test_la_nota_minima_configurable_decide_la_aprobacion`, `S/ApiIntegracionTest::test_solo_el_supervisor_gestiona_las_api_keys` | — |

Total CU: Cumple 22, Parcial 3.

## 5. Flujos (ERS § 9)

| Flujo | Pasos | Estado | Evidencia | Observación |
|---|---|---|---|---|
| 9.1 Alta y matrícula manual | 7 | Cumple | `F/FlujosPrincipalesTest::test_flujo_alta_y_matricula_manual` | Correo real no verificado |
| 9.2 Matrícula por integración | 7 | Cumple | `S/ApiIntegracionTest::test_una_solicitud_valida_crea_la_cuenta_la_matricula_y_notifica` | — |
| 9.3 Evaluación del estudiante | 6 | Cumple | `F/FlujosPrincipalesTest::test_flujo_de_evaluacion_con_nota_final_ponderada` | — |
| 9.4 Clonación para un nuevo ciclo | 5 | Cumple | `F/FlujosPrincipalesTest::test_flujo_de_clonacion_de_curso` | H-14; el clon copia fechas y sesiones del ciclo anterior y redirige a editarlas (05 § 1) |

## 6. Matriz de permisos (ERS § 5, 18 acciones)

Las 18 acciones se verificaron una por una en el archivo 06 § 4: **18 Cumple**. Permisos reales fuera de la matriz (06 § 4): consulta de solo lectura del Supervisor, cambio de rol a cualquier rol (H-12), reapertura de cursos (H-03), widget de IA para todos los roles y registro de asistencia (no está en la matriz).

## 7. Fases (ERS § 11)

| Fase | Ítem | Estado | Observación |
|---|---|---|---|
| 1 Estudiante | Login y cambio de contraseña | Cumple | — |
| 1 Estudiante | Mis cursos y progreso | Parcial | H-02 |
| 1 Estudiante | Contenido | Cumple | — |
| 1 Estudiante | Tareas | Parcial | H-04 |
| 1 Estudiante | Quizzes | Parcial | H-13 |
| 1 Estudiante | Calificaciones por rubro | Cumple | — |
| 1 Estudiante | Notificaciones | Cumple | H-16 menor |
| 1 Estudiante | Calendario | Cumple | — |
| 1 Profesor | Gestión de contenido | Cumple | — |
| 1 Profesor | Tareas y quizzes | Cumple | — |
| 1 Profesor | Registro y edición de notas por rubro | Cumple | H-03 |
| 1 Profesor | Reportes básicos | Cumple | — |
| 1 Profesor | Listado de estudiantes | Cumple | Matrícula, libro y compañeros |
| 1 Supervisor | Gestión de usuarios | Cumple | H-12 |
| 1 Supervisor | Cursos y esquema | Cumple | H-03 |
| 1 Supervisor | Matrícula manual | Cumple | — |
| 1 Supervisor | Dashboard básico | Cumple | — |
| 1 Supervisor | Auditoría | Cumple | H-05 |
| 1 Supervisor | Configuración general | Cumple | H-20 |
| 1 Supervisor | Integración externa | Cumple | H-08 |
| 2 | Mensajería | Cumple | Sin hilos |
| 2 | Grupos | Parcial | H-04 |
| 2 | Banco de preguntas | Cumple | Sin pruebas de CRUD |
| 2 | Asistencia | Cumple | — |
| 2 | Clonación | Cumple | H-14 |
| 2 | Reportes avanzados | Cumple | — |
| 2 | Clases en vivo | Parcial | Solo un enlace externo por sesión y aviso "en curso"; las sesiones no aparecen en el calendario de cursos sin asistencia; no hay RF que defina el alcance |
| 3 | Asistente de IA | Cumple | H-15 |
| 3 | Analítica de riesgo y engagement | Cumple | Excluye tareas grupales asignadas (07a § 3.2) |

## 8. Conteo frente a `docs/AUDITORIA.md`

| Categoría | `docs/AUDITORIA.md` (§ 1 y § 7) | Esta auditoría | Diferencia principal |
|---|---|---|---|
| RF (108) | 106 completos, 0 parciales, 2 no aplica | 93 Cumple, 12 Parcial, 0 No cumple, 3 No verificable | 7 defectos confirmados (H-01 a H-04, H-13, H-24) y omisiones (RF-55, RF-82, RF-108) |
| RNF (21) | 19 completos, 2 parciales (RNF-10 y RNF-13) | 12 Cumple, 5 Parcial, 4 No verificable | RNF-06, 07, 09, 14 y 18 parciales; RNF-11 y RNF-19 no verificables en producción |
| Reglas de negocio | "10 de 10" (§ 12.1) | 12 reglas en el ERS v2: 9 Cumple, 2 Parcial, 1 No cumple | El documento cuenta 10 reglas (versión anterior del ERS); la regla 8 no se cumple (H-03) |
| Casos de uso (25) | 25 de 25 de punta a punta | 22 Cumple, 3 Parcial | CU-02, CU-04, CU-05 |
| Pasos de flujos | "28 de 28" (§ 12.1) | 25 pasos en el ERS v2 (7 + 7 + 6 + 5), 4 flujos Cumple | El documento usa un número de pasos que no coincide con el ERS v2 |
| Matriz de permisos | "17 de 17" | 18 acciones, 18 Cumple | El ERS v2 tiene 18 acciones (06 § 4) |
| Seguridad | "0 hallazgos abiertos" | H-08, H-12, H-15, H-17, H-18, H-22 y otros (archivo 13) | — |
| `npm audit` | "0 vulnerabilidades" | 10 vulnerabilidades (2 críticas, 6 altas, 2 moderadas) en la sesión anterior | Archivo 13 § 12 |

## 9. Defectos del propio ERS

| # | Defecto | Dónde | Efecto |
|---|---|---|---|
| E-1 | RNF-06 exige "Supabase Storage", pero el equipo usa Cloudinary por instrucción del cliente (`docs/TRAZABILIDAD.md`, RNF-06). El ERS v2 no se actualizó. | § 4.1, § 2.5 y § 10 | Requisito incumplible tal como está escrito |
| E-2 | RF-08 y RF-09 son "trabajo futuro" pero están numerados como requisitos con prioridad | § 3.1 | Inflan el total de RF |
| E-3 | No hay RF para "Clases en vivo" (Fase 2) ni para "Analítica de riesgo y engagement" (Fase 3): solo se mencionan en § 11 | § 11.2, § 11.3 | Alcance sin criterio de aceptación |
| E-4 | RF-43 lista 5 estados, pero RF-49 introduce "devolver", que exige un sexto estado | § 3.6 | El código agrega "Devuelto" |
| E-5 | No define cómo un curso pasa a "finalizado" ni si se puede reabrir; la regla 8 lo prohíbe, RF-22 solo dice "archivar" | § 3.3, § 7 | La reapertura (H-03) no está prohibida por ningún RF explícito |
| E-6 | RF-55 dice que el rubro "debe tener" semana, pero no define qué fecha corresponde a "la semana N" | § 3.7 | Interpretación libre (el código usa el lunes desde `fecha_inicio`) |
| E-7 | La matriz § 5 no incluye "Registrar asistencia" (RF-70, CU-17) ni "Gestionar grupos" | § 5 | Permisos sin respaldo en la matriz |
| E-8 | RF-31 y RNF-09 se solapan (confirmación de acciones destructivas) | § 3.4, § 4.2 | Duplicación |
| E-9 | RNF-10 y RNF-13 no son verificables con el producto (usabilidad sin capacitación, 99 % del hosting) | § 4 | Sin criterio de medición |
| E-10 | RF-95 "motivo del ajuste (si aplica)" no dice cuándo aplica | § 3.16 | El código lo exige en rubros y usa un motivo fijo en tareas |
| E-11 | Regla 11 dice "inmutable para todos los roles"; RF-99 limita a "desde la interfaz" | § 7, § 3.16 | Alcance distinto (base de datos frente a interfaz) |
| E-12 | No hay requisitos de retención de datos personales en logs (API, login), ni de límite de solicitudes en el login o en el asistente | — | Riesgos de privacidad y abuso sin requisito (H-15) |
| E-13 | La API (§ 3.17) no define idempotencia, reintentos ni respuesta ante un curso archivado | § 3.17 | Comportamiento definido solo por el código |
| E-14 | RF-18 "estructura base de módulos" no define cuál es | § 3.3 | El código lo interpretó como semanas |
| E-15 | El ERS v2 tiene 12 reglas, pero la documentación del repositorio sigue contando 10 | `docs/AUDITORIA.md` § 12.1 | Trazabilidad desactualizada (archivo 16) |
