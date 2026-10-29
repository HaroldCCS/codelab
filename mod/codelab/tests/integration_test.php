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
 * Prueba de integración: flujo completo de una evaluación.
 *
 * Recorre el camino real: el estudiante guarda un borrador, lo envía, el
 * grader ejecuta los casos (motor simulado), calcula la nota y la registra
 * en el libro de calificaciones de Moodle.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

namespace mod_codelab;

use mod_codelab\executor\mock_executor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/codelab/lib.php');
require_once($CFG->dirroot . '/mod/codelab/classes/grader.php');
require_once($CFG->dirroot . '/mod/codelab/classes/submission_manager.php');
require_once($CFG->dirroot . '/mod/codelab/tests/fixtures/mock_executor.php');

final class integration_test extends \advanced_testcase {

    public function test_full_flow_draft_submit_grade_gradebook(): void {
        global $DB;
        $this->resetAfterTest();

        // 1) Docente crea la actividad con dos casos de prueba (60 + 40 puntos).
        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $codelab = $gen->create_instance([
            'course' => $course->id,
            'grade'  => 100,
            'grading_method' => 'best',
        ]);
        $gen->create_testcase(['codelabid' => $codelab->id, 'stdin' => '3 7', 'expected_output' => '10', 'points' => 60, 'ordering' => 0]);
        $gen->create_testcase(['codelabid' => $codelab->id, 'stdin' => '0 0', 'expected_output' => '0',  'points' => 40, 'ordering' => 1]);

        $cm = get_coursemodule_from_instance('codelab', $codelab->id);
        $context = \context_module::instance($cm->id);

        // 2) Estudiante matriculado.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        // 3) Estudiante guarda un borrador y lo envía.
        $mgr = new submission_manager($codelab, $context);
        $submission = $mgr->save_draft($student->id, "a,b=map(int,input().split())\nprint(a+b)", 'python');
        $mgr->finalize_submission($submission->id);
        $this->assertSame('submitted', $DB->get_field('codelab_submissions', 'status', ['id' => $submission->id]));

        // 4) El grader ejecuta (motor simulado: ambas salidas correctas) y guarda.
        $exec = new mock_executor();
        $exec->push_accepted('10');
        $exec->push_accepted('0');
        $testcases = array_values($DB->get_records('codelab_testcases', ['codelabid' => $codelab->id], 'ordering'));

        $grader = new grader($codelab, $exec);
        $gradingresult = $grader->run_all_testcases($submission->code, $submission->language, $testcases);
        $grader->save_results($submission->id, $gradingresult);

        // 5) Verificación integral.
        $this->assertSame(2, $gradingresult['tests_passed']);
        $this->assertEqualsWithDelta(100.0, $gradingresult['grade'], 0.001);

        // Persistencia de resultados por caso.
        $this->assertSame(2, $DB->count_records('codelab_submission_results', ['submission_id' => $submission->id]));

        // Nota en el libro de calificaciones.
        $grades = grade_get_grades($course->id, 'mod', 'codelab', $codelab->id, $student->id);
        $finalgrade = (float)$grades->items[0]->grades[$student->id]->grade;
        $this->assertEqualsWithDelta(100.0, $finalgrade, 0.001);
    }

    public function test_flow_with_partial_result_and_hidden_case(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $codelab = $gen->create_instance(['course' => $course->id, 'grade' => 100]);
        // Caso visible (pasa) + caso oculto (falla).
        $gen->create_testcase(['codelabid' => $codelab->id, 'expected_output' => 'ok', 'points' => 50, 'is_hidden' => 0, 'ordering' => 0]);
        $gen->create_testcase(['codelabid' => $codelab->id, 'expected_output' => 'secreto', 'points' => 50, 'is_hidden' => 1, 'ordering' => 1]);

        $cm = get_coursemodule_from_instance('codelab', $codelab->id);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $mgr = new submission_manager($codelab, $context);
        $sub = $mgr->save_draft($student->id, 'codigo', 'python');
        $mgr->finalize_submission($sub->id);

        $exec = new mock_executor();
        $exec->push_accepted('ok');       // visible pasa
        $exec->push_accepted('fallido');  // oculto falla
        $testcases = array_values($DB->get_records('codelab_testcases', ['codelabid' => $codelab->id], 'ordering'));

        $grader = new grader($codelab, $exec);
        $res = $grader->run_all_testcases('codigo', 'python', $testcases);
        $grader->save_results($sub->id, $res);

        $this->assertSame(1, $res['tests_passed']);
        $this->assertEqualsWithDelta(50.0, $res['grade'], 0.001);
    }
}
