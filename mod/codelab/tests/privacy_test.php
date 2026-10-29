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
 * Pruebas del proveedor de privacidad (Privacy API).
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\privacy\provider
 */

namespace mod_codelab;

use mod_codelab\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

final class privacy_test extends \core_privacy\tests\provider_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $codelab;
    /** @var \context_module */
    private $context;
    /** @var \stdClass */
    private $user1;
    /** @var \stdClass */
    private $user2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('codelab', $this->codelab->id);
        $this->context = \context_module::instance($cm->id);
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->add_submission($this->user1->id);
        $this->add_submission($this->user2->id);
    }

    private function add_submission(int $userid): int {
        global $DB;
        $s = new \stdClass();
        $s->codelabid = $this->codelab->id; $s->userid = $userid; $s->attempt_number = 1;
        $s->code = 'print(1)'; $s->language = 'python'; $s->status = 'submitted';
        $s->grade = 100; $s->tests_passed = 1; $s->tests_total = 1;
        $s->timecreated = time(); $s->timemodified = time();
        return $DB->insert_record('codelab_submissions', $s);
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_codelab'));
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);
    }

    public function test_get_contexts_for_userid(): void {
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        // Los ids llegan de la BD como cadenas; assertContains compara con ===.
        $ids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int)$this->context->id, $ids);
    }

    public function test_get_users_in_context(): void {
        $userlist = new \core_privacy\local\request\userlist($this->context, 'mod_codelab');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertContains((int)$this->user1->id, $userids);
        $this->assertContains((int)$this->user2->id, $userids);
    }

    public function test_export_user_data(): void {
        $approved = new approved_contextlist($this->user1, 'mod_codelab', [$this->context->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
    }

    public function test_export_user_data_incluye_resultados_de_casos_de_prueba(): void {
        global $DB;

        // Entrega con un resultado de caso de prueba: la salida real del programa del
        // estudiante (INC-62) es dato personal y debe viajar en la exportación, no solo
        // quedar accesible al borrado. Antes de esta prueba, test_export_user_data solo
        // comprobaba has_any_data(), que ya daba true con la exportación incompleta: no
        // distinguía el antes del después de INC-62.
        $submissionid = $this->add_submission($this->user1->id);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');
        $tc = $gen->create_testcase(['codelabid' => $this->codelab->id, 'expected_output' => "42\n"]);

        $resultrow = new \stdClass();
        $resultrow->submission_id = $submissionid;
        $resultrow->testcase_id   = $tc->id;
        $resultrow->status        = 'passed';
        $resultrow->actual_output = "salida-marcada-42\n";
        $resultrow->timecreated   = time();
        $DB->insert_record('codelab_submission_results', $resultrow);

        $approved = new approved_contextlist($this->user1, 'mod_codelab', [$this->context->id]);
        provider::export_user_data($approved);

        $data = writer::with_context($this->context)
            ->get_data([get_string('privacy:path:submissions', 'mod_codelab')]);

        $this->assertNotEmpty($data->submissions, 'La exportación debe traer al menos una entrega');

        // Ubicar la entrega recién creada dentro de lo exportado.
        $exportada = null;
        foreach ($data->submissions as $submission) {
            if ((int)$submission->id === $submissionid) {
                $exportada = $submission;
                break;
            }
        }
        $this->assertNotNull($exportada, 'La entrega con resultado no aparece en la exportación');

        // La aserción que faltaba: que trae sus resultados, no solo los campos de
        // codelab_submissions. Si alguien vuelve a dejar codelab_submission_results
        // fuera de export_user_data(), esto falla con "results" vacío.
        $this->assertNotEmpty(
            $exportada->results,
            'codelab_submission_results ha vuelto a quedar fuera de la exportación (INC-62)'
        );

        $salidas = [];
        foreach ($exportada->results as $r) {
            $salidas[] = $r->actual_output;
        }
        $this->assertContains(
            "salida-marcada-42\n",
            $salidas,
            'El actual_output almacenado debe llegar tal cual a la exportación'
        );
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
    }

    public function test_delete_data_for_user_only_removes_that_user(): void {
        global $DB;
        $approved = new approved_contextlist($this->user1, 'mod_codelab', [$this->context->id]);
        provider::delete_data_for_user($approved);

        $this->assertSame(0, $DB->count_records('codelab_submissions',
            ['codelabid' => $this->codelab->id, 'userid' => $this->user1->id]));
        // El usuario 2 conserva sus datos.
        $this->assertSame(1, $DB->count_records('codelab_submissions',
            ['codelabid' => $this->codelab->id, 'userid' => $this->user2->id]));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $approved = new approved_userlist($this->context, 'mod_codelab', [$this->user2->id]);
        provider::delete_data_for_users($approved);

        $this->assertSame(1, $DB->count_records('codelab_submissions',
            ['codelabid' => $this->codelab->id, 'userid' => $this->user1->id]));
        $this->assertSame(0, $DB->count_records('codelab_submissions',
            ['codelabid' => $this->codelab->id, 'userid' => $this->user2->id]));
    }
}
