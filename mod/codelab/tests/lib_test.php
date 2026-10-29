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
 * Pruebas de las funciones de biblioteca (lib.php) de mod_codelab.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \nothing
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/codelab/lib.php');

final class lib_test extends \advanced_testcase {

    public function test_supports_flags(): void {
        $this->assertTrue(codelab_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(codelab_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(codelab_supports(FEATURE_MOD_INTRO));
        $this->assertSame(MOD_PURPOSE_ASSESSMENT, codelab_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(codelab_supports('una_feature_inexistente'));
    }

    public function test_add_instance_creates_record_and_grade_item(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $course->id, 'name' => 'Ejercicio 1', 'grade' => 80]);

        $record = $DB->get_record('codelab', ['id' => $codelab->id]);
        $this->assertNotEmpty($record);
        $this->assertSame('Ejercicio 1', $record->name);
        $this->assertGreaterThan(0, $record->timecreated);

        // Debe existir el ítem de calificación en el libro de notas.
        $gradeitem = $DB->get_record('grade_items', [
            'itemmodule' => 'codelab',
            'iteminstance' => $codelab->id,
        ]);
        $this->assertNotEmpty($gradeitem);
        $this->assertEqualsWithDelta(80.0, (float)$gradeitem->grademax, 0.001);
    }

    public function test_update_instance_changes_fields(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $course->id, 'name' => 'Original']);

        $codelab->instance = $codelab->id;
        $codelab->name = 'Modificado';
        $codelab->grade = 50;
        $ok = codelab_update_instance($codelab, null);
        $this->assertTrue((bool)$ok);

        $record = $DB->get_record('codelab', ['id' => $codelab->id]);
        $this->assertSame('Modificado', $record->name);
    }

    public function test_delete_instance_removes_all_related_data(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $codelab = $gen->create_instance(['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        // Datos relacionados: caso de prueba, entrega y resultado.
        $tc = $gen->create_testcase(['codelabid' => $codelab->id, 'expected_output' => '1']);

        $sub = new \stdClass();
        $sub->codelabid = $codelab->id; $sub->userid = $user->id; $sub->attempt_number = 1;
        $sub->code = 'x'; $sub->language = 'python'; $sub->status = 'submitted';
        $sub->tests_passed = 0; $sub->tests_total = 0;
        $sub->timecreated = time(); $sub->timemodified = time();
        $sub->id = $DB->insert_record('codelab_submissions', $sub);

        $res = new \stdClass();
        $res->submission_id = $sub->id; $res->testcase_id = $tc->id; $res->status = 'passed';
        $res->timecreated = time();
        $DB->insert_record('codelab_submission_results', $res);

        // Borrar la instancia.
        $this->assertTrue(codelab_delete_instance($codelab->id));

        // No debe quedar nada.
        $this->assertSame(0, $DB->count_records('codelab', ['id' => $codelab->id]));
        $this->assertSame(0, $DB->count_records('codelab_testcases', ['codelabid' => $codelab->id]));
        $this->assertSame(0, $DB->count_records('codelab_submissions', ['codelabid' => $codelab->id]));
        $this->assertSame(0, $DB->count_records('codelab_submission_results', ['submission_id' => $sub->id]));
    }

    public function test_delete_instance_returns_false_for_missing(): void {
        $this->resetAfterTest();
        $this->assertFalse(codelab_delete_instance(999999));
    }

    public function test_save_testcases_skips_empty_and_trims(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $course->id]);

        codelab_save_testcases($codelab->id, [
            ['name' => 'Uno', 'stdin' => '1', 'expected_output' => "  42  \n", 'points' => 10],
            ['name' => '', 'expected_output' => ''], // fila vacía → se ignora
            ['name' => 'Tres', 'expected_output' => 'ok', 'points' => 5, 'is_hidden' => 1],
        ]);

        $tcs = array_values($DB->get_records('codelab_testcases', ['codelabid' => $codelab->id], 'ordering'));
        $this->assertCount(2, $tcs);
        $this->assertSame('42', $tcs[0]->expected_output); // recortado
        $this->assertEqualsWithDelta(10.0, (float)$tcs[0]->points, 0.001);
        $this->assertSame(1, (int)$tcs[1]->is_hidden);
    }

    public function test_get_user_grades_returns_best_per_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        foreach ([40, 90, 70] as $g) {
            $s = new \stdClass();
            $s->codelabid = $codelab->id; $s->userid = $user->id; $s->attempt_number = 1;
            $s->code = 'x'; $s->language = 'python'; $s->status = 'submitted';
            $s->grade = $g; $s->tests_passed = 0; $s->tests_total = 0;
            $s->timecreated = time(); $s->timemodified = time();
            $DB->insert_record('codelab_submissions', $s);
        }

        $grades = codelab_get_user_grades($codelab, $user->id);
        $this->assertIsArray($grades);
        $this->assertEqualsWithDelta(90.0, (float)$grades[$user->id]['rawgrade'], 0.001);
    }

    public function test_get_user_grades_false_when_no_submissions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $course->id]);
        $this->assertFalse(codelab_get_user_grades($codelab, 0));
    }
}
