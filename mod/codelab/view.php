<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Vista principal del módulo CodeLab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/submission_manager.php');

$id   = optional_param('id', 0, PARAM_INT);
$clid = optional_param('c', 0, PARAM_INT);

if ($id) {
    $cm      = get_coursemodule_from_id('codelab', $id, 0, false, MUST_EXIST);
    $course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $codelab = $DB->get_record('codelab', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $codelab = $DB->get_record('codelab', ['id' => $clid], '*', MUST_EXIST);
    $cm      = get_coursemodule_from_instance('codelab', $codelab->id, 0, false, MUST_EXIST);
    $course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/codelab:view', $context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/codelab/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($codelab->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('mod-codelab');
$PAGE->requires->css('/mod/codelab/styles.css');

$isteacher = has_capability('mod/codelab:viewallsubmissions', $context);

$testcases_all     = $DB->get_records('codelab_testcases', ['codelabid' => $codelab->id], 'ordering ASC, id ASC');
$testcases_visible = array_filter($testcases_all, fn($tc) => !$tc->is_hidden);
$hidden_count      = count($testcases_all) - count($testcases_visible);

// El ajuste «Mostrar casos de prueba a estudiantes» decide si el estudiante puede ver el nombre,
// la entrada y la salida esperada de cada caso. execute.php ya lo respetaba al devolver los
// resultados (línea 124), pero la vista no lo miraba y publicaba las salidas esperadas en el
// atributo data-codelab-config del HTML. Cuando está desactivado, todos los casos se tratan como
// ocultos: el estudiante sigue viendo cuántos son y cuántos ha superado, pero no su contenido.
$show_testcases = (bool)$codelab->testcases_visible;

$languages_allowed = !empty($codelab->languages_allowed)
    ? json_decode($codelab->languages_allowed, true)
    : [$codelab->default_language];

$submission_manager = new \mod_codelab\submission_manager($codelab, $context);

if ($isteacher) {
    render_teacher_view($codelab, $cm, $course, $context, $testcases_all, $submission_manager);
} else {
    render_student_view(
        $codelab, $cm, $course, $context,
        $show_testcases ? $testcases_visible : [],
        $show_testcases ? $hidden_count : count($testcases_all),
        $languages_allowed, $submission_manager
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// VISTA ESTUDIANTE
// ─────────────────────────────────────────────────────────────────────────────
function render_student_view(
    stdClass $codelab,
    stdClass $cm,
    stdClass $course,
    context_module $context,
    array $testcases,
    int $hidden_count,
    array $languages,
    \mod_codelab\submission_manager $submission_manager
): void {
    global $OUTPUT, $USER, $PAGE;

    $submission   = $submission_manager->get_best_submission($USER->id);
    $attempts     = $submission_manager->count_attempts($USER->id);
    $max_attempts = (int)$codelab->max_attempts;
    $can_submit   = ($max_attempts === 0 || $attempts < $max_attempts);

    $now          = time();
    $past_due     = $codelab->duedate > 0 && $now > $codelab->duedate;
    $past_cutoff  = $codelab->cutoffdate > 0 && $now > $codelab->cutoffdate;
    $not_open_yet = $codelab->allow_submissions_from_date > 0
        && $now < $codelab->allow_submissions_from_date;

    if ($past_cutoff || $not_open_yet) {
        $can_submit = false;
    }

    $editor_code = ($submission && $submission->code)
        ? $submission->code
        : ($codelab->starter_code ?? '');

    // Preparar casos de prueba para JS.
    $tc_for_js = [];
    foreach ($testcases as $tc) {
        $tc_for_js[] = [
            'id'              => (int)$tc->id,
            'name'            => $tc->name,
            'stdin'           => $tc->stdin,
            'expected_output' => $tc->expected_output,
            'points'          => (float)$tc->points,
        ];
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($codelab->name));

    if ($codelab->intro) {
        echo $OUTPUT->box(
            format_module_intro('codelab', $codelab, $cm->id),
            'generalbox mod_introbox'
        );
    }

    // Aviso si hay runner automático activo.
    if (!empty(trim($codelab->runner_code ?? ''))) {
        echo '<div class="alert alert-success mb-2" style="font-size:0.9rem;">';
        echo '<i class="fa fa-magic mr-1"></i> ' . get_string('runner_active_notice', 'mod_codelab');
        echo '</div>';
    }

    // Alertas de fecha.
    if ($not_open_yet) {
        echo $OUTPUT->notification(
            get_string('before_allow_from', 'mod_codelab',
                userdate($codelab->allow_submissions_from_date)),
            'info'
        );
    } elseif ($past_cutoff) {
        echo $OUTPUT->notification(get_string('past_cutoff', 'mod_codelab'), 'error');
    } elseif ($past_due) {
        echo $OUTPUT->notification(get_string('past_due', 'mod_codelab'), 'warning');
    }

    // Estado de la última entrega.
    if ($submission && $submission->status === 'submitted') {
        // Nota final: si el profesor la modificó manualmente, usar grade_override.
        $effective_grade = $submission->grade_override !== null
            ? (float)$submission->grade_override
            : (float)($submission->grade ?? 0);

        $grade_display = $submission->grade !== null || $submission->grade_override !== null
            ? number_format($effective_grade, 1) . ' / ' . $codelab->grade
            : get_string('pending', 'mod_codelab');

        $has_override  = $submission->grade_override !== null;
        $has_feedback  = !empty(trim($submission->feedback ?? ''));

        echo '<div class="codelab-submission-status alert alert-info mb-2">';
        echo '<div class="d-flex flex-wrap align-items-center gap-3">';

        echo '<span><strong>' . get_string('last_submission', 'mod_codelab') . ':</strong> ';
        echo userdate($submission->timemodified) . '</span>';

        echo '<span><strong>' . get_string('grade', 'mod_codelab') . ':</strong> ';
        if ($has_override) {
            echo '<span class="badge badge-warning text-dark">' . $grade_display . '</span>';
            echo ' <small class="text-muted">' . get_string('grade_manual_note', 'mod_codelab') . '</small>';
        } else {
            echo $grade_display;
        }
        echo '</span>';

        echo '<span><strong>' . get_string('attempts', 'mod_codelab') . ':</strong> ';
        echo $attempts;
        if ($max_attempts > 0) {
            echo ' / ' . $max_attempts;
        }
        echo '</span>';

        echo '</div>';

        // Retroalimentación del profesor.
        if ($has_feedback) {
            echo '<hr class="my-2">';
            echo '<div class="codelab-feedback mt-1">';
            echo '<strong><i class="fa fa-comment-o mr-1"></i> '
                . get_string('teacher_feedback', 'mod_codelab') . ':</strong>';
            echo '<div class="codelab-feedback-text mt-1 p-2" style="';
            echo 'background:rgba(255,255,255,0.5);border-left:4px solid #0f6cbf;';
            echo 'border-radius:4px;white-space:pre-wrap;">';
            echo format_text(s($submission->feedback), FORMAT_PLAIN);
            echo '</div>';
            echo '</div>';
        }

        // Recomendaciones por IA guardadas de la última entrega (cualitativas, no afectan la nota).
        if (!empty(trim($submission->ai_feedback ?? ''))) {
            echo '<hr class="my-2">';
            echo '<div class="codelab-ai-feedback-saved mt-1">';
            echo '<strong>🤖 ' . get_string('ai_feedback_title', 'mod_codelab') . '</strong>';
            echo '<div class="codelab-ai-feedback mt-1 p-2" style="';
            echo 'background:rgba(255,255,255,0.5);border-left:4px solid #8b5cf6;border-radius:4px;">';
            echo format_text($submission->ai_feedback, FORMAT_MARKDOWN);
            echo '</div>';
            echo '<div class="text-muted small mt-1"><i class="fa fa-info-circle"></i> ';
            echo get_string('ai_feedback_disclaimer', 'mod_codelab') . '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    // Selector de lenguaje.
    $lang_labels = [
        'python'     => 'Python 3',
        'javascript' => 'JavaScript (Node.js)',
        'java'       => 'Java',
        'c'          => 'C',
        'cpp'        => 'C++',
        'php'        => 'PHP',
        'csharp'     => 'C# (Mono)',
        'ruby'       => 'Ruby',
        'go'         => 'Go',
        'kotlin'     => 'Kotlin',
    ];

    $lang_options = '';
    foreach ($languages as $lang) {
        $selected     = ($lang === $codelab->default_language) ? ' selected' : '';
        $label        = $lang_labels[$lang] ?? strtoupper($lang);
        $lang_options .= '<option value="' . $lang . '"' . $selected . '>' . $label . '</option>';
    }

    $total_visible = count($testcases) + $hidden_count;

    // Configuracion para el JS. Viaja en un atributo data- del contenedor en lugar de en una
    // variable global inyectada con <script> en linea (INC-13): asi el editor la lee del DOM
    // cuando arranca y no depende del orden en que Moodle emita los scripts.
    $total_points = array_sum(array_column($tc_for_js, 'points')) + $hidden_count;

    $cfg = [
        'cmid'        => $cm->id,
        'codelabid'   => $codelab->id,
        'editorEl'    => 'codelab-monaco-editor',
        'language'    => $codelab->default_language,
        'initialCode' => $editor_code,
        'testcases'   => array_values($tc_for_js),
        'hiddenCount' => $hidden_count,
        'totalPoints' => $total_points,
        'maxGrade'    => (float)$codelab->grade,
        'canSubmit'   => (bool)$can_submit,
        'wwwroot'     => (new moodle_url('/'))->out(false),
        'sesskey'     => sesskey(),
    ];

    // El editor se carga por la API de Moodle; al pedirlo despues de la cabecera, Moodle lo
    // emite al final del <body>, cuando el contenedor y su config ya estan en el DOM.
    $PAGE->requires->js(new moodle_url('/mod/codelab/js/editor.js'));

    // Toolbar.
    echo html_writer::start_div('codelab-workspace', [
        'id'                  => 'codelab-workspace',
        'data-codelab-config' => json_encode($cfg, JSON_HEX_TAG | JSON_HEX_APOS),
    ]);
    echo '<div class="codelab-toolbar d-flex align-items-center mb-2" style="flex-wrap:wrap;gap:8px;">';
    echo '<label for="codelab-lang-select" class="mb-0 font-weight-bold">';
    echo '<i class="fa fa-code"></i> ' . get_string('language', 'mod_codelab') . ':</label>';
    echo '<select id="codelab-lang-select" class="form-control form-control-sm" style="width:auto;">';
    echo $lang_options;
    echo '</select>';
    echo '<button id="codelab-run-btn" class="btn btn-success btn-sm" type="button">';
    echo '<i class="fa fa-play"></i> ' . get_string('run_and_test', 'mod_codelab') . '</button>';

    if ($can_submit) {
        echo '<button id="codelab-submit-btn" class="btn btn-primary btn-sm" type="button">';
        echo '<i class="fa fa-paper-plane"></i> ' . get_string('submit_solution', 'mod_codelab') . '</button>';
    }

    echo '</div>';

    // Paneles: editor + resultados.
    echo '<div class="codelab-panels">';

    // Panel izquierdo: editor Monaco.
    echo '<div class="codelab-editor-panel">';
    echo '<div id="codelab-monaco-editor" class="codelab-monaco-container"></div>';
    echo '</div>';

    // Panel derecho: casos de prueba y salida.
    echo '<div class="codelab-results-panel" id="codelab-results-panel">';
    echo '<div class="codelab-results-header">';
    echo '<span class="font-weight-bold"><i class="fa fa-flask"></i> '
        . get_string('testcases', 'mod_codelab') . '</span>';
    echo '<span id="codelab-score-badge" class="badge badge-secondary ml-2">0 / ' . $total_visible . '</span>';
    echo '</div>';
    echo '<div id="codelab-testcases-list" class="codelab-testcases-results"></div>';
    echo '<div class="codelab-output-section mt-3">';
    echo '<div class="font-weight-bold mb-1"><i class="fa fa-terminal"></i> '
        . get_string('program_output', 'mod_codelab') . '</div>';
    echo '<pre id="codelab-output" class="codelab-output-pre"></pre>';
    echo '</div>';

    // Sección de recomendaciones IA (se rellena tras entregar).
    if (\mod_codelab\ai\feedback_generator::is_enabled_for($codelab)) {
        echo '<div class="codelab-ai-section mt-3" id="codelab-ai-section" style="display:none;">';
        echo '<div class="font-weight-bold mb-1">🤖 ' . get_string('ai_feedback_title', 'mod_codelab') . '</div>';
        echo '<div id="codelab-ai-feedback" class="codelab-ai-feedback"></div>';
        echo '</div>';
    }

    echo '</div>';

    echo '</div>';
    echo '</div>';

    echo $OUTPUT->footer();
}

// ─────────────────────────────────────────────────────────────────────────────
// VISTA PROFESOR
// ─────────────────────────────────────────────────────────────────────────────
function render_teacher_view(
    stdClass $codelab,
    stdClass $cm,
    stdClass $course,
    context_module $context,
    array $testcases,
    \mod_codelab\submission_manager $submission_manager
): void {
    global $OUTPUT, $DB;

    $action = optional_param('action', 'list', PARAM_ALPHA);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($codelab->name));

    if ($codelab->intro) {
        echo $OUTPUT->box(
            format_module_intro('codelab', $codelab, $cm->id),
            'generalbox mod_introbox'
        );
    }

    $tabs = [
        new tabobject(
            'list',
            new moodle_url('/mod/codelab/view.php', ['id' => $cm->id, 'action' => 'list']),
            get_string('submissions', 'mod_codelab')
        ),
        new tabobject(
            'stats',
            new moodle_url('/mod/codelab/view.php', ['id' => $cm->id, 'action' => 'stats']),
            get_string('statistics', 'mod_codelab')
        ),
    ];
    echo $OUTPUT->tabtree($tabs, $action);

    switch ($action) {
        case 'stats':
            render_teacher_stats($codelab, $cm, $testcases, $submission_manager, $OUTPUT, $DB);
            break;
        default:
            render_teacher_list($codelab, $cm, $testcases, $submission_manager, $OUTPUT, $DB);
    }

    echo $OUTPUT->footer();
}

// ─────────────────────────────────────────────────────────────────────────────
// LISTA DE ENTREGAS
// ─────────────────────────────────────────────────────────────────────────────
function render_teacher_list(
    stdClass $codelab,
    stdClass $cm,
    array $testcases,
    \mod_codelab\submission_manager $submission_manager,
    core_renderer $output,
    moodle_database $DB
): void {
    $submissions = $submission_manager->get_all_latest_submissions();

    if (empty($submissions)) {
        echo $output->notification(get_string('no_submissions_yet', 'mod_codelab'), 'info');
        return;
    }

    $table             = new html_table();
    $table->attributes = ['class' => 'generaltable codelab-submissions-table'];
    $table->head       = [
        get_string('student', 'mod_codelab'),
        get_string('submitted', 'mod_codelab'),
        get_string('language', 'mod_codelab'),
        get_string('tests_passed', 'mod_codelab'),
        get_string('grade', 'mod_codelab'),
        get_string('actions', 'mod_codelab'),
    ];

    // Los usuarios, en una sola consulta en vez de una por fila (MDL-07).
    $users = $DB->get_records_list('user', 'id', array_column($submissions, 'userid'));

    foreach ($submissions as $sub) {
        $user     = $users[$sub->userid] ?? null;
        // html_writer::table() no escapa el contenido de las celdas (SEC-16).
        $fullname = $user ? s(fullname($user)) : get_string('unknown_user', 'mod_codelab');

        $tests    = $sub->tests_passed . ' / ' . $sub->tests_total;
        $grade    = $sub->grade !== null
            ? '<strong>' . number_format((float)$sub->grade, 1) . '</strong> / ' . $codelab->grade
            : '<span class="text-muted">-</span>';

        $view_url     = new moodle_url('/mod/codelab/submission.php', ['id' => $cm->id, 'sid' => $sub->id]);
        $table->data[] = [
            $fullname,
            userdate($sub->timemodified),
            strtoupper($sub->language),
            $tests,
            $grade,
            html_writer::link($view_url, get_string('view', 'mod_codelab'), ['class' => 'btn btn-sm btn-secondary']),
        ];
    }

    echo html_writer::table($table);
}

// ─────────────────────────────────────────────────────────────────────────────
// ESTADÍSTICAS
// ─────────────────────────────────────────────────────────────────────────────
function render_teacher_stats(
    stdClass $codelab,
    stdClass $cm,
    array $testcases,
    \mod_codelab\submission_manager $submission_manager,
    core_renderer $output,
    moodle_database $DB
): void {
    $stats = $submission_manager->get_statistics();

    // La tasa de aprobación solo existe si el curso tiene configurada una nota para aprobar.
    // Cuando no la hay se dice, en vez de enseñar un porcentaje que nadie podría justificar.
    $pass_rate_value = $stats['pass_rate'] === null
        ? get_string('pass_rate_unavailable', 'mod_codelab')
        : number_format($stats['pass_rate'], 1) . '%';

    $cards = [
        ['label' => get_string('total_students', 'mod_codelab'),    'value' => $stats['total_students'],                          'color' => 'primary'],
        ['label' => get_string('total_submissions', 'mod_codelab'), 'value' => $stats['total_submissions'],                       'color' => 'info'],
        ['label' => get_string('avg_grade', 'mod_codelab'),         'value' => number_format($stats['avg_grade'], 1),             'color' => 'success'],
        ['label' => get_string('pass_rate', 'mod_codelab'),         'value' => $pass_rate_value,                                  'color' => 'warning'],
    ];

    echo '<div class="codelab-stats row">';
    foreach ($cards as $card) {
        echo '<div class="col-md-3 mb-3">';
        echo '<div class="card border-' . $card['color'] . '">';
        echo '<div class="card-body text-center">';
        echo '<h3 class="card-title text-' . $card['color'] . '">' . $card['value'] . '</h3>';
        echo '<p class="card-text text-muted">' . $card['label'] . '</p>';
        echo '</div></div></div>';
    }
    echo '</div>';

    if ($stats['pass_rate'] === null) {
        echo $OUTPUT->notification(
            get_string('pass_rate_no_gradepass', 'mod_codelab'),
            'info'
        );
    }

    if (!empty($stats['testcase_stats'])) {
        echo '<h4 class="mt-3">' . get_string('testcase_pass_rates', 'mod_codelab') . '</h4>';
        $table             = new html_table();
        $table->attributes = ['class' => 'generaltable'];
        $table->head       = [
            get_string('testcase', 'mod_codelab'),
            get_string('pass_rate', 'mod_codelab'),
            get_string('passed', 'mod_codelab'),
            get_string('failed', 'mod_codelab'),
        ];
        foreach ($stats['testcase_stats'] as $ts) {
            $rate            = $ts['total'] > 0 ? round($ts['passed'] / $ts['total'] * 100, 1) . '%' : '-';
            $table->data[]   = [$ts['name'], $rate, $ts['passed'], $ts['failed']];
        }
        echo html_writer::table($table);
    }
}
