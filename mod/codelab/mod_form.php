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
 * Formulario de configuración de actividad CodeLab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_codelab_mod_form extends moodleform_mod {

    /** @var array Datos de casos de prueba del formulario */
    private array $testcases_data = [];

    public function definition(): void {
        global $CFG, $DB, $PAGE;

        $mform = $this->_form;

        // Sección: General.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Sección: Configuración del editor de código.
        $mform->addElement('header', 'codeeditor_settings', get_string('codeeditor_settings', 'mod_codelab'));
        $mform->setExpanded('codeeditor_settings');

        // Lenguajes de programación permitidos.
        $languageoptions = $this->get_language_options();
        $mform->addElement('select', 'default_language', get_string('default_language', 'mod_codelab'), $languageoptions);
        $mform->setDefault('default_language', 'python');

        $select = $mform->addElement('select', 'languages_allowed_select',
            get_string('languages_allowed', 'mod_codelab'), $languageoptions);
        $select->setMultiple(true);
        $mform->addHelpButton('languages_allowed_select', 'languages_allowed', 'mod_codelab');
        $mform->addElement('hidden', 'languages_allowed', '');
        $mform->setType('languages_allowed', PARAM_TEXT);

        // Código inicial para el estudiante.
        $mform->addElement('header', 'starter_code_header', get_string('starter_code', 'mod_codelab'));
        $mform->addElement('textarea', 'starter_code', get_string('starter_code', 'mod_codelab'), [
            'rows' => 10,
            'cols' => 80,
            'class' => 'codelab-starter-code',
            'style' => 'font-family: monospace;',
        ]);
        $mform->setType('starter_code', PARAM_RAW);
        $mform->addHelpButton('starter_code', 'starter_code', 'mod_codelab');

        // Código envolvente (runner automático de entrada/salida).
        $mform->addElement('header', 'runner_code_header', get_string('runner_code', 'mod_codelab'));
        $mform->setExpanded('runner_code_header', false);

        $mform->addElement('html',
            '<div class="alert alert-info mb-2" style="font-size:0.9rem;">' .
            '💡 ' . get_string('runner_code_explain', 'mod_codelab') . '<br><br>' .
            '<strong>' . get_string('example_python', 'mod_codelab') . ':</strong>' .
            '<pre style="background:#f4f4f4;padding:8px;border-radius:4px;">' .
            "n = int(input())\n{{CODE}}\nprint(fibonacci(n))</pre>" .
            get_string('student_writes_only', 'mod_codelab') .
            '<pre style="background:#f4f4f4;padding:8px;border-radius:4px;">' .
            "def fibonacci(n):\n    # ...</pre>" .
            '</div>'
        );

        $mform->addElement('textarea', 'runner_code', get_string('runner_code', 'mod_codelab'), [
            'rows' => 8,
            'cols' => 80,
            'style' => 'font-family: monospace;',
            'placeholder' => "n = int(input())\n{{CODE}}\nprint(funcion(n))",
        ]);
        $mform->setType('runner_code', PARAM_RAW);

        // Sección: Límites de ejecución.
        $mform->addElement('header', 'execution_limits', get_string('execution_limits', 'mod_codelab'));

        $timelimits = [];
        foreach ([1, 2, 3, 5, 10, 15, 30] as $sec) {
            $timelimits[$sec] = get_string('seconds', 'mod_codelab', $sec);
        }
        $mform->addElement('select', 'time_limit', get_string('time_limit', 'mod_codelab'), $timelimits);
        $mform->setDefault('time_limit', 5);

        $memorylimits = [];
        foreach ([64, 128, 256, 512] as $mb) {
            $memorylimits[$mb] = $mb . ' MB';
        }
        $mform->addElement('select', 'memory_limit', get_string('memory_limit', 'mod_codelab'), $memorylimits);
        $mform->setDefault('memory_limit', 128);

        // Sección: Configuración de entrega.
        $mform->addElement('header', 'submission_settings', get_string('submission_settings', 'mod_codelab'));

        $attemptoptions = [0 => get_string('unlimited', 'mod_codelab')];
        for ($i = 1; $i <= 20; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'max_attempts', get_string('max_attempts', 'mod_codelab'), $attemptoptions);
        $mform->setDefault('max_attempts', 0);

        $gradingmethods = [
            'best'    => get_string('grading_best', 'mod_codelab'),
            'last'    => get_string('grading_last', 'mod_codelab'),
            'first'   => get_string('grading_first', 'mod_codelab'),
            'average' => get_string('grading_average', 'mod_codelab'),
        ];
        $mform->addElement('select', 'grading_method', get_string('grading_method', 'mod_codelab'), $gradingmethods);
        $mform->setDefault('grading_method', 'best');

        $mform->addElement('advcheckbox', 'testcases_visible', get_string('testcases_visible', 'mod_codelab'));
        $mform->setDefault('testcases_visible', 1);
        $mform->addHelpButton('testcases_visible', 'testcases_visible', 'mod_codelab');

        // Fechas.
        $mform->addElement('date_time_selector', 'allow_submissions_from_date',
            get_string('allow_submissions_from_date', 'mod_codelab'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'duedate',
            get_string('duedate', 'mod_codelab'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'cutoffdate',
            get_string('cutoffdate', 'mod_codelab'), ['optional' => true]);

        // Sección: Recomendaciones por IA (retroalimentación cualitativa, no afecta la nota).
        $mform->addElement('header', 'ai_header', get_string('ai_feedback_section', 'mod_codelab'));

        $ai_globally_enabled = (bool)get_config('mod_codelab', 'ai_enabled');

        if (!$ai_globally_enabled) {
            // La función está apagada a nivel de sitio: informar y persistir valores neutros.
            $mform->addElement('html',
                '<div class="alert alert-secondary mb-2" style="font-size:0.9rem;">' .
                get_string('ai_disabled_site_notice', 'mod_codelab') . '</div>');

            $mform->addElement('hidden', 'ai_feedback_enabled', 0);
            $mform->setType('ai_feedback_enabled', PARAM_INT);
            $mform->addElement('hidden', 'ai_criteria', '[]');
            $mform->setType('ai_criteria', PARAM_RAW);
        } else {
            $mform->addElement('advcheckbox', 'ai_feedback_enabled',
                get_string('ai_feedback_enabled', 'mod_codelab'),
                get_string('ai_feedback_enabled_label', 'mod_codelab'));
            $mform->setDefault('ai_feedback_enabled', 0);
            $mform->addHelpButton('ai_feedback_enabled', 'ai_feedback_enabled', 'mod_codelab');

            $critselect = $mform->addElement('select', 'ai_criteria_select',
                get_string('ai_criteria', 'mod_codelab'),
                \mod_codelab\ai\criteria::options());
            $critselect->setMultiple(true);
            $mform->addHelpButton('ai_criteria_select', 'ai_criteria', 'mod_codelab');
            $mform->hideIf('ai_criteria_select', 'ai_feedback_enabled', 'notchecked');

            // Campo oculto donde se serializan los criterios seleccionados (JSON).
            $mform->addElement('hidden', 'ai_criteria', '');
            $mform->setType('ai_criteria', PARAM_RAW);
        }

        // Sección: Casos de prueba (Test Cases).
        $mform->addElement('header', 'testcases_header', get_string('testcases', 'mod_codelab'));
        $mform->setExpanded('testcases_header');

        $mform->addElement('html',
            '<div id="codelab-testcases-container" class="codelab-testcases-form">' .
            $this->render_testcases_html() .
            '</div>'
        );

        $mform->addElement('button', 'add_testcase', get_string('add_testcase', 'mod_codelab'), [
            'id'    => 'codelab-add-testcase',
            'class' => 'btn btn-secondary',
        ]);

        // Campos ocultos para serializar los casos de prueba.
        $mform->addElement('hidden', 'testcases_json', '');
        $mform->setType('testcases_json', PARAM_RAW);

        // Calificación estándar de Moodle.
        $this->standard_grading_coursemodule_elements();

        // Elementos estándar (visible, ID de número, etc.).
        $this->standard_coursemodule_elements();

        // Botones de acción.
        $this->add_action_buttons();

        // Cargar el JS para la gestión dinámica de casos de prueba (script directo, sin AMD).
        $cfg_json = json_encode(['containerid' => 'codelab-testcases-container'], JSON_HEX_TAG);
        $PAGE->requires->js_init_code("window.CODELAB_FORM_CFG = $cfg_json;", true);
        $PAGE->requires->js(new moodle_url('/mod/codelab/js/testcases_form.js'), true);
    }

    /**
     * Carga los datos existentes en el formulario al editar.
     */
    public function set_data($data): void {
        if (!empty($data->id)) {
            global $DB;
            $testcases = $DB->get_records('codelab_testcases',
                ['codelabid' => $data->id], 'ordering ASC, id ASC');
            $data->testcases_json = json_encode(array_values($testcases));

            if (!empty($data->languages_allowed)) {
                $data->languages_allowed_select = json_decode($data->languages_allowed, true);
            }

            // Precargar los criterios de IA guardados en el selector múltiple.
            if (!empty($data->ai_criteria)) {
                $data->ai_criteria_select = json_decode($data->ai_criteria, true) ?: [];
            }
        }
        parent::set_data($data);
    }

    /**
     * Procesa y valida los datos del formulario.
     */
    public function get_data(): ?stdClass {
        $data = parent::get_data();
        if ($data) {
            // Serializar los lenguajes permitidos.
            if (!empty($data->languages_allowed_select)) {
                $data->languages_allowed = json_encode(array_values($data->languages_allowed_select));
            } else {
                $data->languages_allowed = json_encode([$data->default_language]);
            }
            unset($data->languages_allowed_select);

            // Serializar los criterios de IA seleccionados (solo si la función está activa a nivel de sitio).
            if (property_exists($data, 'ai_criteria_select')) {
                $selected = \mod_codelab\ai\criteria::sanitize((array)$data->ai_criteria_select);
                $data->ai_criteria = json_encode(array_values($selected));
                unset($data->ai_criteria_select);
            }

            // Procesar los casos de prueba desde el JSON.
            if (!empty($data->testcases_json)) {
                $this->testcases_data = json_decode($data->testcases_json, true) ?? [];
            }
            unset($data->testcases_json);
        }
        return $data;
    }

    /**
     * Retorna los casos de prueba procesados del formulario.
     */
    public function get_testcases_data(): array {
        return $this->testcases_data;
    }

    /**
     * Renderiza el HTML inicial de la tabla de casos de prueba.
     */
    private function render_testcases_html(): string {
        global $DB;

        $testcases = [];
        if (!empty($this->_instance)) {
            $testcases = $DB->get_records('codelab_testcases',
                ['codelabid' => $this->_instance], 'ordering ASC, id ASC');
        }

        $html = '<div class="codelab-testcases-list">';
        $html .= '<div class="codelab-tc-header row font-weight-bold mb-2">';
        $html .= '<div class="col-3">' . get_string('tc_name', 'mod_codelab') . '</div>';
        $html .= '<div class="col-3">' . get_string('tc_stdin', 'mod_codelab') . '</div>';
        $html .= '<div class="col-3">' . get_string('tc_expected_output', 'mod_codelab') . '</div>';
        $html .= '<div class="col-1">' . get_string('tc_points', 'mod_codelab') . '</div>';
        $html .= '<div class="col-1">' . get_string('tc_hidden', 'mod_codelab') . '</div>';
        $html .= '<div class="col-1"></div>';
        $html .= '</div>';
        $html .= '<div id="codelab-tc-rows" class="codelab-tc-rows">';

        if (empty($testcases)) {
            $html .= $this->render_empty_testcase_row(0);
        } else {
            foreach (array_values($testcases) as $i => $tc) {
                $html .= $this->render_testcase_row($i, (array)$tc);
            }
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Renderiza una fila vacía de caso de prueba.
     */
    private function render_empty_testcase_row(int $index): string {
        return $this->render_testcase_row($index, [
            'name'            => '',
            'stdin'           => '',
            'expected_output' => '',
            'points'          => '1',
            'is_hidden'       => '0',
        ]);
    }

    /**
     * Renderiza una fila de caso de prueba con datos.
     */
    private function render_testcase_row(int $index, array $data): string {
        $name     = htmlspecialchars($data['name'] ?? '');
        $stdin    = htmlspecialchars($data['stdin'] ?? '');
        $expected = htmlspecialchars($data['expected_output'] ?? '');
        $points   = htmlspecialchars($data['points'] ?? '1');
        $hidden   = !empty($data['is_hidden']) ? 'checked' : '';

        $html  = '<div class="codelab-tc-row row mb-2 align-items-start" data-index="' . $index . '">';
        $html .= '<div class="col-md-3 col-sm-12 mb-1">';
        $html .= '<input type="text" class="form-control form-control-sm tc-name"';
        $html .= ' placeholder="' . s(get_string('tc_name_placeholder', 'mod_codelab')) . '"'
            . ' value="' . $name . '"/>';
        $html .= '</div>';
        $html .= '<div class="col-md-3 col-sm-12 mb-1">';
        $html .= '<textarea class="form-control form-control-sm tc-stdin" rows="2"';
        $html .= ' placeholder="' . s(get_string('tc_stdin', 'mod_codelab')) . '">'
            . $stdin . '</textarea>';
        $html .= '</div>';
        $html .= '<div class="col-md-3 col-sm-12 mb-1">';
        $html .= '<textarea class="form-control form-control-sm tc-expected" rows="2"';
        $html .= ' placeholder="' . s(get_string('tc_expected_output', 'mod_codelab')) . '">'
            . $expected . '</textarea>';
        $html .= '</div>';
        $html .= '<div class="col-md-1 col-sm-4 mb-1">';
        $html .= '<label class="d-block d-md-none small text-muted">'
            . get_string('tc_points', 'mod_codelab') . '</label>';
        $html .= '<input type="number" class="form-control form-control-sm tc-points"';
        $html .= ' min="0" step="0.5" value="' . $points . '" style="min-width:60px;"/>';
        $html .= '</div>';
        $html .= '<div class="col-md-1 col-sm-4 mb-1 text-center pt-2">';
        $html .= '<label class="d-block d-md-none small text-muted">'
            . get_string('tc_hidden', 'mod_codelab') . '</label>';
        $html .= '<input type="checkbox" class="tc-hidden" ' . $hidden;
        $html .= ' title="' . s(get_string('hide_from_student', 'mod_codelab')) . '"'
            . ' style="width:20px;height:20px;"/>';
        $html .= '</div>';
        $html .= '<div class="col-md-1 col-sm-4 mb-1 pt-1">';
        $html .= '<button type="button" class="btn btn-sm btn-danger codelab-remove-tc"'
            . ' title="' . s(get_string('delete', 'mod_codelab')) . '">';
        $html .= '<i class="fa fa-trash"></i></button>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Opciones de lenguajes de programación soportados.
     */
    private function get_language_options(): array {
        return [
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
    }
}
