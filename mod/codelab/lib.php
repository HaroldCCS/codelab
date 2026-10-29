<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Funciones principales del módulo CodeLab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Agrega una nueva instancia de codelab al curso.
 */
function codelab_add_instance(stdClass $codelab, ?mod_codelab_mod_form $mform = null): int {
    global $DB;

    $codelab->timecreated  = time();
    $codelab->timemodified = time();

    $codelab->id = $DB->insert_record('codelab', $codelab);

    codelab_grade_item_update($codelab);

    if (!empty($mform)) {
        codelab_save_testcases($codelab->id, $mform->get_testcases_data());
    }

    return $codelab->id;
}

/**
 * Actualiza una instancia existente de codelab.
 */
function codelab_update_instance(stdClass $codelab, ?mod_codelab_mod_form $mform = null): bool {
    global $DB;

    $codelab->timemodified = time();
    $codelab->id = $codelab->instance;

    $result = $DB->update_record('codelab', $codelab);

    codelab_grade_item_update($codelab);

    if (!empty($mform)) {
        codelab_save_testcases($codelab->id, $mform->get_testcases_data());
    }

    return $result;
}

/**
 * Elimina una instancia de codelab del curso.
 */
function codelab_delete_instance(int $id): bool {
    global $DB;

    if (!$codelab = $DB->get_record('codelab', ['id' => $id])) {
        return false;
    }

    // Borrar los resultados de todas las entregas de esta actividad.
    // (delete_records no admite un array como valor de condición; usamos delete_records_list.)
    $submissionids = $DB->get_fieldset_select('codelab_submissions', 'id', 'codelabid = ?', [$id]);
    if (!empty($submissionids)) {
        $DB->delete_records_list('codelab_submission_results', 'submission_id', $submissionids);
    }
    $DB->delete_records('codelab_submissions', ['codelabid' => $id]);
    $DB->delete_records('codelab_testcases', ['codelabid' => $id]);
    $DB->delete_records('codelab', ['id' => $id]);

    codelab_grade_item_delete($codelab);

    return true;
}

/**
 * Devuelve si el usuario puede añadir esta actividad.
 */
// Devuelve mixed, no ?bool: FEATURE_MOD_PURPOSE espera una cadena
// ('assessment') y con ?bool PHP la convertía en true sin avisar.
function codelab_supports(string $feature): mixed {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Crea/actualiza el ítem de calificación en el libro de notas.
 */
function codelab_grade_item_update(stdClass $codelab, mixed $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $codelab->name,
        'idnumber' => $codelab->cmidnumber ?? '',
    ];

    if (!isset($codelab->courseid)) {
        $codelab->courseid = $codelab->course;
    }

    if ($codelab->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $codelab->grade;
        $params['grademin']  = 0;
    } else if ($codelab->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$codelab->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/codelab',
        $codelab->course,
        'mod',
        'codelab',
        $codelab->id,
        0,
        $grades,
        $params
    );
}

/**
 * Elimina el ítem de calificación del libro de notas.
 */
function codelab_grade_item_delete(stdClass $codelab): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/codelab',
        $codelab->course,
        'mod',
        'codelab',
        $codelab->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Actualiza la calificación de un usuario en el libro de notas.
 */
function codelab_update_grades(stdClass $codelab, int $userid = 0): void {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($grades = codelab_get_user_grades($codelab, $userid)) {
        codelab_grade_item_update($codelab, $grades);
    } else {
        codelab_grade_item_update($codelab);
    }
}

/**
 * Obtiene las calificaciones de usuario para el libro de notas.
 */
function codelab_get_user_grades(stdClass $codelab, int $userid = 0): array|false {
    global $DB;

    require_once(__DIR__ . '/classes/grader.php');

    // Este es el gancho que Moodle invoca en un recálculo completo del libro de notas, sin pasar
    // por grader. Antes hacía siempre MAX(grade): ignoraba el método de calificación configurado
    // y la corrección manual del docente, así que un «Recalcular todas las notas ahora» podía
    // sustituir en silencio un 40 de «primer intento» por un 90, o tirar un grade_override.
    // Ahora solo averigua QUIÉNES tienen entregas y delega el CUÁNTO en grader, que es la fuente
    // única del cálculo (MDL-03).
    $params = ['codelabid' => $codelab->id, 'status' => 'submitted'];
    $sql = "SELECT userid, MAX(timemodified) AS dategraded
              FROM {codelab_submissions}
             WHERE codelabid = :codelabid
               AND status = :status";

    if ($userid) {
        $sql .= " AND userid = :userid";
        $params['userid'] = $userid;
    }

    $sql .= " GROUP BY userid";

    $grades = [];
    $records = $DB->get_records_sql($sql, $params);

    foreach ($records as $record) {
        $rawgrade = \mod_codelab\grader::compute_final_grade($codelab, (int)$record->userid);

        if ($rawgrade === null) {
            continue;
        }

        $grades[$record->userid] = [
            'userid'     => $record->userid,
            'rawgrade'   => $rawgrade,
            'dategraded' => $record->dategraded,
        ];
    }

    return empty($grades) ? false : $grades;
}

/**
 * Dibuja las opciones de CodeLab en «Administración del curso → Restablecer».
 *
 * Sin estas tres funciones (definition/defaults/reset_userdata) Moodle no incluye al módulo en el
 * restablecimiento y no avisa de nada: el docente ve «restablecido con éxito» y las entregas del
 * semestre anterior siguen ahí (MDL-02).
 *
 * @param MoodleQuickForm $mform Formulario de restablecimiento del curso.
 */
function codelab_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'codelabheader', get_string('modulenameplural', 'mod_codelab'));
    $mform->addElement(
        'checkbox',
        'reset_codelab_submissions',
        get_string('reset_submissions', 'mod_codelab')
    );
}

/**
 * Valores por defecto de las opciones de restablecimiento de CodeLab.
 *
 * @param stdClass $course Curso que se restablece.
 * @return array
 */
function codelab_reset_course_form_defaults(stdClass $course): array {
    return ['reset_codelab_submissions' => 1];
}

/**
 * Borra los datos de estudiante de CodeLab cuando se restablece el curso.
 *
 * @param stdClass $data Datos del formulario de restablecimiento.
 * @return array Estado por componente, tal como lo espera reset_course_userdata().
 */
function codelab_reset_userdata(stdClass $data): array {
    global $DB;

    $status = [];

    if (empty($data->reset_codelab_submissions)) {
        return $status;
    }

    $codelabs = $DB->get_records('codelab', ['course' => $data->courseid]);

    if ($codelabs) {
        $ids = array_keys($codelabs);
        list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);

        // El detalle por caso de prueba va primero: cuelga de las entregas por `submission_id`,
        // así que borrar las entregas antes lo dejaría huérfano (es el mismo orden que ya usan
        // codelab_delete_instance() y las rutas de borrado de la Privacy API).
        $subids = $DB->get_fieldset_select('codelab_submissions', 'id', "codelabid $insql", $inparams);
        if ($subids) {
            $DB->delete_records_list('codelab_submission_results', 'submission_id', $subids);
        }

        $DB->delete_records_list('codelab_submissions', 'codelabid', $ids);

        // Y las notas del libro de calificaciones, salvo que el formulario ya esté restableciendo
        // el libro entero por su cuenta (en ese caso Moodle lo hace después y sería trabajo doble).
        if (empty($data->reset_gradebook_grades)) {
            foreach ($codelabs as $codelab) {
                codelab_grade_item_update($codelab, 'reset');
            }
        }
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_codelab'),
        'item'      => get_string('reset_submissions', 'mod_codelab'),
        'error'     => false,
    ];

    return $status;
}

/**
 * Guarda los casos de prueba de una actividad.
 */
function codelab_save_testcases(int $codelabid, array $testcases): void {
    global $DB;

    $DB->delete_records('codelab_testcases', ['codelabid' => $codelabid]);

    foreach ($testcases as $order => $tc) {
        if (empty($tc['expected_output']) && empty($tc['name'])) {
            continue;
        }
        $record = new stdClass();
        $record->codelabid       = $codelabid;
        $record->name            = $tc['name'] ?? get_string('testcase', 'mod_codelab') . ' ' . ($order + 1);
        $record->stdin           = $tc['stdin'] ?? '';
        $record->expected_output = trim($tc['expected_output'] ?? '');
        $record->points          = (float)($tc['points'] ?? 1);
        $record->is_hidden       = (int)($tc['is_hidden'] ?? 0);
        $record->ordering        = $order;
        $record->timecreated     = time();
        $DB->insert_record('codelab_testcases', $record);
    }
}

/**
 * Retorna la información de la actividad para la lista del curso.
 */
function codelab_get_coursemodule_info(stdClass $coursemodule): ?cached_cm_info {
    global $DB;

    if (!$codelab = $DB->get_record('codelab', ['id' => $coursemodule->instance], 'id, name, intro, introformat')) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $codelab->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('codelab', $codelab, $coursemodule->id, false);
    }

    return $info;
}
