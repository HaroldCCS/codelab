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
 * Pruebas del sistema de calificación automática.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\grader
 */

namespace mod_codelab;

use mod_codelab\executor\mock_executor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/codelab/lib.php');
require_once($CFG->dirroot . '/mod/codelab/classes/grader.php');
require_once($CFG->dirroot . '/mod/codelab/tests/fixtures/mock_executor.php');

final class grader_test extends \advanced_testcase {

    /**
     * Crea una lista de objetos de caso de prueba.
     *
     * @param array $specs Cada elemento: [expected_output, points, name?]
     * @return \stdClass[]
     */
    private function make_testcases(array $specs): array {
        $tcs = [];
        $i = 1;
        foreach ($specs as $spec) {
            $tc = new \stdClass();
            $tc->id              = $i;
            $tc->name            = $spec['name'] ?? "Caso $i";
            $tc->stdin           = $spec['stdin'] ?? '';
            $tc->expected_output = $spec['expected'];
            $tc->points          = $spec['points'] ?? 1;
            $tcs[] = $tc;
            $i++;
        }
        return $tcs;
    }

    private function make_codelab(float $grademax = 100, string $method = 'best'): \stdClass {
        $c = new \stdClass();
        $c->id             = 1;
        $c->grade          = $grademax;
        $c->grading_method = $method;
        $c->time_limit     = 5;
        $c->memory_limit   = 128;
        return $c;
    }

    public function test_all_testcases_passed_gives_full_grade(): void {
        $exec = new mock_executor();
        $exec->push_accepted('10');
        $exec->push_accepted('20');
        $exec->push_accepted('30');

        $testcases = $this->make_testcases([
            ['expected' => '10', 'points' => 1],
            ['expected' => '20', 'points' => 1],
            ['expected' => '30', 'points' => 1],
        ]);

        $grader = new grader($this->make_codelab(100), $exec);
        $result = $grader->run_all_testcases('codigo', 'python', $testcases);

        $this->assertSame(3, $result['tests_passed']);
        $this->assertSame(3, $result['tests_total']);
        $this->assertEqualsWithDelta(100.0, $result['grade'], 0.001);
    }

    public function test_partial_weighted_grade(): void {
        // Pesos 40/30/30. Pasan los dos primeros → 70 de 100 puntos.
        $exec = new mock_executor();
        $exec->push_accepted('ok1');   // pasa
        $exec->push_accepted('ok2');   // pasa
        $exec->push_status('wrong_answer', 'malo'); // falla

        $testcases = $this->make_testcases([
            ['expected' => 'ok1', 'points' => 40],
            ['expected' => 'ok2', 'points' => 30],
            ['expected' => 'ok3', 'points' => 30],
        ]);

        $grader = new grader($this->make_codelab(100), $exec);
        $result = $grader->run_all_testcases('codigo', 'python', $testcases);

        $this->assertSame(2, $result['tests_passed']);
        $this->assertEqualsWithDelta(70.0, $result['grade'], 0.001);
        $this->assertEqualsWithDelta(70.0, $result['points_earned'], 0.001);
        $this->assertEqualsWithDelta(100.0, $result['points_total'], 0.001);
    }

    public function test_grade_scales_to_grademax(): void {
        // Mismo 70% pero con nota máxima 50 → 35.
        $exec = new mock_executor();
        $exec->push_accepted('a');
        $exec->push_accepted('b');
        $exec->push_status('wrong_answer', 'x');

        $testcases = $this->make_testcases([
            ['expected' => 'a', 'points' => 40],
            ['expected' => 'b', 'points' => 30],
            ['expected' => 'c', 'points' => 30],
        ]);

        $grader = new grader($this->make_codelab(50), $exec);
        $result = $grader->run_all_testcases('codigo', 'python', $testcases);
        $this->assertEqualsWithDelta(35.0, $result['grade'], 0.001);
    }

    public function test_no_testcases_passed_gives_zero(): void {
        $exec = new mock_executor();
        $exec->set_default(new \mod_codelab\executor\execution_result('wrong_answer', 'nope'));

        $testcases = $this->make_testcases([
            ['expected' => '1', 'points' => 50],
            ['expected' => '2', 'points' => 50],
        ]);

        $grader = new grader($this->make_codelab(100), $exec);
        $result = $grader->run_all_testcases('codigo', 'python', $testcases);
        $this->assertSame(0, $result['tests_passed']);
        $this->assertEqualsWithDelta(0.0, $result['grade'], 0.001);
    }

    public function test_zero_testcases_does_not_divide_by_zero(): void {
        $exec = new mock_executor();
        $grader = new grader($this->make_codelab(100), $exec);
        $result = $grader->run_all_testcases('codigo', 'python', []);
        $this->assertSame(0, $result['tests_total']);
        $this->assertEqualsWithDelta(0.0, $result['grade'], 0.001);
    }

    public function test_accepted_status_but_wrong_output_is_failed(): void {
        // El motor acepta la ejecución (accepted) pero la salida no coincide.
        $exec = new mock_executor();
        $exec->push_accepted('resultado_incorrecto');

        $testcases = $this->make_testcases([['expected' => 'resultado_correcto', 'points' => 10]]);

        $grader = new grader($this->make_codelab(100), $exec);
        $result = $grader->run_all_testcases('codigo', 'python', $testcases);

        $this->assertSame(0, $result['tests_passed']);
        $this->assertSame('failed', $result['results'][0]['status']);
    }

    public function test_status_mapping_for_failures(): void {
        $cases = [
            'time_limit_exceeded' => 'timeout',
            'compile_error'       => 'compile_error',
            'runtime_error'       => 'error',
        ];
        foreach ($cases as $execstatus => $expected) {
            $exec = new mock_executor();
            $exec->push_status($execstatus);
            $testcases = $this->make_testcases([['expected' => 'algo', 'points' => 1]]);
            $grader = new grader($this->make_codelab(100), $exec);
            $result = $grader->run_all_testcases('codigo', 'python', $testcases);
            $this->assertSame($expected, $result['results'][0]['status'], "estado $execstatus");
        }
    }

    public function test_executor_receives_language_and_stdin(): void {
        $exec = new mock_executor();
        $exec->push_accepted('salida');
        $testcases = $this->make_testcases([['expected' => 'salida', 'points' => 1, 'stdin' => '5 7']]);

        $grader = new grader($this->make_codelab(100), $exec);
        $grader->run_all_testcases('mi codigo', 'java', $testcases);

        $this->assertCount(1, $exec->calls);
        $this->assertSame('java', $exec->calls[0]['language']);
        $this->assertSame('5 7', $exec->calls[0]['stdin']);
        $this->assertSame('mi codigo', $exec->calls[0]['code']);
    }

    // ── Pruebas con base de datos (persistencia + gradebook) ───────────────

    public function test_save_results_persists_and_updates_gradebook(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $codelab, $user] = $this->create_activity_with_user();

        // Dos casos de prueba reales en BD.
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $tc1 = $gen->create_testcase(['codelabid' => $codelab->id, 'expected_output' => '10', 'points' => 60]);
        $tc2 = $gen->create_testcase(['codelabid' => $codelab->id, 'expected_output' => '20', 'points' => 40]);

        // Entrega del estudiante.
        $submission = $this->create_submission($codelab->id, $user->id);

        // Motor simulado: pasa el primero, falla el segundo → 60/100.
        $exec = new mock_executor();
        $exec->push_accepted('10');
        $exec->push_accepted('99'); // no coincide con 20

        $testcases = array_values($DB->get_records('codelab_testcases', ['codelabid' => $codelab->id], 'ordering'));

        $grader = new grader($codelab, $exec);
        $gradingresult = $grader->run_all_testcases('codigo', 'python', $testcases);
        $grader->save_results($submission->id, $gradingresult);

        // Resultados por caso persistidos.
        $rescount = $DB->count_records('codelab_submission_results', ['submission_id' => $submission->id]);
        $this->assertSame(2, $rescount);

        // Nota de la entrega actualizada.
        $updated = $DB->get_record('codelab_submissions', ['id' => $submission->id]);
        $this->assertEqualsWithDelta(60.0, (float)$updated->grade, 0.001);
        $this->assertSame(1, (int)$updated->tests_passed);

        // Nota reflejada en el libro de calificaciones de Moodle.
        $grades = grade_get_grades($course->id, 'mod', 'codelab', $codelab->id, $user->id);
        $finalgrade = $grades->items[0]->grades[$user->id]->grade;
        $this->assertEqualsWithDelta(60.0, (float)$finalgrade, 0.001);
    }

    public function test_gradebook_method_best_keeps_highest(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $codelab, $user] = $this->create_activity_with_user(['grading_method' => 'best']);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $gen->create_testcase(['codelabid' => $codelab->id, 'expected_output' => 'ok', 'points' => 100]);
        $testcases = array_values($DB->get_records('codelab_testcases', ['codelabid' => $codelab->id]));

        // Primera entrega: 100.
        $s1 = $this->create_submission($codelab->id, $user->id);
        $exec1 = new mock_executor();
        $exec1->push_accepted('ok');
        $g1 = new grader($codelab, $exec1);
        $r1 = $g1->run_all_testcases('c', 'python', $testcases);
        $g1->save_results($s1->id, $r1);

        // Segunda entrega: 0 (peor). Con método "best" la nota del libro debe seguir siendo 100.
        $s2 = $this->create_submission($codelab->id, $user->id);
        $exec2 = new mock_executor();
        $exec2->push_status('wrong_answer', 'no');
        $g2 = new grader($codelab, $exec2);
        $r2 = $g2->run_all_testcases('c', 'python', $testcases);
        $g2->save_results($s2->id, $r2);

        $grades = grade_get_grades($course->id, 'mod', 'codelab', $codelab->id, $user->id);
        $finalgrade = (float)$grades->items[0]->grades[$user->id]->grade;
        $this->assertEqualsWithDelta(100.0, $finalgrade, 0.001);
    }

    public function test_gradebook_method_last_uses_latest(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $codelab, $user] = $this->create_activity_with_user(['grading_method' => 'last']);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $gen->create_testcase(['codelabid' => $codelab->id, 'expected_output' => 'ok', 'points' => 100]);
        $testcases = array_values($DB->get_records('codelab_testcases', ['codelabid' => $codelab->id]));

        // Primera: 100. Segunda (última): 0 → el libro debe reflejar 0.
        $s1 = $this->create_submission($codelab->id, $user->id);
        $e1 = new mock_executor(); $e1->push_accepted('ok');
        $g1 = new grader($codelab, $e1); $g1->save_results($s1->id, $g1->run_all_testcases('c', 'python', $testcases));

        $s2 = $this->create_submission($codelab->id, $user->id);
        $e2 = new mock_executor(); $e2->push_status('wrong_answer', 'no');
        $g2 = new grader($codelab, $e2); $g2->save_results($s2->id, $g2->run_all_testcases('c', 'python', $testcases));

        $grades = grade_get_grades($course->id, 'mod', 'codelab', $codelab->id, $user->id);
        $finalgrade = (float)$grades->items[0]->grades[$user->id]->grade;
        $this->assertEqualsWithDelta(0.0, $finalgrade, 0.001);
    }

    public function test_codelab_update_grades_usa_first_no_el_maximo_bruto(): void {
        global $DB;
        $this->resetAfterTest();

        // MDL-03: codelab_update_grades() (el gancho que Moodle llama en «Recalcular todas
        // las notas ahora») hacía MAX(grade) sobre la columna cruda, ignorando por completo
        // el método de calificación configurado. Con "first" y estos tres intentos (40, 90,
        // 60, en ese orden), el valor correcto es el del PRIMERO: 40. El código anterior
        // daba 90 (el mayor). Si alguien reintroduce el MAX(grade), esta prueba lo detecta.
        [$course, $codelab, $user] = $this->create_activity_with_user(['grading_method' => 'first']);

        $base = time() - 300;
        $this->create_graded_submission($codelab->id, $user->id, 40.0, null, $base);
        $this->create_graded_submission($codelab->id, $user->id, 90.0, null, $base + 100);
        $this->create_graded_submission($codelab->id, $user->id, 60.0, null, $base + 200);

        codelab_update_grades($codelab);

        $grades = grade_get_grades($course->id, 'mod', 'codelab', $codelab->id, $user->id);
        $finalgrade = (float)$grades->items[0]->grades[$user->id]->grade;
        $this->assertEqualsWithDelta(
            40.0,
            $finalgrade,
            0.001,
            'Con grading_method=first debe ganar el primer intento (40), no el mayor (90)'
        );
    }

    public function test_codelab_update_grades_conserva_el_grade_override_del_docente(): void {
        global $DB;
        $this->resetAfterTest();

        // MDL-03 (segunda parte): el recálculo también ignoraba grade_override. Con la nota
        // automática en 50 y una corrección manual del docente a 95, «Recalcular todas las
        // notas ahora» no puede volver a poner 50: pisaría en silencio el criterio del
        // profesor, que es exactamente lo que se reportó.
        [$course, $codelab, $user] = $this->create_activity_with_user(['grading_method' => 'best']);

        $this->create_graded_submission($codelab->id, $user->id, 50.0, 95.0, time());

        codelab_update_grades($codelab);

        $grades = grade_get_grades($course->id, 'mod', 'codelab', $codelab->id, $user->id);
        $finalgrade = (float)$grades->items[0]->grades[$user->id]->grade;
        $this->assertEqualsWithDelta(
            95.0,
            $finalgrade,
            0.001,
            'grade_override debe sobrevivir al recálculo: la nota automática (50) no debe '
            . 'pisar la corrección manual del docente (95)'
        );
    }

    public function test_compute_final_grade_y_compute_final_grades_coinciden_para_el_mismo_usuario(): void {
        global $DB;
        $this->resetAfterTest();

        // compute_final_grade() (un usuario) y compute_final_grades() (muchos, para la vista
        // del docente tras INC-107) comparten select_by_method() por diseño, pero eso es un
        // acuerdo, no una garantía: este código ya divergió cuatro veces en el proyecto. Un
        // grade_override de por medio para que la coincidencia no sea trivial.
        [$course, $codelab, $user] = $this->create_activity_with_user();

        $base = time() - 300;
        $this->create_graded_submission($codelab->id, $user->id, 40.0, null, $base);
        $this->create_graded_submission($codelab->id, $user->id, 90.0, 55.0, $base + 100);
        $this->create_graded_submission($codelab->id, $user->id, 60.0, null, $base + 200);

        foreach (['best', 'first', 'average', 'last'] as $method) {
            $DB->set_field('codelab', 'grading_method', $method, ['id' => $codelab->id]);
            $codelab->grading_method = $method;

            $individual = grader::compute_final_grade($codelab, $user->id);
            $enlote     = grader::compute_final_grades($codelab, [$user->id])[$user->id];

            $this->assertSame(
                $individual,
                $enlote,
                "compute_final_grade() y compute_final_grades() deben coincidir para grading_method='$method'"
            );
        }
    }

    public function test_compute_final_grade_desempata_por_id_con_el_mismo_timecreated(): void {
        global $DB;
        $this->resetAfterTest();

        // Dos intentos con EXACTAMENTE el mismo timecreated (el mismo segundo): sin el
        // desempate por id, el orden queda al criterio del motor de base de datos, y
        // "primer intento"/"último intento" podían dar notas distintas en cargas sucesivas
        // de la misma página.
        [$course, $codelab, $user] = $this->create_activity_with_user(['grading_method' => 'first']);

        $mismoinstante = time();
        $primero = $this->create_graded_submission($codelab->id, $user->id, 30.0, null, $mismoinstante);
        $segundo = $this->create_graded_submission($codelab->id, $user->id, 90.0, null, $mismoinstante);
        $this->assertLessThan($segundo->id, $primero->id, 'La entrega insertada primero debe tener el id menor');

        $this->assertSame(
            30.0,
            grader::compute_final_grade($codelab, $user->id),
            'Con grading_method=first y timecreated empatado, debe ganar la de menor id (insertada primero)'
        );

        $DB->set_field('codelab', 'grading_method', 'last', ['id' => $codelab->id]);
        $codelab->grading_method = 'last';

        $this->assertSame(
            90.0,
            grader::compute_final_grade($codelab, $user->id),
            'Con grading_method=last y timecreated empatado, debe ganar la de mayor id (insertada después)'
        );
    }

    // ── Helpers de BD ──────────────────────────────────────────────────────

    /**
     * @return array{0:\stdClass,1:\stdClass,2:\stdClass} [course, codelab, user]
     */
    private function create_activity_with_user(array $codelaboverrides = []): array {
        $course = $this->getDataGenerator()->create_course();
        $params = array_merge(['course' => $course->id], $codelaboverrides);
        $codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')->create_instance($params);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        return [$course, $codelab, $user];
    }

    private function create_submission(int $codelabid, int $userid): \stdClass {
        global $DB;
        $s = new \stdClass();
        $s->codelabid      = $codelabid;
        $s->userid         = $userid;
        $s->attempt_number = 1;
        $s->code           = 'print(1)';
        $s->language       = 'python';
        $s->status         = 'submitted';
        $s->tests_passed   = 0;
        $s->tests_total    = 0;
        $s->timecreated    = time();
        $s->timemodified   = time();
        $s->id = $DB->insert_record('codelab_submissions', $s);
        return $s;
    }

    /**
     * Como create_submission(), pero permite fijar nota, grade_override y marca de tiempo:
     * lo que hace falta para las pruebas de compute_final_grade()/codelab_update_grades()
     * (MDL-03), donde el orden de los intentos y el valor de la corrección manual importan.
     */
    private function create_graded_submission(
        int $codelabid,
        int $userid,
        float $grade,
        ?float $gradeoverride,
        int $timecreated
    ): \stdClass {
        global $DB;
        $s = new \stdClass();
        $s->codelabid      = $codelabid;
        $s->userid         = $userid;
        $s->attempt_number = 1;
        $s->code           = 'print(1)';
        $s->language       = 'python';
        $s->status         = 'submitted';
        $s->grade          = $grade;
        $s->grade_override = $gradeoverride;
        $s->tests_passed   = 1;
        $s->tests_total    = 1;
        $s->timecreated    = $timecreated;
        $s->timemodified   = $timecreated;
        $s->id = $DB->insert_record('codelab_submissions', $s);
        return $s;
    }
}
