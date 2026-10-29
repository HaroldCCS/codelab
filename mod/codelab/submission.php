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
 * Vista detallada de una entrega individual (para el profesor).
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/submission_manager.php');

$cmid = required_param('id', PARAM_INT);
$sid  = required_param('sid', PARAM_INT);

$cm      = get_coursemodule_from_id('codelab', $cmid, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$codelab = $DB->get_record('codelab', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/codelab:viewallsubmissions', $context);

$PAGE->set_url('/mod/codelab/submission.php', ['id' => $cmid, 'sid' => $sid]);
$PAGE->set_title(format_string($codelab->name) . ' - ' . get_string('submissions', 'mod_codelab'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/codelab/styles.css');

$submission_manager = new \mod_codelab\submission_manager($codelab, $context);
$data = $submission_manager->get_submission_with_results($sid);

if (!$data) {
    throw new moodle_exception('invalidsubmission', 'mod_codelab');
}

$submission = $data['submission'];
$results    = $data['results'];

$student = $DB->get_record('user', ['id' => $submission->userid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submissions', 'mod_codelab') . ': ' . s(fullname($student)));

// Navegación de regreso.
echo html_writer::link(
    new moodle_url('/mod/codelab/view.php', ['id' => $cmid]),
    '← ' . get_string('submissions', 'mod_codelab'),
    ['class' => 'btn btn-secondary btn-sm mb-3']
);

// Información de la entrega.
echo '<div class="card mb-3">';
echo '<div class="card-header"><strong>' . get_string('last_submission', 'mod_codelab') . '</strong></div>';
echo '<div class="card-body">';
echo '<div class="row">';
echo '<div class="col-md-3"><strong>' . get_string('student', 'mod_codelab') . ':</strong> '
    . s(fullname($student)) . '</div>';
echo '<div class="col-md-3"><strong>' . get_string('language', 'mod_codelab') . ':</strong> ' . strtoupper($submission->language) . '</div>';
echo '<div class="col-md-3"><strong>' . get_string('submitted', 'mod_codelab') . ':</strong> ' . userdate($submission->timemodified) . '</div>';
echo '<div class="col-md-3"><strong>' . get_string('grade', 'mod_codelab') . ':</strong> ';

$grade = $submission->grade_override ?? $submission->grade;
if ($grade !== null) {
    // El color salía de un 60 % inventado. Ahora sale de la nota de corte real del curso; si
    // nadie la ha configurado, el distintivo es neutro en lugar de afirmar aprobado o suspenso.
    $passing = \mod_codelab\submission_manager::get_passing_grade($codelab);
    $badge   = $passing === null
        ? 'info'
        : ((float)$grade >= $passing ? 'success' : 'warning');

    echo '<span class="badge badge-' . $badge . '">';
    echo number_format((float)$grade, 1) . ' / ' . $codelab->grade;
    echo '</span>';
} else {
    echo '<span class="text-muted">' . get_string('not_graded', 'mod_codelab') . '</span>';
}
echo '</div>';
echo '</div>';

// Tests summary.
echo '<div class="mt-2">';
$passed = $submission->tests_passed;
$total  = $submission->tests_total;
$pct    = $total > 0 ? round($passed / $total * 100) : 0;
echo '<strong>' . get_string('testcases', 'mod_codelab') . ":</strong> {$passed}/{$total} ({$pct}%)";
echo ' <div class="progress mt-1" style="height:8px;max-width:300px;">';
// El color indica si se superaron TODOS los casos, no un umbral de aprobado inventado: el
// porcentaje de casos superados no es la nota, y convertirlo en un aprobado sería otra cifra
// sin respaldo (mismo motivo que INC-102).
echo "<div class='progress-bar bg-" . ($pct >= 100 ? 'success' : 'info') . "' style='width:{$pct}%'></div>";
echo '</div>';
echo '</div>';

echo '</div></div>';

// Recomendaciones por IA generadas para el estudiante (cualitativas, no afectan la nota).
if (!empty(trim($submission->ai_feedback ?? ''))) {
    echo '<div class="card mb-3 border-info">';
    echo '<div class="card-header"><strong>🤖 ' . get_string('ai_feedback_title', 'mod_codelab') . '</strong> ';
    echo '<span class="text-muted small">' . get_string('ai_feedback_disclaimer', 'mod_codelab') . '</span></div>';
    echo '<div class="card-body">';
    echo format_text($submission->ai_feedback, FORMAT_MARKDOWN);
    echo '</div></div>';
}

// Resultados de casos de prueba.
if (!empty($results)) {
    echo '<h5>' . get_string('testcases', 'mod_codelab') . '</h5>';
    foreach ($results as $r) {
        $status_class = $r->status === 'passed' ? 'success' : 'danger';
        $status_icon  = $r->status === 'passed' ? 'check-circle' : 'times-circle';

        echo "<div class='card mb-2 border-{$status_class}'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center'>";
        echo "<span><i class='fa fa-{$status_icon} text-{$status_class}'></i> <strong>" . htmlspecialchars($r->testcase_name) . "</strong>";
        if ($r->is_hidden) {
            echo " <span class='badge badge-secondary'>" . get_string('tc_hidden', 'mod_codelab') . "</span>";
        }
        echo "</span>";
        echo "<span class='badge badge-{$status_class}'>{$r->points} pts</span>";
        echo "</div>";
        echo "<div class='card-body py-2'>";

        if (!empty($r->stdin)) {
            echo "<div class='mb-1'><strong class='text-muted small'>"
                . get_string('tc_stdin', 'mod_codelab') . ":</strong><br><code>" . htmlspecialchars($r->stdin) . "</code></div>";
        }
        echo "<div class='mb-1'><strong class='text-muted small'>"
            . get_string('tc_expected_output', 'mod_codelab') . ":</strong><br><code>" . htmlspecialchars($r->expected_output) . "</code></div>";
        if ($r->actual_output !== null) {
            $match_class = $r->status === 'passed' ? '' : 'text-danger';
            echo "<div class='mb-1'><strong class='text-muted small'>"
                . get_string('actual_output', 'mod_codelab') . ":</strong><br><code class='{$match_class}'>" . htmlspecialchars($r->actual_output) . "</code></div>";
        }
        if (!empty($r->compile_output)) {
            echo "<div class='mb-1'><strong class='text-danger small'>"
                . get_string('compile_error_label', 'mod_codelab') . ":</strong><br><pre class='text-danger' style='font-size:0.8rem'>" . htmlspecialchars($r->compile_output) . "</pre></div>";
        }
        if (!empty($r->stderr)) {
            echo "<div class='mb-1'><strong class='text-warning small'>"
                . get_string('stderr_label', 'mod_codelab') . ":</strong><br><pre class='text-warning' style='font-size:0.8rem'>" . htmlspecialchars($r->stderr) . "</pre></div>";
        }
        if ($r->execution_time) {
            echo "<small class='text-muted'><i class='fa fa-clock-o'></i> " . $r->execution_time . "s";
            if ($r->memory_used) {
                echo " &nbsp; <i class='fa fa-microchip'></i> " . round($r->memory_used / 1024, 1) . " MB";
            }
            echo "</small>";
        }

        echo "</div></div>";
    }
}

// Código del estudiante.
echo '<h5 class="mt-4">' . get_string('submitted_code', 'mod_codelab') . '</h5>';
echo '<div class="position-relative">';
$s_copy   = s(get_string('copy', 'mod_codelab'));
$s_copied = s(get_string('copied', 'mod_codelab'));
echo '<button class="btn btn-sm btn-outline-secondary position-absolute" style="top:8px;right:8px;z-index:1;" '
    . 'onclick="navigator.clipboard.writeText(document.getElementById(\'sub-code\').textContent);'
    . 'this.textContent=\'✓ ' . $s_copied . '\';'
    . 'setTimeout(()=>this.textContent=\'' . $s_copy . '\',2000)">' . $s_copy . '</button>';
echo '<pre id="sub-code" class="codelab-submission-code-block">' . htmlspecialchars($submission->code) . '</pre>';
echo '</div>';

// Formulario de retroalimentación y nota manual.
echo '<div class="card mt-4">';
echo '<div class="card-header"><strong>'
    . get_string('teacher_feedback_optional', 'mod_codelab') . '</strong></div>';
echo '<div class="card-body">';
$action_url = new moodle_url('/mod/codelab/grade.php', ['id' => $cmid, 'sid' => $sid]);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $action_url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo '<div class="form-group">';
echo '<label for="grade_override">' . get_string('manual_grade_label', 'mod_codelab') . ':</label>';
echo html_writer::empty_tag('input', [
    'type'  => 'number',
    'id'    => 'grade_override',
    'name'  => 'grade_override',
    'class' => 'form-control',
    'min'   => 0,
    'max'   => $codelab->grade,
    'step'  => '0.01',
    'value' => $submission->grade_override ?? '',
    'placeholder' => get_string('auto_grade_placeholder', 'mod_codelab',
        number_format((float)($submission->grade ?? 0), 1)),
    'style' => 'max-width:200px;',
]);
echo '</div>';

echo '<div class="form-group">';
echo '<label for="feedback">' . get_string('comments', 'mod_codelab') . ':</label>';
echo html_writer::tag('textarea', htmlspecialchars($submission->feedback ?? ''), [
    'id'    => 'feedback',
    'name'  => 'feedback',
    'class' => 'form-control',
    'rows'  => 4,
    'placeholder' => get_string('feedback_placeholder', 'mod_codelab'),
]);
echo '</div>';

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('save_grade', 'mod_codelab'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');
echo '</div></div>';

echo $OUTPUT->footer();
