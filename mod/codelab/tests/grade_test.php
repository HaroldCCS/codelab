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
 * Pruebas de grade.php: la calificación manual del docente.
 *
 * Antes de este archivo, ninguno de los 12 archivos de tests/ mencionaba grade.php
 * (comprobado con `grep -rl "grade.php" tests/`): una funcionalidad que modifica
 * calificaciones no tenía ni una sola prueba automatizada (D-03 de decisiones-pendientes.md).
 *
 * La lógica de grade.php vive en \mod_codelab\grade_request (grade.php solo lee los parámetros
 * y redirige), así que estas pruebas llaman a la clase directamente. La versión anterior
 * incluía grade.php con `include`: su `require('../../config.php')` es relativo al directorio
 * actual y bajo PHPUnit nunca se resolvía, de modo que las pruebas fallaban sin haber
 * ejercitado nada (o, peor, se tragaban el error en un `catch (\Throwable)`).
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\grade_request
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/codelab/classes/grade_request.php');

final class grade_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $codelab;
    /** @var \stdClass */
    private $cm;
    /** @var \context_module */
    private $context;
    /** @var \stdClass */
    private $student;
    /** @var int */
    private $submissionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $this->course->id, 'grade' => 100]);
        $this->cm = get_coursemodule_from_instance('codelab', $this->codelab->id);
        $this->context = \context_module::instance($this->cm->id);

        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');

        // Entrega ya calificada automáticamente (grade = 80) que el docente va a corregir
        // a mano. Si algo falla antes de tiempo, este es el estado "antes" con el que se
        // compara.
        $this->submissionid = $this->add_submission($this->student->id, 80.0);
    }

    private function add_submission(int $userid, float $grade): int {
        global $DB;
        $s = new \stdClass();
        $s->codelabid      = $this->codelab->id;
        $s->userid         = $userid;
        $s->attempt_number = 1;
        $s->code           = "print('42')";
        $s->language       = 'python';
        $s->status         = 'submitted';
        $s->grade          = $grade;
        $s->grade_override = null;
        $s->feedback       = '';
        $s->tests_passed   = 1;
        $s->tests_total    = 1;
        $s->timecreated    = time();
        $s->timemodified   = time();
        return $DB->insert_record('codelab_submissions', $s);
    }

    /**
     * Simula el envío del formulario de calificación manual de submission.php: los mismos
     * parámetros que grade.php lee con required_param/optional_param.
     */
    private function calificar(array $params): array {
        return (new grade_request($params))->handle();
    }

    public function test_grade_sin_capacidad_lanza_excepcion_y_no_modifica_la_entrega(): void {
        global $DB;

        // Por defecto (db/access.php) mod/codelab:grade solo lo tienen teacher,
        // editingteacher y manager: un estudiante no lo tiene.
        $this->setUser($this->student);
        $sesskey = sesskey();

        $antes = $DB->get_record('codelab_submissions', ['id' => $this->submissionid]);

        $this->expectException(\required_capability_exception::class);
        try {
            $this->calificar([
                'id'             => $this->cm->id,
                'sid'            => $this->submissionid,
                'grade_override' => 95,
                'feedback'       => 'Nota puesta por un estudiante sin permiso',
                'sesskey'        => $sesskey,
            ]);
        } finally {
            // Aunque el estudiante conozca el sid y un sesskey válido de su propia sesión,
            // la entrega no debe haber cambiado: la falta de capacidad debe cortar ANTES
            // de tocar la base de datos.
            $despues = $DB->get_record('codelab_submissions', ['id' => $this->submissionid]);
            $this->assertEquals($antes->grade_override, $despues->grade_override);
            $this->assertEquals($antes->feedback, $despues->feedback);
        }
    }

    public function test_grade_con_sesskey_invalida_lanza_excepcion_y_no_modifica_la_entrega(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $antes = $DB->get_record('codelab_submissions', ['id' => $this->submissionid]);

        $this->expectException(\moodle_exception::class);
        try {
            $this->calificar([
                'id'             => $this->cm->id,
                'sid'            => $this->submissionid,
                'grade_override' => 95,
                'feedback'       => 'Nota puesta con sesskey falsa (CSRF)',
                'sesskey'        => 'sesskey-invalida-a-proposito',
            ]);
        } finally {
            // Un docente con la capacidad correcta pero sin un sesskey válido tampoco debe
            // poder cambiar la nota: es la protección contra CSRF, y también debe cortar
            // antes de escribir nada.
            $despues = $DB->get_record('codelab_submissions', ['id' => $this->submissionid]);
            $this->assertEquals($antes->grade_override, $despues->grade_override);
            $this->assertEquals($antes->feedback, $despues->feedback);
        }
    }

    public function test_grade_actualiza_la_nota_y_el_libro_de_calificaciones(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $resultado = $this->calificar([
            'id'             => $this->cm->id,
            'sid'            => $this->submissionid,
            'grade_override' => 95.5,
            'feedback'       => 'Buen trabajo, corregido a mano.',
            'sesskey'        => sesskey(),
        ]);
        $this->assertEqualsWithDelta(95.5, $resultado['grade'], 0.001);

        $actualizada = $DB->get_record('codelab_submissions', ['id' => $this->submissionid]);
        $this->assertEqualsWithDelta(95.5, (float)$actualizada->grade_override, 0.001);
        $this->assertSame('Buen trabajo, corregido a mano.', $actualizada->feedback);

        $grades = grade_get_grades(
            $this->course->id, 'mod', 'codelab', $this->codelab->id, $this->student->id
        );
        $notafinal = (float)$grades->items[0]->grades[$this->student->id]->grade;
        $this->assertEqualsWithDelta(95.5, $notafinal, 0.001, 'La nota manual debe propagarse al libro de calificaciones');
    }

    public function test_grade_override_fuera_de_rango_no_reemplaza_la_nota_automatica(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->calificar([
            'id'             => $this->cm->id,
            'sid'            => $this->submissionid,
            // codelab->grade es 100: 999 está fuera de rango y debe descartarse para
            // conservar la nota automática (80), no truncarse ni aceptarse.
            'grade_override' => 999,
            'feedback'       => 'Intento de nota fuera de rango',
            'sesskey'        => sesskey(),
        ]);

        $actualizada = $DB->get_record('codelab_submissions', ['id' => $this->submissionid]);
        $this->assertNull($actualizada->grade_override,
            'Un grade_override fuera de rango (> codelab->grade) no debe guardarse');

        $grades = grade_get_grades(
            $this->course->id, 'mod', 'codelab', $this->codelab->id, $this->student->id
        );
        $notafinal = (float)$grades->items[0]->grades[$this->student->id]->grade;
        $this->assertEqualsWithDelta(80.0, $notafinal, 0.001,
            'Con el override rechazado, el libro de calificaciones debe conservar la nota automática');
    }
}
