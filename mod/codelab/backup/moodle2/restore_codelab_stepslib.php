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
 * Estructura de restauración para mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class restore_codelab_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure(): array {
        $paths = [];

        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('codelab', '/activity/codelab');
        $paths[] = new restore_path_element('codelab_testcase', '/activity/codelab/testcases/testcase');

        if ($userinfo) {
            $paths[] = new restore_path_element('codelab_submission', '/activity/codelab/submissions/submission');
            $paths[] = new restore_path_element('codelab_submission_result',
                '/activity/codelab/submissions/submission/results/result');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_codelab(array $data): void {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $oldid  = $data->id;
        $newid  = $DB->insert_record('codelab', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('codelab', $oldid, $newid);
    }

    protected function process_codelab_testcase(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->codelabid = $this->get_new_parentid('codelab');
        $newid = $DB->insert_record('codelab_testcases', $data);
        $this->set_mapping('codelab_testcase', $oldid, $newid);
    }

    protected function process_codelab_submission(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->codelabid = $this->get_new_parentid('codelab');
        $data->userid    = $this->get_mappingid('user', $data->userid);
        $newid = $DB->insert_record('codelab_submissions', $data);

        // Sin este mapeo, los resultados por caso de prueba no pueden reengancharse a su entrega.
        $this->set_mapping('codelab_submission', $oldid, $newid);
    }

    protected function process_codelab_submission_result(array $data): void {
        global $DB;

        $data = (object)$data;

        // Los dos extremos de la relación se remapean a los ids nuevos: la entrega por el padre
        // en curso, y el caso de prueba por el mapeo que dejó process_codelab_testcase().
        $data->submission_id = $this->get_new_parentid('codelab_submission');
        $data->testcase_id   = $this->get_mappingid('codelab_testcase', $data->testcase_id);

        // Si el caso de prueba ya no existe en el origen, el resultado queda huérfano: se omite
        // en lugar de insertar una fila que apunte a un id inexistente.
        if (empty($data->testcase_id)) {
            return;
        }

        $DB->insert_record('codelab_submission_results', $data);
    }

    protected function after_execute(): void {
        $this->add_related_files('mod_codelab', 'intro', null);
    }
}
