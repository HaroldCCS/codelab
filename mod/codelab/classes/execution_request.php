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
 * Lógica del endpoint de ejecución y calificación, separada del transporte HTTP.
 *
 * `execute.php` queda como una envoltura delgada que lee el cuerpo de la petición y delega
 * aquí. El motivo es que la entrada sea INYECTABLE: esta clase recibe el array ya decodificado,
 * de modo que una prueba puede construirlo sin que exista una petición HTTP detrás. Es el mismo
 * patrón que ya usan `grader` (ejecutor inyectable) y `submission_manager` (actividad y contexto
 * por constructor).
 *
 * El código del estudiante NO se ejecuta aquí: se delega en `grader`, que a su vez lo envía al
 * motor aislado en contenedor. Esta clase solo valida, ordena y arma la respuesta.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/execution_exception.php');
require_once(__DIR__ . '/grader.php');
require_once(__DIR__ . '/submission_manager.php');

class execution_request {

    /** Tamaño máximo aceptado para el código del estudiante, en bytes (RNF-14). */
    public const MAX_CODE_BYTES = 65536;

    /** @var array Entrada ya decodificada: el JSON del cuerpo, o el array que fija una prueba. */
    private array $input;

    /** @var \mod_codelab\executor\base_executor|null Ejecutor a usar; null = el motor real. */
    private ?\mod_codelab\executor\base_executor $executor;

    /**
     * @param array $input Datos de la petición: cmid, code, language, action, sesskey.
     *        Nunca incluye el identificador de usuario: ese sale de $USER tras require_login(),
     *        para que nadie pueda actuar en nombre de otro pasándolo por el cuerpo.
     * @param \mod_codelab\executor\base_executor|null $executor Ejecutor que recibe el
     *        `grader`. Null en producción (motor Judge0); una prueba inyecta uno simulado para
     *        no depender de que haya un motor escuchando.
     */
    public function __construct(array $input, ?\mod_codelab\executor\base_executor $executor = null) {
        $this->input    = $input;
        $this->executor = $executor;
    }

    /**
     * Valida la petición, califica y devuelve la respuesta.
     *
     * No imprime nada ni llama a exit(): los fallos de validación se lanzan como
     * execution_exception y quien invoque decide cómo representarlos. Eso es justo lo que
     * permite ejercitar cada comprobación por separado en una prueba.
     *
     * @return array Respuesta lista para json_encode().
     * @throws execution_exception Si alguna validación de la actividad no pasa.
     */
    public function handle(): array {
        global $DB, $USER;

        $cmid     = (int)($this->input['cmid'] ?? 0);
        $code     = (string)($this->input['code'] ?? '');
        $language = clean_param($this->input['language'] ?? 'python', PARAM_ALPHA);
        $action   = clean_param($this->input['action'] ?? 'run', PARAM_ALPHA);
        $sesskey  = (string)($this->input['sesskey'] ?? '');

        if (!$cmid || empty($code)) {
            throw new execution_exception('missingparam', 'error', '', 'cmid/code');
        }

        $cm      = get_coursemodule_from_id('codelab', $cmid, 0, false, MUST_EXIST);
        $course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $codelab = $DB->get_record('codelab', ['id' => $cm->instance], '*', MUST_EXIST);

        // Orden canónico de Moodle (SEC-18): primero QUIÉN es, luego SI PUEDE, y solo entonces
        // el sesskey. Antes el sesskey se validaba el primero de todos, lo que además impedía
        // llegar a estas comprobaciones desde una prueba. Ninguna escritura ocurre antes de la
        // validación del sesskey, que es lo que exige la protección contra CSRF.
        require_login($course, false, $cm);
        $context = \context_module::instance($cm->id);
        require_capability('mod/codelab:execute', $context);

        if ($sesskey === '' || !confirm_sesskey($sesskey)) {
            throw new execution_exception('invalidsesskey', 'error');
        }

        // Validar lenguaje permitido.
        $allowed = json_decode($codelab->languages_allowed ?? '[]', true) ?: [$codelab->default_language];
        if (!in_array($language, $allowed)) {
            throw new execution_exception('language_not_allowed', 'mod_codelab');
        }

        // Validar tamaño del código (RNF-14).
        if (strlen($code) > self::MAX_CODE_BYTES) {
            throw new execution_exception('code_too_large', 'mod_codelab');
        }

        // Guardar el código ORIGINAL del estudiante (sin envolver) para la IA.
        $student_code = $code;

        // Si hay un runner_code definido, envolver el código del estudiante.
        if (!empty(trim($codelab->runner_code ?? ''))) {
            $runner = $codelab->runner_code;
            if (strpos($runner, '{{CODE}}') !== false) {
                $code = str_replace('{{CODE}}', $code, $runner);
            } else {
                // Si no hay marcador, poner el código del estudiante al inicio.
                $code = $code . "\n" . $runner;
            }
        }

        // Obtener todos los casos de prueba (incluyendo ocultos para la calificación).
        $testcases = $DB->get_records('codelab_testcases', ['codelabid' => $codelab->id], 'ordering ASC, id ASC');

        if (empty($testcases)) {
            throw new execution_exception('no_testcases', 'mod_codelab');
        }

        // Una entrega que de todos modos se va a rechazar no debe llegar al motor: antes estas
        // comprobaciones iban DESPUÉS de ejecutar, así que un estudiante sin intentos o fuera de
        // plazo seguía consumiendo ejecuciones de código no confiable con cada clic en
        // «Entregar». Se valida primero y se ejecuta solo lo que se va a guardar.
        if ($action === 'submit') {
            require_capability('mod/codelab:submit', $context);

            $submission_manager = new submission_manager($codelab, $context);

            if (!$submission_manager->can_submit($USER->id)) {
                throw new execution_exception('max_attempts_reached', 'mod_codelab');
            }

            // La actividad puede no estar abierta todavía. Hasta ahora este ajuste se
            // guardaba y no lo leía nadie: el docente fijaba la fecha creyendo que cerraba la
            // actividad y los estudiantes entregaban igual (INC del barrido inverso). Se
            // comprueba aquí, que es donde de verdad se bloquea, no solo en la vista.
            if ($codelab->allow_submissions_from_date > 0
                && time() < $codelab->allow_submissions_from_date
            ) {
                // El tercer argumento de moodle_exception es el enlace; el cuarto, el {$a} de
                // la cadena. Sin él, el estudiante vería «{$a}» literal en el mensaje.
                throw new execution_exception(
                    'before_allow_from',
                    'mod_codelab',
                    '',
                    userdate($codelab->allow_submissions_from_date)
                );
            }

            // Verificar fecha de corte.
            if ($codelab->cutoffdate > 0 && time() > $codelab->cutoffdate) {
                throw new execution_exception('past_cutoff', 'mod_codelab');
            }
        }

        $grader = new grader($codelab, $this->executor);
        $grading_result = $grader->run_all_testcases($code, $language, $testcases);

        // Si es una entrega definitiva, guardar en BD y actualizar la calificación.
        if ($action === 'submit') {
            $submission = $submission_manager->save_draft($USER->id, $student_code, $language);
            $grader->save_results($submission->id, $grading_result);
            $submission_manager->finalize_submission($submission->id);

            $grading_result['submission_id'] = $submission->id;
            $grading_result['submitted']     = true;
            $grading_result['message']       = get_string('submission_saved', 'mod_codelab');

            // Recomendaciones cualitativas por IA (opcional). NUNCA afectan la nota:
            // la calificación ya fue calculada y guardada arriba. La generación y su
            // persistencia (con aislamiento de errores) viven en feedback_generator.
            if (\mod_codelab\ai\feedback_generator::is_enabled_for($codelab)) {
                $grading_result['ai_feedback'] = \mod_codelab\ai\feedback_generator::run_for_submission(
                    $codelab, $submission->id, $student_code, $language, $grading_result
                );
            }
        }

        return $this->build_response($codelab, $context, $testcases, $grading_result);
    }

    /**
     * Arma la respuesta filtrando lo que el estudiante no debe ver.
     *
     * @param \stdClass $codelab Instancia de la actividad.
     * @param \context_module $context Contexto del módulo.
     * @param array $testcases Casos de prueba indexados por id.
     * @param array $grading_result Resultado devuelto por el grader.
     * @return array
     */
    private function build_response(
        \stdClass $codelab,
        \context_module $context,
        array $testcases,
        array $grading_result
    ): array {
        $is_teacher = has_capability('mod/codelab:viewallsubmissions', $context);

        $base = [
            'success'      => true,
            'tests_passed' => $grading_result['tests_passed'],
            'tests_total'  => $grading_result['tests_total'],
            'grade'        => $grading_result['grade'],
            'max_grade'    => (float)$codelab->grade,
            'submitted'    => $grading_result['submitted'] ?? false,
            'message'      => $grading_result['message'] ?? '',
            'ai_feedback'  => $grading_result['ai_feedback'] ?? null,
        ];

        // Si los casos no son visibles para el estudiante, solo el resumen: sin detalle alguno.
        if (!$is_teacher && !(bool)$codelab->testcases_visible) {
            return array_merge($base, ['results' => []]);
        }

        // Ocultar los detalles de los casos marcados como ocultos.
        $visible_results = [];
        foreach ($grading_result['results'] as $r) {
            $tc = $testcases[$r['testcase_id']] ?? null;
            $is_hidden = $tc && $tc->is_hidden;

            if (!$is_teacher && $is_hidden) {
                $visible_results[] = [
                    'testcase_id'   => $r['testcase_id'],
                    'testcase_name' => get_string('hidden_testcase', 'mod_codelab'),
                    'status'        => $r['passed'] ? 'passed' : 'failed',
                    'passed'        => $r['passed'],
                    'points_earned' => $r['points_earned'],
                    'points_possible' => $r['points_possible'],
                    'hidden'        => true,
                ];
            } else {
                $visible_results[] = array_merge($r, ['hidden' => false]);
            }
        }

        return array_merge($base, ['results' => $visible_results]);
    }
}
