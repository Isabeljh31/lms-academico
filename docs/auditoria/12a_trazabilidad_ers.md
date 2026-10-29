# 12a. Trazabilidad con el ERS v2 (parte 1: RF-01 a RF-65)

Fuente: `ERS_LearnUp_v2.docx` (versión 2.0, setiembre 2026), texto extraído en el scratchpad de la sesión anterior (`ers.txt`, 985 líneas). El ERS tiene 108 RF, 21 RNF, 12 reglas de negocio, 25 casos de uso, 4 flujos, una matriz de 18 acciones y 3 fases. Esta parte cubre los RF-01 a RF-65; la parte 12b cubre RF-66 a RF-108, RNF, reglas, casos de uso, flujos, matriz, fases, el conteo frente a `docs/AUDITORIA.md` y los defectos del propio ERS.

## Criterios de estado

| Estado | Significado |
|---|---|
| Cumple | El código implementa el requisito y hay evidencia (archivo:línea o prueba). Puede tener observaciones menores. |
| Parcial | Implementado, pero un defecto confirmado o una omisión hace que no se cumpla en todos los casos. |
| No cumple | El comportamiento real contradice el requisito. |
| No verificable | No se puede comprobar con el código ni con pruebas locales (depende del hosting, de usuarios reales o de servicios externos), o es trabajo futuro según el propio ERS. |

Abreviaturas de pruebas: `F/` = `tests/Feature/Flujos/`, `S/` = `tests/Feature/Seguridad/`; el resto, `tests/Feature/` salvo que se indique `Unit/`.

## 3.1 Autenticación y gestión de cuentas

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-01 | Login con correo y contraseña para los 3 roles | Cumple | `Forms/LoginForm.php:51-89`; `Auth/AuthenticationTest::test_users_can_authenticate_using_the_login_screen` | El error de credenciales sale en inglés (H-17) |
| RF-02 | "¿Olvidaste tu contraseña?" con enlace al correo | Cumple | Volt `pages/auth/forgot-password`; `Auth/PasswordResetTest` (4) | Revela si el correo existe y el correo está en inglés (H-17); el enlace puede apuntar a otro dominio si el proxy reenvía el host (H-22) |
| RF-03 | Contraseña temporal al crear (manual o masiva) | Cumple | `GestorCuentas.php:39,68-71`; `UsuariosTest::test_supervisor_crea_cuenta_de_estudiante_con_password_temporal` | — |
| RF-04 | Correo de bienvenida con contraseña temporal y enlace | Cumple | `BienvenidaMail` (cola cifrada), `emails/bienvenida.blade.php:35`; `UsuariosTest::test_el_correo_de_bienvenida_se_encola_cifrado_sin_la_contrasena_en_texto_plano` | Envío SMTP real no verificado; `correo_enviado` significa "encolado" (08 § 10) |
| RF-05 | Cambio obligatorio en el primer ingreso; restringir lo demás | Cumple | `Middleware/PrimerLogin`, `Auth/CambiarPasswordPrimerLogin`; `S/AutenticacionTest::test_el_primer_ingreso_obliga_a_cambiar_la_contrasena_antes_de_todo` | `PrimerLogin` deja pasar `livewire/*` (archivo 06 § 2) |
| RF-06 | Bloqueo de 15 min tras 3 fallos; recuperar sin esperar | Cumple | `config/auth.php` (`bloqueo_login`), `User::registrarIntentoFallido`; `S/AutenticacionTest::test_bloquea_la_cuenta_tras_tres_intentos_fallidos`, `::test_recuperar_la_contrasena_desbloquea_la_cuenta` | Bloqueo por cuenta, sin límite por IP (07a § 11.1) |
| RF-07 | Cambiar contraseña desde el perfil | Cumple | Volt `profile/update-password-form`; `Auth/PasswordUpdateTest` (2) | Basta con 8 caracteres (H-18) |
| RF-08 | 2FA (trabajo futuro) | No verificable | ERS: "(Trabajo futuro)" | No implementado, como indica el ERS |
| RF-09 | Login con Google (trabajo futuro) | No verificable | ERS: "(Trabajo futuro)" | No implementado, como indica el ERS |

## 3.2 Gestión de usuarios (Supervisor)

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-10 | Crear Estudiante y Profesor individualmente (nombres, apellidos, correo, rol) | Cumple | `Usuarios/Index.php:88-116`, `GestorCuentas::ROLES_CREABLES`; `UsuariosTest::test_supervisor_crea_cuenta_de_estudiante_con_password_temporal`, `AdminProfesoresTest::test_administrador_puede_crear_un_nuevo_profesor_con_rol` | El cambio de rol posterior sí permite `supervisor` (H-12) |
| RF-11 | Importación masiva CSV/Excel | Cumple | `Usuarios/ImportarCSV`, `HojasCalculo/LectorXlsx`; `F/CasosDeUsoTest::test_la_importacion_acepta_un_libro_de_excel` | Máximo 1000 filas y 2 MB |
| RF-12 | Validar fila por fila sin bloquear las válidas | Cumple | `ImportarCSV.php:101-143`; `F/CasosDeUsoTest::test_la_importacion_crea_las_filas_validas_y_reporta_los_errores` | — |
| RF-13 | Reporte de creados y fallidos con motivo | Cumple | `ImportarCSV.php:117-141` (creados, omitidos, errores) | Las contraseñas temporales quedan en el *snapshot* de Livewire (07b § 9.1) |
| RF-14 | Editar datos de una cuenta | Cumple | `Usuarios/Index.php:152-195` con bitácora | Sin prueba específica de edición (búsqueda en `tests/`) |
| RF-15 | Activar o desactivar sin borrar historial | Cumple | `Usuarios/Index.php:201-244`, `Middleware/CuentaActiva`; `F/FlujosPrincipalesTest::test_desactivar_una_cuenta_conserva_su_historial`, `S/AutenticacionTest::test_una_sesion_abierta_se_cierra_si_la_cuenta_se_desactiva` | — |
| RF-16 | Listado con filtros por nombre, correo, estado o curso | Cumple | `Usuarios/Index.php:248-263` | — |
| RF-17 | No eliminar físicamente cuentas con historial | Cumple | Sin `->delete()` sobre `User` en `app/` (04b § 4); `ProfileTest::test_el_usuario_no_puede_eliminar_su_propia_cuenta` | La protección depende de la aplicación: las FK tienen `cascade` hacia `users` |

## 3.3 Cursos y clonación

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-18 | Crear curso: nombre, descripción y estructura base de módulos | Cumple | `Cursos/Formulario.php:85-188`; `CursosTest::test_supervisor_crea_un_curso_asignando_un_profesor` | La "estructura base" son las semanas 1..N (16 por defecto), que se crean al prepararlas (10 § 5) |
| RF-19 | Profesor, fechas, sesiones de clase y semanas, independientes | Cumple | `Cursos/Formulario.php:97-119` (`sesiones_clase_count`, `semanas_duracion`); `ContenidoPorSemanasTest::test_el_supervisor_define_la_duracion_y_no_puede_dejarla_por_debajo_del_contenido` | — |
| RF-20 | Clonar contenido y esquema como plantilla | Cumple | `Curso::clonar` (`Curso.php:250-322`), `Cursos/Show.php:439-468`; `F/FlujosPrincipalesTest::test_flujo_de_clonacion_de_curso`, `RecursosLeccionTest::test_clonar_el_curso_copia_los_recursos_de_sus_lecciones` | Titular del clon sin validar rol (H-14); no copia profesores adicionales ni sesiones |
| RF-21 | No copiar matriculados, notas, entregas ni resultados | Cumple | `Curso.php:243-248` (docblock) y cuerpo de `clonar`; `F/FlujosPrincipalesTest::test_flujo_de_clonacion_de_curso` | — |
| RF-22 | Editar, publicar, despublicar y archivar | Cumple | `Cursos/Formulario.php` (`Curso::ESTADOS`) | Archivar no es definitivo: el Supervisor reabre (H-03) |
| RF-23 | Uno o más profesores por curso | Cumple | Tabla `curso_profesor`, `Formulario.php:128-135,175`; `F/FlujosPrincipalesTest::test_el_supervisor_asigna_profesores_adicionales_y_queda_auditado`, `::test_un_profesor_adicional_gestiona_y_califica_el_curso` | — |
| RF-24 | Cambiar el profesor asignado | Cumple | `Admin/Profesores/Index.php:87-125`; `AdminProfesoresTest::test_administrador_puede_reasignar_un_curso_a_un_profesor` | — |
| RF-25 | Al menos un módulo antes de publicar | Cumple | `Cursos/Formulario.php:137-142`; `CursosTest::test_no_se_puede_publicar_un_curso_sin_modulos` | Una semana "preparada" sin contenido ya cuenta como módulo |

## 3.4 Matrícula

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-26 | Matrícula manual por el Supervisor | Cumple | `GestorMatriculas::matricularManual`; `F/FlujosPrincipalesTest::test_flujo_alta_y_matricula_manual`, `::test_el_profesor_no_puede_matricular` | — |
| RF-27 | Endpoint de integración | Cumple | `Api/MatriculaController`; `S/ApiIntegracionTest::test_una_solicitud_valida_crea_la_cuenta_la_matricula_y_notifica` | — |
| RF-28 | Registrar el origen (manual o API) | Cumple | `inscripciones.origen`; `GestorMatriculas.php:62,102` | — |
| RF-29 | Baja por el Supervisor y el Profesor del curso | Cumple | `CursoPolicy::darDeBaja`, `GestionMatricula::puedeDarDeBajaA`; `F/FlujosPrincipalesTest::test_el_profesor_da_de_baja_en_su_propio_curso_con_historial_y_auditoria`, `::test_el_profesor_no_da_de_baja_en_un_curso_ajeno` | Coherente en ERS, policy, ruta y código (06 § 5) |
| RF-30 | La baja conserva el historial | Cumple | `Inscripcion::darDeBaja` (lógica); `F/FlujosPrincipalesTest::test_la_baja_es_logica_y_conserva_el_historial` | Tras la baja, el panel del estudiante sigue mostrando tareas del curso (H-01) |
| RF-31 | Confirmación explícita con mensaje de retiro | Cumple | Modal de `GestionMatricula` con el texto "Esta acción retirará a ... del curso" (`gestion-matricula.blade.php:144`) | — |
| RF-32 | Matrícula de API: solo desactivar acceso local | Cumple | `GestorMatriculas::darDeBaja` (`desactivarAccesoLocal`); `F/FlujosPrincipalesTest::test_el_profesor_no_desactiva_una_matricula_que_llego_por_api`, `S/ApiIntegracionTest::test_el_bloqueo_local_del_supervisor_se_respeta_al_resincronizar` | — |
| RF-33 | Notificar al estudiante (in-app y correo con enlace) | Cumple | `MatriculadoEnCurso` (database + mail); `F/FlujosPrincipalesTest::test_flujo_alta_y_matricula_manual` | Correo real no verificado; la fila in-app se fecha en UTC (H-21) |

## 3.5 Contenido educativo

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-34 | Crear, editar, ordenar y eliminar módulos | Cumple | `Cursos/Show.php:313-400` (preparar, editar, mover de semana, vaciar); `ContenidoPorSemanasTest::test_mover_el_contenido_a_otra_semana_conserva_sus_tareas_y_no_pisa_una_semana_ocupada`, `F/CasosDeUsoTest::test_un_modulo_con_entregas_no_se_puede_eliminar` | Reinterpretado: un módulo = una semana o Prácticas de campo; no hay módulos libres ni dos por semana (índice único) |
| RF-35 | Lecciones con título, descripción, video y material | Cumple | `Lecciones/Index.php:115-196`; `F/CasosDeUsoPrincipalesTest::test_cu11_el_profesor_publica_contenido_semanal_y_el_estudiante_lo_ve` | — |
| RF-36 | Recursos en varios formatos y enlaces | Cumple | `Lecciones/Index::agregarRecurso`, `config/archivos.php` (lista blanca); `RecursosLeccionTest::test_el_profesor_agrega_varios_recursos_y_el_estudiante_los_ve_en_la_leccion` | Admite además `mp4`, `mp3`, `rar`, `csv`, `txt`; 10 MB |
| RF-37 | Borrador, Publicado o Programado en módulo, lección, tarea y quiz | Cumple | `TieneEstadoPublicacion`, `Leccion::scopePublicadas`, formularios; `F/CasosDeUsoTest::test_el_contenido_programado_notifica_al_publicarse_una_sola_vez` | En tareas y quizzes "borrador" = `visible=false` (dos campos); `publicar_en` admite fechas pasadas en tareas y quizzes |
| RF-38 | Borrador o Programado no visible para el estudiante | Parcial | Scopes y policies; `F/CasosDeUsoTest::test_el_contenido_en_borrador_o_programado_no_es_visible_para_el_estudiante` | **H-01**: el panel de inicio muestra títulos de tareas programadas, de módulos en borrador y de cursos en borrador o despublicados (confirmado por prueba temporal) |
| RF-39 | Sesiones de clase independientes de módulos y lecciones | Cumple | `cursos.sesiones_clase_count` y tabla `sesiones_clase` | — |

## 3.6 Tareas y quizzes

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-40 | Tarea con título, descripción, instrucciones, inicio, fecha límite, puntaje, intentos y tipo | Cumple | `Tareas/Formulario.php:93-113`; `TareasTest::test_profesor_puede_crear_una_tarea_en_su_curso` | "Puntaje" = `peso_nota` (0-100), usado solo en el promedio de práctica |
| RF-41 | Restringir formatos de entrega | Cumple | `Tarea::formatosPermitidos`, `SubidorArchivos::reglas`; `S/SubidaArchivosTest::test_respeta_los_formatos_restringidos_por_la_tarea` | Validación por extensión, no por contenido (archivo 13) |
| RF-42 | Entregar archivo, respuesta y/o enlace | Cumple | `Tareas/Detalle::entregar`; `F/CasosDeUsoTest::test_el_estudiante_entrega_texto_y_enlace_sin_archivo`, `TareasTest::test_estudiante_inscrito_puede_entregar_una_tarea` | En grupales, la entrega de un integrante nuevo borra las notas del grupo (H-04) |
| RF-43 | Estado automático: Pendiente, Entregado, Calificado, Fuera de plazo, No entregado | Cumple | `Tarea::estadoPara` (`Tarea.php:149-168`); `Unit/Models/TareaTest` (7) | Agrega "Devuelto"; usa `updated_at` como momento de entrega (H-10) |
| RF-44 | Quiz de opción única, múltiple y V/F | Cumple | `Quiz::TIPOS_PREGUNTA`, `Quizzes/Formulario`; `QuizzesTest::test_profesor_puede_crear_un_quiz_con_preguntas`, `Unit/Models/QuizTest::test_la_opcion_multiple_exige_exactamente_las_correctas` | — |
| RF-45 | Puntaje por pregunta, retroalimentación, intentos, tiempo límite, aleatorización y fecha límite | Parcial | `Quizzes/Formulario.php:300-338`, `Quizzes/Resolver::enviar`; `F/CasosDeUsoTest::test_el_quiz_respeta_la_cantidad_de_intentos`, `::test_un_intento_fuera_del_tiempo_limite_no_se_registra` | **H-13**: el tiempo límite se reinicia al recargar la página (inicio no persistido) |
| RF-46 | Resultado automático al terminar | Cumple | `Quiz::calcularPuntaje`, `Resolver::enviar`; `QuizzesTest::test_estudiante_inscrito_puede_resolver_y_se_califica_automaticamente` | — |
| RF-47 | Banco de preguntas por tema, reutilizable | Cumple | `Quizzes/BancoPreguntas`, `Quizzes/Formulario::agregarDesdeBanco` | Guardar, editar y eliminar del banco sin pruebas (11 § 4) |
| RF-48 | Tareas y quizzes independientes de las notas de evaluación | Cumple | `CalculadoraNotas` solo usa `notas_rubro`; `F/FlujosPrincipalesTest::test_las_notas_de_tareas_y_quizzes_no_cambian_la_nota_final` | — |
| RF-49 | Revisar, ver, comentar, calificar o devolver | Cumple | `Tareas/Detalle::calificar`, `::devolver`, `Docente/PorCalificar`; `F/CasosDeUsoTest::test_una_entrega_devuelta_se_puede_volver_a_entregar`, `BlackboardDocenteAlumnoTest::test_docente_puede_acceder_a_cola_por_calificar_y_evaluar_entrega` | "Devolver" no limpia la nota ni se audita |

## 3.6.1 Corrección de preguntas

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-50 | Editar pregunta y respuesta aunque haya intentos | Cumple | `Quizzes/Formulario::guardar`, `::confirmarGuardado` | No se pueden agregar ni quitar preguntas con intentos (`F/CasosDeUsoTest::test_con_intentos_no_se_pueden_agregar_preguntas`); el bloqueo es evitable (H-19) |
| RF-51 | Advertir cuántos estudiantes afecta y preguntar si recalcular | Cumple | `Quizzes/Formulario.php:93-101,189-195` (`estudiantesAfectados`, modal) | — |
| RF-52 | Recalcular puntajes si se confirma | Cumple | `Quiz::recalcularResultados` (`Quiz.php:240-257`); `F/CasosDeUsoTest::test_corregir_una_pregunta_y_recalcular_actualiza_notas_audita_y_notifica`, `::test_guardar_sin_recalcular_mantiene_las_notas` | Ligado por posición de la pregunta (H-09) |
| RF-53 | Notificar el cambio de nota del quiz | Cumple | `NotaQuizModificada`; `F/CasosDeUsoTest::test_corregir_una_pregunta_y_recalcular_actualiza_notas_audita_y_notifica` | — |

## 3.7 Esquema de evaluación y calificaciones

| ID | Requisito (resumen) | Estado | Evidencia | Observación |
|---|---|---|---|---|
| RF-54 | El Supervisor define el esquema (uno o más rubros) | Cumple | `Cursos/EsquemaEvaluacion` (`administrar`); `F/FlujosPrincipalesTest::test_flujo_de_evaluacion_con_nota_final_ponderada` | — |
| RF-55 | Cada rubro con nombre, semana y peso | Parcial | `EsquemaEvaluacion.php:88-92` | La semana es **opcional** (`'rubros.*.semana' => ['nullable', ...]`, línea 91); el ERS dice "debe tener". Un rubro sin semana no aparece en el calendario (`EventoCalendario/Index.php:208`) |
| RF-56 | Pesos = 100 % antes de guardar (backend) | Cumple | `CalculadoraNotas::pesosSumanCien`, `EsquemaEvaluacion.php:101-106`; `F/FlujosPrincipalesTest::test_el_esquema_no_se_guarda_si_los_pesos_no_suman_cien`, `Unit/.../CalculadoraNotasTest::test_rechaza_pesos_que_no_suman_cien` | — |
| RF-57 | El Profesor registra nota 0-20 por rubro y estudiante | Cumple | `LibroCalificaciones::guardarNotaRubro` (0-20, un decimal); `BlackboardDocenteAlumnoTest::test_docente_puede_ver_y_editar_libro_de_calificaciones` | Solo con esquema al 100 % |
| RF-58 | Nota final = suma ponderada | Cumple | `CalculadoraNotas::notaFinal`; `Unit/.../CalculadoraNotasTest::test_calcula_la_nota_final_como_suma_ponderada` | Redondeo a 1 decimal antes de comparar con la mínima (08 § 5) |
| RF-59 | Desglose al estudiante: rubro, peso, nota y nota final | Cumple | `Cursos/BoletaEstudiante` (pantalla); `F/FlujosPrincipalesTest::test_flujo_de_evaluacion_con_nota_final_ponderada` | La boleta **en PDF** no muestra rubros ni nota final (H-23) |
| RF-60 | Tareas y quizzes de práctica aparte | Cumple | Vista de actividades del libro, hoja "Práctica" del Excel; `HojasCalculo/ExportacionesTest::test_el_libro_de_calificaciones_se_exporta_a_excel_con_resumen_notas_y_practica` | — |
| RF-61 | Al clonar, el esquema se copia editable | Cumple | `Curso::clonar` (esquema y rubros); `F/FlujosPrincipalesTest::test_flujo_de_clonacion_de_curso` | — |
| RF-62 | No editar el esquema de un curso finalizado | Parcial | `EsquemaEvaluacion::getEditableProperty`; `F/FlujosPrincipalesTest::test_un_curso_archivado_no_admite_cambios_en_esquema_ni_notas` | **H-03**: el Supervisor reabre el curso archivado y el esquema vuelve a ser editable (confirmado por prueba temporal) |
| RF-63 | Listado de calificaciones con nota final y detalle por rubro | Cumple | `Cursos/LibroCalificaciones`, exportación a Excel; `HojasCalculo/ExportacionesTest` | — |
| RF-64 | Resaltar en rojo las notas finales bajo la mínima | Cumple | Vistas del libro y la boleta; `ExportadorLibroCalificaciones::nota` (estilo de alerta) | No se verificó el color en pantalla |
| RF-65 | Editar nota de rubro mientras no esté finalizado, con bitácora | Parcial | `LibroCalificaciones.php:103-148` (motivo obligatorio y bitácora); `S/AuditoriaTest::test_un_ajuste_de_nota_queda_con_valor_anterior_nuevo_y_motivo` | La restricción "no finalizado" se anula al reabrir (H-03) |

## Subtotal de esta parte (RF-01 a RF-65)

| Estado | Cantidad | IDs |
|---|---|---|
| Cumple | 58 | los no listados abajo |
| Parcial | 5 | RF-38 (H-01), RF-45 (H-13), RF-55 (semana opcional), RF-62 (H-03), RF-65 (H-03) |
| No cumple | 0 | — |
| No verificable | 2 | RF-08 y RF-09 (trabajo futuro según el ERS) |

El total consolidado de los 108 RF está en 12b § 1.
