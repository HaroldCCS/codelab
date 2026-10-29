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
 * Prueba de copia de seguridad y restauración de la actividad.
 *
 * Verifica que, al hacer backup de un curso y restaurarlo en otro, la actividad
 * CodeLab y sus casos de prueba se conservan.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 * @group      mod_codelab
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

final class backup_test extends \advanced_testcase {

    public function test_backup_and_restore_preserves_activity_and_testcases(): void {
        global $DB, $USER, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Curso origen con una actividad y dos casos de prueba.
        $course1 = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $codelab = $gen->create_instance(['course' => $course1->id, 'name' => 'Ejercicio backup', 'grade' => 100]);
        $gen->create_testcase(['codelabid' => $codelab->id, 'name' => 'Caso A', 'expected_output' => '10', 'points' => 60, 'ordering' => 0]);
        $gen->create_testcase(['codelabid' => $codelab->id, 'name' => 'Caso B', 'expected_output' => '20', 'points' => 40, 'ordering' => 1]);

        // Curso destino.
        $course2 = $this->getDataGenerator()->create_course();

        // Backup del curso 1 en modo importación.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE, $course1->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_IMPORT, $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restauración en el curso 2.
        $rc = new \restore_controller(
            $backupid, $course2->id, \backup::INTERACTIVE_NO, \backup::MODE_IMPORT,
            $USER->id, \backup::TARGET_CURRENT_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // El curso 2 debe tener una instancia de codelab con sus 2 casos.
        $instances = $DB->get_records('codelab', ['course' => $course2->id]);
        $this->assertCount(1, $instances);
        $restored = reset($instances);
        $this->assertSame('Ejercicio backup', $restored->name);

        $testcases = $DB->get_records('codelab_testcases', ['codelabid' => $restored->id]);
        $this->assertCount(2, $testcases);
    }

    public function test_backup_and_restore_preserves_submission_results(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Curso origen con una entrega y su resultado por caso de prueba: la tabla
        // codelab_submission_results guarda la salida real del programa del estudiante
        // (actual_output), la misma que la Privacy API declara y exporta como dato personal
        // (INC-62). MDL-01 era justamente que, aun incluyendo datos de usuario en el backup,
        // la entrega sobrevivía pero su resultado por caso de prueba se perdía.
        $course1 = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $codelab = $gen->create_instance(['course' => $course1->id, 'name' => 'Ejercicio con resultados', 'grade' => 100]);
        $tc = $gen->create_testcase(['codelabid' => $codelab->id, 'name' => 'Caso único', 'expected_output' => '10', 'points' => 100]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, 'student');

        $submission = new \stdClass();
        $submission->codelabid      = $codelab->id;
        $submission->userid         = $student->id;
        $submission->attempt_number = 1;
        $submission->code           = "print('10')";
        $submission->language       = 'python';
        $submission->status         = 'submitted';
        $submission->grade          = 100;
        $submission->tests_passed   = 1;
        $submission->tests_total    = 1;
        $submission->timecreated    = time();
        $submission->timemodified   = time();
        $submission->id = $DB->insert_record('codelab_submissions', $submission);

        $result = new \stdClass();
        $result->submission_id  = $submission->id;
        $result->testcase_id    = $tc->id;
        $result->status         = 'passed';
        $result->actual_output  = "salida-original-10\n";
        $result->timecreated    = time();
        $DB->insert_record('codelab_submission_results', $result);

        $course2 = $this->getDataGenerator()->create_course();

        // A diferencia de la prueba anterior (solo testcases, que no dependen de userinfo),
        // aquí hay que incluir datos de usuario: el árbol de backup_codelab_stepslib.php cuelga
        // <submissions>/<results> de "if ($userinfo)". Por eso NO sirve MODE_IMPORT: en ese
        // modo Moodle bloquea el ajuste "users" (setting_locked_by_permission), porque importar
        // entre cursos nunca copia datos de estudiantes. Se hace una copia de seguridad normal
        // (MODE_GENERAL), se descomprime y se restaura, igual que un docente desde la interfaz.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE, $course1->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $backupid = 'codelab-backup-test';
        $file->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupid)
        );

        $rc = new \restore_controller(
            $backupid, $course2->id, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL,
            $USER->id, \backup::TARGET_EXISTING_ADDING
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restoredcodelab = $DB->get_record('codelab', ['course' => $course2->id], '*', MUST_EXIST);
        $restoredsubmissions = $DB->get_records('codelab_submissions', ['codelabid' => $restoredcodelab->id]);
        $this->assertCount(
            1,
            $restoredsubmissions,
            'La entrega debe restaurarse cuando el backup incluye datos de usuario'
        );

        $restoredsubmission = reset($restoredsubmissions);
        $restoredresults = $DB->get_records('codelab_submission_results', ['submission_id' => $restoredsubmission->id]);
        $this->assertCount(
            1,
            $restoredresults,
            'codelab_submission_results debe sobrevivir al backup/restore (MDL-01): antes de la '
            . 'corrección, la entrega se restauraba pero su resultado por caso de prueba se perdía'
        );

        $restoredresult = reset($restoredresults);
        $this->assertSame(
            "salida-original-10\n",
            $restoredresult->actual_output,
            'El actual_output original debe llegar intacto, no solo el conteo de filas'
        );
    }
}
