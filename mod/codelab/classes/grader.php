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
 * Sistema de calificación automática para CodeLab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/executor/base_executor.php');
require_once(__DIR__ . '/executor/judge0_executor.php');

class grader {

    /**
     * Orden canónico de los intentos de un estudiante, del más antiguo al más reciente.
     *
     * El desempate por `id` no es decorativo: dos intentos creados en el mismo segundo comparten
     * `timecreated`, y sin criterio adicional el motor de base de datos los devuelve en el orden
     * que le convenga, que puede cambiar entre dos cargas de la misma página. Con los métodos
     * «primer intento» y «último intento» eso significa dos notas distintas para los mismos datos.
     *
     * **Cualquier consulta del complemento que ordene entregas por tiempo debe desempatar por
     * `id`.** Esta constante la aplica donde el orden se pasa como parámetro. Donde el ORDER BY
     * va escrito dentro de un SQL —porque lleva alias de tabla o dirección descendente— no se
     * puede reutilizar la cadena, y entonces el desempate va a mano con un comentario que apunta
     * aquí. Es más débil, y conviene saberlo: nada impide que una consulta futura lo olvide. Las
     * consultas que hoy lo llevan a mano son `get_best_submission()`, `get_last_submission()` y
     * `get_all_latest_submissions()`, todas en `submission_manager`.
     */
    public const ORDER_ATTEMPTS_ASC = 'timecreated ASC, id ASC';

    /** @var \stdClass Instancia de la actividad */
    private \stdClass $codelab;
    /** @var \mod_codelab\executor\base_executor Ejecutor de código */
    private \mod_codelab\executor\base_executor $executor;

    /**
     * @param \stdClass $codelab Instancia de la actividad.
     * @param \mod_codelab\executor\base_executor|null $executor Ejecutor a usar.
     *        Si es null se crea un judge0_executor con los límites de la actividad.
     *        Permite inyectar un ejecutor simulado en las pruebas (sin red).
     */
    public function __construct(\stdClass $codelab, ?\mod_codelab\executor\base_executor $executor = null) {
        $this->codelab  = $codelab;
        $this->executor = $executor ?? new \mod_codelab\executor\judge0_executor(
            (int)$codelab->time_limit,
            (int)$codelab->memory_limit
        );
    }

    /**
     * Ejecuta todos los casos de prueba y retorna los resultados con la calificación.
     *
     * @param string $code     Código a evaluar
     * @param string $language Lenguaje de programación
     * @param array  $testcases Array de objetos de casos de prueba
     * @return array ['results' => [...], 'grade' => float, 'tests_passed' => int, 'tests_total' => int]
     */
    public function run_all_testcases(string $code, string $language, array $testcases): array {
        $results      = [];
        $total_points = 0;
        $earned_points = 0;
        $tests_passed = 0;
        $tests_total  = count($testcases);

        foreach ($testcases as $tc) {
            $result = $this->executor->execute($code, $language, $tc->stdin ?? '');

            $passed = $result->status === 'accepted'
                      && $result->matches_expected($tc->expected_output);

            $tc_points = (float)($tc->points ?? 1);
            $total_points  += $tc_points;
            if ($passed) {
                $earned_points += $tc_points;
                $tests_passed++;
            }

            $results[] = [
                'testcase_id'    => (int)$tc->id,
                'testcase_name'  => $tc->name,
                'status'         => $passed ? 'passed' : $this->map_status($result->status, $passed),
                'actual_output'  => $result->stdout,
                'expected_output' => $tc->expected_output,
                'stderr'         => $result->stderr,
                'compile_output' => $result->compile_output,
                'execution_time' => $result->time,
                'memory_used'    => $result->memory,
                'passed'         => $passed,
                'points_earned'  => $passed ? $tc_points : 0,
                'points_possible' => $tc_points,
            ];
        }

        // Calcular la nota como proporción sobre la nota máxima.
        $raw_grade = $total_points > 0
            ? ($earned_points / $total_points) * (float)$this->codelab->grade
            : 0;

        return [
            'results'      => $results,
            'grade'        => round($raw_grade, 2),
            'tests_passed' => $tests_passed,
            'tests_total'  => $tests_total,
            'points_earned' => $earned_points,
            'points_total'  => $total_points,
        ];
    }

    /**
     * Guarda los resultados en la base de datos y actualiza el libro de notas.
     */
    public function save_results(int $submission_id, array $grading_result): void {
        global $DB;

        // Guardar resultados individuales.
        $DB->delete_records('codelab_submission_results', ['submission_id' => $submission_id]);

        foreach ($grading_result['results'] as $res) {
            $record = new \stdClass();
            $record->submission_id  = $submission_id;
            $record->testcase_id    = $res['testcase_id'];
            $record->status         = $res['status'];
            $record->actual_output  = $res['actual_output'];
            $record->stderr         = $res['stderr'] ?? '';
            $record->compile_output = $res['compile_output'] ?? '';
            $record->execution_time = $res['execution_time'];
            $record->memory_used    = $res['memory_used'];
            $record->timecreated    = time();
            $DB->insert_record('codelab_submission_results', $record);
        }

        // Actualizar la entrega.
        $submission = $DB->get_record('codelab_submissions', ['id' => $submission_id]);
        $submission->grade        = $grading_result['grade'];
        $submission->tests_passed = $grading_result['tests_passed'];
        $submission->tests_total  = $grading_result['tests_total'];
        $submission->timemodified = time();
        $DB->update_record('codelab_submissions', $submission);

        // Actualizar el libro de notas según el método.
        $this->update_gradebook($submission->userid, $grading_result['grade']);
    }

    /**
     * Calcula la nota final de un usuario en la actividad. Fuente ÚNICA de ese cálculo.
     *
     * Es estática y no necesita un ejecutor a propósito: calcular una nota nunca toca el motor
     * de ejecución ni la IA. Eso permite además que el libro de calificaciones la invoque en un
     * recálculo masivo sin construir un cliente HTTP por cada usuario.
     *
     * Dos reglas, en este orden:
     *  1. **Nota efectiva de cada intento**: si el docente corrigió a mano ese intento
     *     (`grade_override`), esa es su nota; si no, la automática (`grade`). La corrección
     *     manual existe precisamente para poder desautorizar a la evaluación automática, así que
     *     nunca puede perderla un recálculo.
     *  2. **El método de calificación elige entre esas notas efectivas**, no entre las brutas:
     *     `best` la mayor, `first` la del primer intento, `average` la media, `last` la del
     *     último. El método sigue decidiendo *qué intento cuenta*; el override decide
     *     *cuánto vale ese intento*. Son preguntas distintas y por eso se componen.
     *
     * @param \stdClass $codelab Instancia de la actividad.
     * @param int $userid Usuario.
     * @param float|null $pendinggrade Nota del intento que se está entregando en este mismo
     *        momento y que todavía figura como borrador (`finalize_submission()` aún no ha
     *        corrido). Se cuenta como un intento entregado más, el más reciente. En el recálculo
     *        del libro de notas no hay intento pendiente y se omite.
     * @return float|null Nota final, o null si el usuario no tiene ningún intento entregado.
     */
    public static function compute_final_grade(
        \stdClass $codelab,
        int $userid,
        ?float $pendinggrade = null
    ): ?float {
        global $DB;

        // El orden lo fija esta función, no quien la llama. Ver grader::ORDER_ATTEMPTS_ASC.
        $submissions = $DB->get_records('codelab_submissions', [
            'codelabid' => $codelab->id,
            'userid'    => $userid,
            'status'    => 'submitted',
        ], self::ORDER_ATTEMPTS_ASC);

        $grades = self::effective_grades($submissions);

        if ($pendinggrade !== null) {
            $grades[] = $pendinggrade;
        }

        return self::select_by_method($codelab, $grades);
    }

    /**
     * Igual que compute_final_grade(), pero para muchos usuarios y con UNA sola consulta.
     *
     * Existe para que la vista del docente no haga una consulta por estudiante (INC-107). No
     * recibe filas ya cargadas a propósito: ese sería un contrato implícito —«pásame solo las
     * entregadas de ese usuario, ordenadas por antigüedad»— y un contrato implícito sobre las
     * filas que determinan una NOTA es por donde se cuela un error de calificación silencioso.
     * Aquí la consulta, el filtro y el orden los hace la propia función, exactamente igual que
     * en la versión de un solo usuario, y las dos terminan llamando a select_by_method(), que es
     * la única que sabe interpretar el método de calificación. Si alguien cambia la regla, la
     * cambia en un sitio o no la cambia en ninguno.
     *
     * @param \stdClass $codelab Instancia de la actividad.
     * @param int[] $userids Usuarios a calcular.
     * @return array<int, float|null> userid => nota final (null si no tiene intentos entregados).
     */
    public static function compute_final_grades(\stdClass $codelab, array $userids): array {
        global $DB;

        $resultado = [];
        foreach ($userids as $uid) {
            $resultado[(int)$uid] = null;
        }

        if (empty($userids)) {
            return $resultado;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $inparams['codelabid'] = $codelab->id;

        $submissions = $DB->get_records_select(
            'codelab_submissions',
            "codelabid = :codelabid AND status = 'submitted' AND userid $insql",
            $inparams,
            self::ORDER_ATTEMPTS_ASC
        );

        // Agrupar por usuario conservando el orden que trae la consulta.
        $poruser = [];
        foreach ($submissions as $s) {
            $poruser[(int)$s->userid][] = $s;
        }

        foreach ($poruser as $uid => $filas) {
            $resultado[$uid] = self::select_by_method($codelab, self::effective_grades($filas));
        }

        return $resultado;
    }

    /**
     * Nota efectiva de cada intento: la corrección manual del docente manda sobre la automática.
     *
     * @param array $submissions Entregas, ya filtradas y ordenadas por antigüedad.
     * @return float[]
     */
    private static function effective_grades(array $submissions): array {
        $grades = [];
        foreach ($submissions as $s) {
            $grades[] = $s->grade_override !== null
                ? (float)$s->grade_override
                : (float)($s->grade ?? 0);
        }
        return $grades;
    }

    /**
     * Aplica el método de calificación a una lista de notas efectivas ordenadas por antigüedad.
     *
     * Es pura y no toca la base de datos: es el punto en el que se decide la nota, y la comparten
     * la ruta de un usuario y la de muchos para que no puedan divergir.
     *
     * @param \stdClass $codelab Instancia de la actividad.
     * @param float[] $grades Notas efectivas, de la más antigua a la más reciente.
     * @return float|null
     */
    private static function select_by_method(\stdClass $codelab, array $grades): ?float {
        if (empty($grades)) {
            return null;
        }

        switch ($codelab->grading_method) {
            case 'best':
                return max($grades);

            case 'first':
                return reset($grades);

            case 'average':
                return array_sum($grades) / count($grades);

            default: // 'last'
                return end($grades);
        }
    }

    /**
     * Actualiza el libro de notas según el método de calificación configurado.
     */
    private function update_gradebook(int $userid, float $new_grade): void {
        $grade_to_set = self::compute_final_grade($this->codelab, $userid, $new_grade);

        if ($grade_to_set === null) {
            return;
        }

        $grades = [
            $userid => [
                'userid'   => $userid,
                'rawgrade' => $grade_to_set,
            ],
        ];

        codelab_grade_item_update($this->codelab, $grades);
    }

    /**
     * Mapea los estados del ejecutor a estados legibles.
     */
    private function map_status(string $executor_status, bool $passed): string {
        if ($passed) {
            return 'passed';
        }
        return match($executor_status) {
            'accepted'             => 'failed',
            'wrong_answer'         => 'failed',
            'time_limit_exceeded'  => 'timeout',
            'compile_error'        => 'compile_error',
            'runtime_error'        => 'error',
            default                => 'error',
        };
    }
}
