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
 * Estructura de backup para mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class backup_codelab_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure(): backup_nested_element {

        $userinfo = $this->get_setting_value('userinfo');

        // Elemento raíz.
        // La lista debe cubrir TODOS los campos de la tabla `codelab` en db/install.xml salvo
        // `id` y `course`, que los pone la restauración. Faltaban `solution_code`, `runner_code`,
        // `ai_feedback_enabled` y `ai_criteria`: al duplicar o restaurar la actividad se perdían
        // el código envolvente y toda la configuración de IA (MDL-01).
        $codelab = new backup_nested_element('codelab', ['id'], [
            'name', 'intro', 'introformat', 'default_language', 'languages_allowed',
            'starter_code', 'solution_code', 'runner_code',
            'ai_feedback_enabled', 'ai_criteria',
            'time_limit', 'memory_limit', 'max_attempts',
            'grading_method', 'grade', 'testcases_visible',
            'allow_submissions_from_date', 'duedate', 'cutoffdate',
            'timecreated', 'timemodified',
        ]);

        // Casos de prueba.
        $testcases = new backup_nested_element('testcases');
        $testcase  = new backup_nested_element('testcase', ['id'], [
            'name', 'description', 'stdin', 'expected_output',
            'points', 'is_hidden', 'ordering', 'timecreated',
        ]);

        // Entregas (solo si se incluye userinfo).
        $submissions = new backup_nested_element('submissions');
        $submission  = new backup_nested_element('submission', ['id'], [
            'userid', 'attempt_number', 'code', 'language', 'status',
            'grade', 'grade_override', 'feedback',
            'ai_feedback', 'ai_feedback_status', 'ai_feedback_time',
            'tests_passed', 'tests_total', 'timecreated', 'timemodified',
        ]);

        // Resultado por caso de prueba. Es la tabla que guarda la salida real del programa del
        // estudiante (`actual_output`, `stderr`), la misma que la Privacy API declara y exporta
        // como dato personal: si el backup no la lleva, una restauración la destruye (MDL-01).
        $results = new backup_nested_element('results');
        $result  = new backup_nested_element('result', ['id'], [
            'testcase_id', 'status', 'actual_output', 'stderr', 'compile_output',
            'execution_time', 'memory_used', 'timecreated',
        ]);

        // Relaciones jerárquicas.
        $codelab->add_child($testcases);
        $testcases->add_child($testcase);

        if ($userinfo) {
            $codelab->add_child($submissions);
            $submissions->add_child($submission);
            $submission->add_child($results);
            $results->add_child($result);
        }

        // Fuentes de datos.
        $codelab->set_source_table('codelab', ['id' => backup::VAR_ACTIVITYID]);
        $testcase->set_source_table('codelab_testcases', ['codelabid' => backup::VAR_PARENTID], 'ordering ASC, id ASC');

        if ($userinfo) {
            $submission->set_source_table('codelab_submissions', ['codelabid' => backup::VAR_PARENTID]);
            $submission->annotate_ids('user', 'userid');

            $result->set_source_table('codelab_submission_results', ['submission_id' => backup::VAR_PARENTID]);
        }

        $codelab->annotate_files('mod_codelab', 'intro', null);

        return $this->prepare_activity_structure($codelab);
    }
}
