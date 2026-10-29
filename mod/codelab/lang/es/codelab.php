<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cadenas de idioma en Español para mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Información del plugin.
$string['pluginname']        = 'CodeLab - Editor de Código';
$string['pluginadministration'] = 'Administración de CodeLab';
$string['modulename']        = 'CodeLab';

// Capacidades (db/access.php).
$string['codelab:view'] = 'Ver la actividad CodeLab';
$string['codelab:submit'] = 'Entregar código en CodeLab';
$string['codelab:grade'] = 'Calificar entregas de CodeLab';
$string['codelab:viewallsubmissions'] = 'Ver todas las entregas de CodeLab';
$string['codelab:addinstance'] = 'Añadir una actividad CodeLab';
$string['codelab:execute'] = 'Ejecutar código en CodeLab';
$string['modulenameplural']  = 'CodeLabs';
$string['modulename_help']   = 'CodeLab permite a los estudiantes escribir, ejecutar y entregar código directamente en Moodle. Los profesores pueden definir casos de prueba para calificación automática.';

// Configuración general.
$string['codeeditor_settings']  = 'Configuración del Editor';
$string['default_language']     = 'Lenguaje por defecto';
$string['languages_allowed']    = 'Lenguajes permitidos';
$string['languages_allowed_help'] = 'Selecciona los lenguajes de programación que los estudiantes pueden usar para esta actividad.';
$string['starter_code']         = 'Código inicial';
$string['starter_code_help']    = 'Código que aparece en el editor cuando el estudiante abre la actividad por primera vez. Útil para proporcionar una plantilla o estructura base.';
$string['runner_code']          = 'Código envolvente (runner automático)';
$string['runner_code_help']     = 'Código que envuelve automáticamente la solución del estudiante. Usa {{CODE}} como marcador donde se insertará el código del estudiante. Permite que los estudiantes solo escriban su función/lógica sin necesitar input() ni print().';
$string['execution_limits']     = 'Límites de Ejecución';
$string['time_limit']           = 'Límite de tiempo por ejecución';
$string['memory_limit']         = 'Límite de memoria';
$string['seconds']              = '{$a} segundos';
$string['submission_settings']  = 'Configuración de Entrega';
$string['max_attempts']         = 'Intentos máximos';
$string['unlimited']            = 'Ilimitado';
$string['grading_method']       = 'Método de calificación';
$string['grading_best']         = 'Mejor intento';
$string['grading_last']         = 'Último intento';
$string['grading_first']        = 'Primer intento';
$string['grading_average']      = 'Promedio de intentos';
$string['testcases_visible']    = 'Mostrar casos de prueba a estudiantes';
$string['testcases_visible_help'] = 'Si está activo, los estudiantes pueden ver el nombre, la entrada y la salida esperada de cada caso de prueba. Los casos marcados como "oculto" nunca serán visibles.';
$string['allow_submissions_from_date'] = 'Permitir entregas desde';
$string['duedate']              = 'Fecha de entrega';
$string['cutoffdate']           = 'Fecha de corte (sin entregas después de esta fecha)';
$string['languages']            = 'Lenguajes';

// Casos de prueba.
$string['testcases']            = 'Casos de Prueba';
$string['testcase']             = 'Caso de prueba';
$string['add_testcase']         = '+ Agregar caso de prueba';
$string['tc_name']              = 'Nombre';
$string['tc_stdin']             = 'Entrada (stdin)';
$string['tc_expected_output']   = 'Salida esperada';
$string['tc_points']            = 'Puntos';
$string['tc_hidden']            = 'Oculto';
$string['hidden_testcase']      = 'Caso oculto';

// Vista del estudiante.
$string['last_submission']      = 'Última entrega';
$string['grade']                = 'Calificación';
$string['attempts']             = 'Intentos';
$string['pending']              = 'Pendiente';
$string['past_due']             = '⚠️ La fecha de entrega ha pasado. Puedes seguir enviando, pero podría afectar tu calificación.';
$string['before_allow_from']    = 'Esta actividad todavía no admite entregas. Podrás entregar a partir del {$a}.';
$string['past_cutoff']          = '❌ La actividad está cerrada. No se aceptan más entregas.';

// Vista del profesor.
$string['submissions']          = 'Entregas';
$string['statistics']           = 'Estadísticas';
$string['preview']              = 'Vista previa';
$string['student']              = 'Estudiante';
$string['submitted']            = 'Enviado';
$string['language']             = 'Lenguaje';
$string['tests_passed']         = 'Casos correctos';
$string['actions']              = 'Acciones';
$string['view']                 = 'Ver';
$string['no_submissions_yet']   = 'Aún no hay entregas para esta actividad.';
$string['preview_mode_notice']  = 'Modo vista previa: estás viendo la actividad como un estudiante.';
$string['total_students']       = 'Estudiantes';
$string['total_submissions']    = 'Total entregas';
$string['avg_grade']            = 'Nota promedio';
$string['pass_rate']            = 'Tasa de aprobación';
$string['pass_rate_unavailable'] = 'Sin definir';
$string['pass_rate_no_gradepass'] = 'La tasa de aprobación no se puede calcular porque esta actividad no tiene una «nota para aprobar» configurada. Defínela en el libro de calificaciones del curso (Configuración del ítem de calificación → Nota para aprobar) y el porcentaje aparecerá aquí.';
$string['grade_saved']          = 'Calificación guardada. Nota registrada en el libro de calificaciones: {$a->grade} (método: {$a->method}).';

// Interfaz del estudiante y del docente (MDL-05: antes escritas a mano en el código).
$string['unknown_user']         = 'Usuario desconocido';
$string['runner_active_notice'] = '<strong>Modo simplificado activo:</strong> solo escribes tu función o tu lógica; la lectura de datos y la impresión del resultado se hacen automáticamente. No necesitas usar <code>input()</code> ni <code>print()</code>.';
$string['grade_manual_note']    = '(calificación manual del profesor)';
$string['teacher_feedback']     = 'Retroalimentación del profesor';
$string['teacher_feedback_optional'] = 'Retroalimentación del profesor (opcional)';
$string['run_and_test']         = 'Ejecutar y probar';
$string['submit_solution']      = 'Entregar';
$string['program_output']       = 'Salida del programa';
$string['not_graded']           = 'Sin calificar';
$string['actual_output']        = 'Salida obtenida';
$string['compile_error_label']  = 'Error de compilación';
$string['stderr_label']         = 'Salida de error (stderr)';
$string['submitted_code']       = 'Código enviado';
$string['copy']                 = 'Copiar';
$string['copied']               = 'Copiado';
$string['manual_grade_label']   = 'Nota manual (deja vacío para usar la automática)';
$string['auto_grade_placeholder'] = 'Nota automática: {$a}';
$string['comments']             = 'Comentarios';
$string['feedback_placeholder'] = 'Escribe retroalimentación para el estudiante...';
$string['save_grade']           = 'Guardar calificación';
$string['tc_name_placeholder']  = 'Ej: Prueba básica';
$string['hide_from_student']    = 'Ocultar este caso al estudiante';
$string['delete']               = 'Eliminar';
$string['runner_code_explain']  = '<strong>¿Para qué sirve esto?</strong><br>Si lo rellenas, los estudiantes <strong>solo escriben su función o su lógica</strong> sin necesitar <code>input()</code> ni <code>print()</code>. Usa <code>{{CODE}}</code> como marcador donde se insertará el código del estudiante.<br><br>Si lo dejas vacío, el comportamiento es el habitual: el estudiante maneja la entrada y la salida.';
$string['example_python']       = 'Ejemplo (Python)';
$string['student_writes_only']  = 'El estudiante solo escribe:';
$string['testcase_pass_rates']  = 'Rendimiento por caso de prueba';
$string['passed']               = 'Aprobados';
$string['failed']               = 'Fallidos';

$string['invalidsubmission']    = 'La entrega solicitada no existe o no pertenece a esta actividad.';
$string['reset_submissions']    = 'Eliminar todas las entregas, sus resultados y sus calificaciones';

// Ejecución y evaluación.
$string['language_not_allowed'] = 'El lenguaje seleccionado no está permitido en esta actividad.';
$string['code_too_large']       = 'El código es demasiado largo (máximo 64KB).';
$string['no_testcases']         = 'Esta actividad no tiene casos de prueba configurados.';
$string['max_attempts_reached'] = 'Has alcanzado el límite de intentos permitidos.';
$string['submission_saved']     = '¡Tu entrega ha sido registrada exitosamente!';
$string['executor_connection_error'] = 'No se pudo conectar con el servidor de ejecución de código. Contacta al administrador.';
$string['executor_timeout']     = 'El servidor de ejecución tardó demasiado. Inténtalo más tarde.';
$string['executor_not_configured'] = 'El servidor de ejecución de código no está configurado. Un administrador debe indicar la URL del servidor Judge0 propio en los ajustes de CodeLab.';

// Configuración de administración.
$string['admin_settings']       = 'Configuración de CodeLab';
$string['judge0_api_url']       = 'URL de la API Judge0';
$string['judge0_api_url_desc']  = 'URL base de tu servidor Judge0 auto-hospedado (ej: http://judge0_server:2358 con el overlay local, o http://TU_HOST_JUDGE0:2358).';
$string['judge0_api_key']       = 'Clave API de Judge0';
$string['judge0_api_key_desc']  = 'Token de autenticación (X-Auth-Token) si tu servidor lo exige. En un auto-hospedado sin token, déjalo vacío.';

// Recomendaciones por IA — configuración de administración.
$string['ai_settings']       = 'Recomendaciones por IA';
$string['ai_settings_desc']  = 'Genera retroalimentación cualitativa sobre el código del estudiante tras cada entrega. Estas recomendaciones NUNCA modifican la calificación.';
$string['ai_enabled']        = 'Habilitar recomendaciones por IA';
$string['ai_enabled_desc']   = 'Interruptor general. Si está desactivado, ninguna actividad podrá generar recomendaciones aunque el docente lo pida.';
$string['ai_provider']       = 'Proveedor de IA';
$string['ai_provider_desc']  = 'Servicio que generará las recomendaciones. Debes tener una clave de API válida del proveedor seleccionado.';
$string['ai_provider_claude'] = 'Claude (Anthropic)';
$string['ai_provider_gemini'] = 'Gemini (Google)';
$string['ai_provider_openai'] = 'OpenAI';
$string['ai_api_key']        = 'Clave de API';
$string['ai_api_key_desc']   = 'Clave de autenticación del proveedor seleccionado. Se almacena de forma oculta.';
$string['ai_model']          = 'Modelo (opcional)';
$string['ai_model_desc']     = 'Nombre del modelo a utilizar. Déjalo vacío para usar un modelo por defecto adecuado del proveedor.';

// Recomendaciones por IA — configuración por actividad.
$string['ai_feedback_section']       = 'Recomendaciones por IA';
$string['ai_disabled_site_notice']   = 'Las recomendaciones por IA están desactivadas a nivel de sitio. Un administrador debe habilitarlas y configurar un proveedor en los ajustes de CodeLab.';
$string['ai_feedback_enabled']       = 'Generar recomendaciones por IA';
$string['ai_feedback_enabled_label'] = 'Activar para esta actividad';
$string['ai_feedback_enabled_help']  = 'Si se activa, al entregar se generará una retroalimentación cualitativa sobre el código según los criterios que selecciones. La calificación se calcula solo con los casos de prueba; la IA nunca la modifica.';
$string['ai_criteria']       = 'Criterios a evaluar';
$string['ai_criteria_help']  = 'Selecciona qué aspectos cualitativos debe comentar la IA. Solo se evaluarán los criterios marcados.';
$string['ai_criterion_readability']    = 'Legibilidad y estilo';
$string['ai_criterion_efficiency']     = 'Eficiencia y complejidad';
$string['ai_criterion_best_practices'] = 'Buenas prácticas del lenguaje';
$string['ai_criterion_naming']         = 'Nombres de variables y funciones';
$string['ai_criterion_error_handling'] = 'Manejo de errores y casos borde';
$string['ai_criterion_modularity']     = 'Modularidad y estructura';

// Recomendaciones por IA — interfaz y mensajes.
$string['ai_feedback_title']      = 'Recomendaciones de la IA';
$string['ai_feedback_disclaimer'] = 'Sugerencias cualitativas generadas automáticamente. No afectan tu calificación.';
$string['ai_connection_error']    = 'No se pudo contactar con el servicio de IA. Tu entrega y tu nota se registraron correctamente.';
$string['ai_response_error']      = 'El servicio de IA devolvió una respuesta inválida. Tu entrega y tu nota se registraron correctamente.';

// Privacidad.
$string['privacy:metadata:codelab_submissions']              = 'Entregas de código de los estudiantes.';
$string['privacy:metadata:codelab_submissions:userid']       = 'ID del usuario que realiza la entrega.';
$string['privacy:metadata:codelab_submissions:code']         = 'El código fuente enviado por el estudiante.';
$string['privacy:metadata:codelab_submissions:language']     = 'Lenguaje de programación utilizado.';
$string['privacy:metadata:codelab_submissions:grade']        = 'Calificación obtenida.';
$string['privacy:metadata:codelab_submissions:timecreated']  = 'Fecha de creación de la entrega.';
$string['privacy:metadata:codelab_submissions:timemodified'] = 'Fecha de última modificación.';
$string['privacy:metadata:judge0_api']       = 'El código del estudiante y la entrada estándar se envían al servidor externo Judge0 para su ejecución.';
$string['privacy:metadata:judge0_api:code']  = 'El código fuente que se ejecuta.';
$string['privacy:metadata:judge0_api:stdin'] = 'La entrada estándar proporcionada al programa.';
$string['privacy:metadata:ai_api']            = 'Si el docente activa las recomendaciones por IA, el código de la entrega y el enunciado del ejercicio se envían al proveedor de IA configurado para generar retroalimentación cualitativa.';
$string['privacy:metadata:ai_api:code']       = 'El código fuente enviado por el estudiante.';
$string['privacy:metadata:ai_api:statement']  = 'El enunciado del ejercicio.';
$string['privacy:metadata:codelab_submissions:ai_feedback'] = 'Recomendación cualitativa generada por IA para la entrega.';
$string['privacy:metadata:codelab_submissions:attempt_number']     = 'Número de intento de la entrega.';
$string['privacy:metadata:codelab_submissions:status']             = 'Estado de la entrega (borrador, entregada o calificada).';
$string['privacy:metadata:codelab_submissions:grade_override']     = 'Calificación asignada manualmente por el docente.';
$string['privacy:metadata:codelab_submissions:feedback']           = 'Retroalimentación escrita por el docente sobre la entrega.';
$string['privacy:metadata:codelab_submissions:ai_feedback_status'] = 'Estado de la generación de la recomendación por IA.';
$string['privacy:metadata:codelab_submissions:ai_feedback_time']   = 'Fecha en que se generó la recomendación por IA.';
$string['privacy:metadata:codelab_submissions:tests_passed']       = 'Número de casos de prueba superados.';
$string['privacy:metadata:codelab_submissions:tests_total']        = 'Número total de casos de prueba evaluados.';

$string['privacy:metadata:codelab_submission_results']                = 'Resultado de cada caso de prueba para las entregas del estudiante.';
$string['privacy:metadata:codelab_submission_results:status']         = 'Resultado del caso de prueba (superado, fallido, error, tiempo agotado o error de compilación).';
$string['privacy:metadata:codelab_submission_results:actual_output']  = 'La salida que produjo el programa escrito por el estudiante.';
$string['privacy:metadata:codelab_submission_results:stderr']         = 'La salida de error que produjo el programa escrito por el estudiante.';
$string['privacy:metadata:codelab_submission_results:compile_output'] = 'Los mensajes del compilador al procesar el código del estudiante.';
$string['privacy:metadata:codelab_submission_results:execution_time'] = 'Tiempo que tardó en ejecutarse el código, en segundos.';
$string['privacy:metadata:codelab_submission_results:memory_used']    = 'Memoria que consumió la ejecución del código, en kilobytes.';
$string['privacy:metadata:codelab_submission_results:timecreated']    = 'Fecha en que se registró el resultado del caso de prueba.';
$string['privacy:path:submissions']          = 'Entregas de código';
