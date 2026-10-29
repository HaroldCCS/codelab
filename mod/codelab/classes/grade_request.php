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
 * Lógica de la calificación manual del docente, separada del transporte HTTP.
 *
 * `grade.php` queda como una envoltura delgada que lee los parámetros, delega aquí y
 * redirige. Antes la lógica vivía en el propio script, y la única forma de probarla era
 * incluirlo con `include`: como todo script de entrada de Moodle hace
 * `require('../../config.php')` relativo al directorio actual, bajo PHPUnit la inclusión
 * fallaba y las pruebas no llegaban a ejercitar nada. Es el mismo patrón que
 * `execution_request` con `execute.php`.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/grader.php');

class grade_request {

    /** @var array Entrada: id (cmid), sid, grade_override (?float), feedback, sesskey. */
    private array $input;

    /**
     * @param array $input Parámetros ya limpiados por quien invoca (grade.php usa
     *        required_param/optional_param). grade_override null = sin corrección manual.
     */
    public function __construct(array $input) {
        $this->input = $input;
    }

    /**
     * Valida permisos y sesskey, guarda la corrección y actualiza el libro de calificaciones.
     *
     * @return array ['grade' => ?float nota que quedó en el libro, 'method' => método de calificación]
     * @throws \required_capability_exception Si el usuario no puede calificar.
     * @throws \moodle_exception Si el sesskey no es válido.
     */
    public function handle(): array {
        global $DB;

        $cmid     = (int)($this->input['id'] ?? 0);
        $sid      = (int)($this->input['sid'] ?? 0);
        $override = $this->input['grade_override'] ?? null;
        $feedback = (string)($this->input['feedback'] ?? '');
        $sesskey  = (string)($this->input['sesskey'] ?? '');

        $cm      = get_coursemodule_from_id('codelab', $cmid, 0, false, MUST_EXIST);
        $course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $codelab = $DB->get_record('codelab', ['id' => $cm->instance], '*', MUST_EXIST);

        // Mismo orden que require_sesskey() en un script: quién es, si puede, y solo entonces
        // el sesskey. Ninguna escritura ocurre antes de las tres comprobaciones.
        require_login($course, true, $cm);
        $context = \context_module::instance($cm->id);
        require_capability('mod/codelab:grade', $context);
        if ($sesskey === '' || !confirm_sesskey($sesskey)) {
            throw new \moodle_exception('invalidsesskey', 'error');
        }

        $submission = $DB->get_record('codelab_submissions', ['id' => $sid, 'codelabid' => $codelab->id], '*', MUST_EXIST);

        $submission->feedback     = $feedback;
        $submission->timemodified = time();

        if ($override !== null && $override >= 0 && $override <= $codelab->grade) {
            $submission->grade_override = $override;
        } else {
            $submission->grade_override = null;
        }

        $DB->update_record('codelab_submissions', $submission);

        // El libro de notas lo fija la MISMA función que usan la calificación automática y el
        // recálculo (INC-101). Antes esta ruta escribía la nota de esta entrega sin mirar
        // `grading_method`, así que el libro mostraba un número distinto según quién lo hubiera
        // escrito por última vez, y el primer recálculo lo cambiaba. La corrección del docente no
        // se pierde: entra como nota efectiva de esta entrega, y el método configurado decide qué
        // entrega cuenta.
        $grade_to_set = grader::compute_final_grade($codelab, (int)$submission->userid);

        if ($grade_to_set !== null) {
            $grades = [
                $submission->userid => [
                    'userid'   => $submission->userid,
                    'rawgrade' => $grade_to_set,
                ],
            ];
            codelab_grade_item_update($codelab, $grades);
        }

        return ['grade' => $grade_to_set, 'method' => $codelab->grading_method];
    }
}
