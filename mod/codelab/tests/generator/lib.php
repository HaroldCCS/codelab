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
 * Generador de datos de prueba para mod_codelab.
 *
 * Permite crear instancias de la actividad en las pruebas con:
 *   $this->getDataGenerator()->get_plugin_generator('mod_codelab')->create_instance([...]);
 *
 * @package   mod_codelab
 * @category  test
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generador del módulo CodeLab.
 */
class mod_codelab_generator extends testing_module_generator {

    /**
     * Crea una instancia de CodeLab con valores por defecto sensatos.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        $defaults = [
            'name'                      => 'CodeLab de prueba',
            'intro'                     => 'Descripción de la actividad',
            'introformat'               => FORMAT_HTML,
            'default_language'          => 'python',
            'languages_allowed'         => json_encode(['python', 'javascript']),
            'starter_code'              => '',
            'solution_code'             => '',
            'time_limit'                => 5,
            'memory_limit'              => 128,
            'max_attempts'              => 0,
            'grading_method'            => 'best',
            'grade'                     => 100,
            'testcases_visible'         => 1,
            'allow_submissions_from_date' => 0,
            'duedate'                   => 0,
            'cutoffdate'                => 0,
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->{$field})) {
                $record->{$field} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Crea un caso de prueba asociado a una actividad.
     *
     * @param array $record Debe incluir 'codelabid'. Opcionales: name, stdin,
     *                      expected_output, points, is_hidden, ordering.
     * @return stdClass El registro insertado.
     */
    public function create_testcase(array $record): stdClass {
        global $DB;

        if (empty($record['codelabid'])) {
            throw new coding_exception('create_testcase requiere codelabid');
        }

        $tc = (object)array_merge([
            'name'            => 'Caso',
            'description'     => '',
            'stdin'           => '',
            'expected_output' => '',
            'points'          => 1,
            'is_hidden'       => 0,
            'ordering'        => 0,
            'timecreated'     => time(),
        ], $record);

        $tc->id = $DB->insert_record('codelab_testcases', $tc);
        return $tc;
    }
}
