<?php
// This file is part of Moodle - http://moodle.org/
//
// Datos de demostración para el entorno LOCAL de desarrollo.
// Crea (de forma idempotente) un curso, un estudiante matriculado y una
// actividad CodeLab lista para probar. NO usar en producción.
//
// Lo ejecuta el instalador (scripts/install-plugin.sh) como el usuario de
// Apache, después de instalar el plugin.
//
// @package   mod_codelab
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir  . '/enrollib.php');
require_once($CFG->libdir  . '/gradelib.php');

global $DB, $CFG;

// Actuar como administrador (tiene todas las capacidades).
$USER = get_admin();

// ---- Parámetros del seed (cámbialos si quieres) --------------------
$COURSE_SHORT  = 'CODELAB-DEMO';
$COURSE_NAME   = 'Curso de prueba CodeLab';
$STUDENT_USER  = 'estudiante';
$STUDENT_PASS  = 'Estudiante1!';
$ACTIVITY_NAME = 'Suma de dos números';

// ---- 1. Curso ------------------------------------------------------
$course = $DB->get_record('course', ['shortname' => $COURSE_SHORT]);
if (!$course) {
    $data = new stdClass();
    $data->fullname      = $COURSE_NAME;
    $data->shortname     = $COURSE_SHORT;
    $data->category      = 1;                 // Categoría "Miscelánea" (id 1 por defecto).
    $data->summary       = 'Curso de demostración con una actividad CodeLab lista para probar.';
    $data->summaryformat = FORMAT_HTML;
    $data->format        = 'topics';
    $data->numsections   = 1;
    $data->visible       = 1;
    $course = create_course($data);
    echo "[seed] Curso creado: {$course->shortname} (id {$course->id})\n";
} else {
    echo "[seed] Curso ya existe: {$course->shortname} (id {$course->id})\n";
}

// ---- 2. Estudiante -------------------------------------------------
$user = $DB->get_record('user', ['username' => $STUDENT_USER, 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$user) {
    $u = new stdClass();
    $u->auth       = 'manual';
    $u->confirmed  = 1;
    $u->mnethostid = $CFG->mnet_localhost_id;
    $u->username   = $STUDENT_USER;
    $u->password   = $STUDENT_PASS;           // user_create_user la cifra.
    $u->firstname  = 'Estudiante';
    $u->lastname   = 'Demo';
    $u->email      = 'estudiante@codelab.local';
    $u->lang       = 'es';
    $uid  = user_create_user($u, true, false);
    $user = $DB->get_record('user', ['id' => $uid]);
    echo "[seed] Estudiante creado: {$STUDENT_USER} / {$STUDENT_PASS}\n";
} else {
    echo "[seed] Estudiante ya existe: {$STUDENT_USER}\n";
}

// ---- 3. Matrícula del estudiante -----------------------------------
$context       = context_course::instance($course->id);
$studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
if (!is_enrolled($context, $user)) {
    $enrol    = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    if (!$instance) {
        $enrol->add_default_instance($course);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    }
    $enrol->enrol_user($instance, $user->id, $studentroleid);
    echo "[seed] Estudiante matriculado como 'student'.\n";
} else {
    echo "[seed] Estudiante ya matriculado.\n";
}

// ---- 4. Actividad CodeLab con ejercicio y casos --------------------
$existing = $DB->get_record('codelab', ['course' => $course->id, 'name' => $ACTIVITY_NAME]);
if (!$existing) {
    $mi = new stdClass();
    // Campos estándar del módulo.
    $mi->modulename          = 'codelab';
    $mi->module              = $DB->get_field('modules', 'id', ['name' => 'codelab']);
    $mi->course              = $course->id;
    $mi->section             = 0;             // Sección 0 (General): siempre existe.
    $mi->visible             = 1;
    $mi->visibleoncoursepage = 1;
    $mi->cmidnumber          = '';
    $mi->groupmode           = 0;
    $mi->groupingid          = 0;
    $mi->availability        = null;
    $mi->showdescription     = 0;
    $mi->downloadcontent     = 1;
    // Completion (desactivado).
    $mi->completion                 = 0;
    $mi->completionview             = 0;
    $mi->completionexpected         = 0;
    $mi->completiongradeitemnumber  = null;
    $mi->completionpassgrade        = 0;
    // Campos propios del plugin.
    $mi->name              = $ACTIVITY_NAME;
    $mi->intro             = '<p>Escribe un programa que lea dos enteros separados por un espacio '
                           . 'y muestre su suma.</p><p>Ejemplo: entrada <code>3 5</code> → salida <code>8</code>.</p>';
    $mi->introformat       = FORMAT_HTML;
    $mi->default_language  = 'python';
    $mi->languages_allowed = json_encode(['python', 'javascript']);
    // Código inicial = solución que funciona, para poder probar de inmediato (solo demo).
    $mi->starter_code      = "# Lee dos enteros separados por espacio y muestra su suma\n"
                           . "a, b = map(int, input().split())\n"
                           . "print(a + b)\n";
    $mi->solution_code     = "a, b = map(int, input().split())\nprint(a + b)\n";
    $mi->runner_code       = '';
    $mi->time_limit        = 5;
    $mi->memory_limit      = 128;
    $mi->max_attempts      = 0;
    $mi->grading_method    = 'best';
    $mi->grade             = 100;
    $mi->testcases_visible = 1;
    $mi->ai_feedback_enabled = 0;
    $mi->ai_criteria       = null;
    $mi->allow_submissions_from_date = 0;
    $mi->duedate           = 0;
    $mi->cutoffdate        = 0;

    $mi = add_moduleinfo($mi, $course);
    $codelabid = $mi->instance;

    $tcs = [
        ['name' => 'Ejemplo 3 + 5',    'stdin' => '3 5',     'expected_output' => '8',   'is_hidden' => 0, 'ordering' => 0],
        ['name' => 'Ejemplo 10 + 20',  'stdin' => '10 20',   'expected_output' => '30',  'is_hidden' => 0, 'ordering' => 1],
        ['name' => 'Oculto 100 + 250', 'stdin' => '100 250', 'expected_output' => '350', 'is_hidden' => 1, 'ordering' => 2],
    ];
    foreach ($tcs as $t) {
        $rec = (object) array_merge([
            'codelabid'   => $codelabid,
            'description' => '',
            'points'      => 1,
            'timecreated' => time(),
        ], $t);
        $DB->insert_record('codelab_testcases', $rec);
    }
    echo "[seed] Actividad '{$ACTIVITY_NAME}' creada (codelabid {$codelabid}) con " . count($tcs) . " casos de prueba.\n";
} else {
    echo "[seed] Actividad '{$ACTIVITY_NAME}' ya existe (codelabid {$existing->id}).\n";
}

echo "[seed] Datos de demostración listos.\n";
