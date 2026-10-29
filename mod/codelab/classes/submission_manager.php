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
 * Gestión de entregas de estudiantes para CodeLab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

class submission_manager {

    /** @var \stdClass Instancia de la actividad */
    private \stdClass $codelab;
    /** @var \context_module Contexto del módulo */
    private \context_module $context;

    public function __construct(\stdClass $codelab, \context_module $context) {
        $this->codelab = $codelab;
        $this->context = $context;
    }

    /**
     * Crea o actualiza la entrega borrador del estudiante.
     */
    public function save_draft(int $userid, string $code, string $language): \stdClass {
        global $DB;

        $existing = $DB->get_record('codelab_submissions', [
            'codelabid' => $this->codelab->id,
            'userid'    => $userid,
            'status'    => 'draft',
        ]);

        if ($existing) {
            $existing->code         = $code;
            $existing->language     = $language;
            $existing->timemodified = time();
            $DB->update_record('codelab_submissions', $existing);
            return $existing;
        }

        $submission = new \stdClass();
        $submission->codelabid      = $this->codelab->id;
        $submission->userid         = $userid;
        $submission->attempt_number = $this->count_attempts($userid) + 1;
        $submission->code           = $code;
        $submission->language       = $language;
        $submission->status         = 'draft';
        $submission->tests_passed   = 0;
        $submission->tests_total    = 0;
        $submission->timecreated    = time();
        $submission->timemodified   = time();
        $submission->id             = $DB->insert_record('codelab_submissions', $submission);

        return $submission;
    }

    /**
     * Marca una entrega como enviada (submitted) y actualiza el contador.
     */
    public function finalize_submission(int $submission_id): void {
        global $DB;

        $DB->set_field('codelab_submissions', 'status', 'submitted', ['id' => $submission_id]);
        $DB->set_field('codelab_submissions', 'timemodified', time(), ['id' => $submission_id]);
    }

    /**
     * Obtiene la mejor entrega de un usuario (mayor nota).
     */
    public function get_best_submission(int $userid): ?\stdClass {
        global $DB;

        $sql = "SELECT *
                  FROM {codelab_submissions}
                 WHERE codelabid = :codelabid
                   AND userid = :userid
                   AND status = 'submitted'
                 ORDER BY grade DESC, timemodified DESC, id DESC
                 LIMIT 1";

        return $DB->get_record_sql($sql, [
            'codelabid' => $this->codelab->id,
            'userid'    => $userid,
        ]) ?: null;
    }

    /**
     * Obtiene la última entrega enviada de un usuario.
     */
    public function get_last_submission(int $userid): ?\stdClass {
        global $DB;

        $sql = "SELECT *
                  FROM {codelab_submissions}
                 WHERE codelabid = :codelabid
                   AND userid = :userid
                   AND status = 'submitted'
                 ORDER BY timemodified DESC, id DESC
                 LIMIT 1";

        return $DB->get_record_sql($sql, [
            'codelabid' => $this->codelab->id,
            'userid'    => $userid,
        ]) ?: null;
    }

    /**
     * Obtiene el borrador activo de un usuario.
     */
    public function get_draft(int $userid): ?\stdClass {
        global $DB;

        return $DB->get_record('codelab_submissions', [
            'codelabid' => $this->codelab->id,
            'userid'    => $userid,
            'status'    => 'draft',
        ]) ?: null;
    }

    /**
     * Cuenta el número de intentos enviados de un usuario.
     */
    public function count_attempts(int $userid): int {
        global $DB;

        return (int)$DB->count_records('codelab_submissions', [
            'codelabid' => $this->codelab->id,
            'userid'    => $userid,
            'status'    => 'submitted',
        ]);
    }

    /**
     * Verifica si el usuario puede enviar según límites configurados.
     */
    public function can_submit(int $userid): bool {
        $max = (int)$this->codelab->max_attempts;
        if ($max === 0) {
            return true;
        }
        return $this->count_attempts($userid) < $max;
    }

    /**
     * Obtiene la última entrega (enviada o borrador) de cada estudiante.
     */
    public function get_all_latest_submissions(): array {
        global $DB;

        // El JOIN anterior emparejaba por MAX(timemodified), así que un estudiante con DOS
        // entregas en el mismo segundo aparecía DUPLICADO en la lista del docente: las dos
        // empataban con el máximo. Este NOT EXISTS pide «la fila de este usuario para la que no
        // existe ninguna más reciente», con el desempate por `id` dentro de la propia condición,
        // y devuelve siempre exactamente una por estudiante. Ver grader::ORDER_ATTEMPTS_ASC.
        $sql = "SELECT s.*
                  FROM {codelab_submissions} s
                 WHERE s.codelabid = :codelabid
                   AND s.status = 'submitted'
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {codelab_submissions} s2
                        WHERE s2.codelabid = s.codelabid
                          AND s2.userid    = s.userid
                          AND s2.status    = 'submitted'
                          AND (s2.timemodified > s.timemodified
                               OR (s2.timemodified = s.timemodified AND s2.id > s.id))
                   )
                 ORDER BY s.timemodified DESC, s.id DESC";

        return $DB->get_records_sql($sql, [
            'codelabid' => $this->codelab->id,
        ]);
    }

    /**
     * Obtiene todas las entregas con sus resultados para una entrega específica.
     */
    public function get_submission_with_results(int $submission_id): ?array {
        global $DB;

        // El $submission_id llega de la URL (submission.php?sid=...) y la capacidad se comprueba
        // sobre el contexto del cmid, no sobre el de la entrega. Sin acotar por codelabid, un
        // docente podía recorrer las entregas de cualquier actividad de toda la instalación.
        $submission = $DB->get_record('codelab_submissions', [
            'id'        => $submission_id,
            'codelabid' => $this->codelab->id,
        ]);
        if (!$submission) {
            return null;
        }

        $results = $DB->get_records_sql(
            "SELECT r.*, tc.name AS testcase_name, tc.expected_output, tc.stdin,
                    tc.is_hidden, tc.points
               FROM {codelab_submission_results} r
               JOIN {codelab_testcases} tc ON tc.id = r.testcase_id
              WHERE r.submission_id = ?
              ORDER BY tc.ordering, tc.id",
            [$submission_id]
        );

        return [
            'submission' => $submission,
            'results'    => array_values($results),
        ];
    }

    /**
     * Nota mínima para aprobar, tal como la configuró el curso.
     *
     * Fuente única del umbral de aprobado. `gradepass` es donde Moodle guarda «nota para
     * aprobar» en el ítem del libro de calificaciones, y lo fija quien configura el curso: una
     * universidad que aprueba con 3.0/5.0 no tiene por qué aprobar con el 60 % que este
     * complemento daba por supuesto.
     *
     * @param \stdClass $codelab Instancia de la actividad.
     * @return float|null Nota de corte, o null si nadie la ha configurado. null significa
     *         «no se sabe», y quien llame debe decirlo en vez de inventar un umbral.
     */
    public static function get_passing_grade(\stdClass $codelab): ?float {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'codelab',
            'iteminstance' => $codelab->id,
            'itemnumber'   => 0,
            'courseid'     => $codelab->course,
        ]);

        return ($gradeitem && (float)$gradeitem->gradepass > 0)
            ? (float)$gradeitem->gradepass
            : null;
    }

    /**
     * Calcula estadísticas globales de la actividad.
     */
    public function get_statistics(): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once(__DIR__ . '/grader.php');

        $codelabid = $this->codelab->id;

        $userids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {codelab_submissions}
              WHERE codelabid = ? AND status = 'submitted'",
            [$codelabid]
        );

        $total_students = count($userids);

        $total_submissions = (int)$DB->count_records('codelab_submissions', [
            'codelabid' => $codelabid,
            'status'    => 'submitted',
        ]);

        // Nota final de cada ESTUDIANTE, con el mismo criterio que la califica de verdad.
        // Antes esto era AVG(grade) sobre todas las entregas (quien entregaba ocho veces pesaba
        // ocho veces en la media) y MAX(grade) por usuario para el aprobado (es decir, «mejor
        // intento» a fuego, aunque la actividad estuviera configurada en «último» o «promedio»).
        // El docente veía un número que no correspondía a las notas de sus estudiantes (INC-102).
        $finalgrades = array_values(array_filter(
            grader::compute_final_grades($this->codelab, $userids),
            static fn($g) => $g !== null
        ));

        $avg_grade = !empty($finalgrades)
            ? array_sum($finalgrades) / count($finalgrades)
            : 0.0;

        $passing_grade = self::get_passing_grade($this->codelab);

        // DECISIÓN EXPLÍCITA: si nadie ha configurado la nota para aprobar, la tasa de aprobación
        // NO se puede calcular, y se devuelve null para que la vista lo diga. La alternativa
        // —inventar un umbral -- es lo que había antes: producía un porcentaje con pinta de dato
        // que nadie podía justificar. Un hueco declarado es preferible a una cifra falsa,
        // sobre todo porque este número acaba citado en el documento de grado.
        if ($passing_grade === null) {
            $passed_students = null;
            $pass_rate       = null;
        } else {
            $passed_students = 0;
            foreach ($finalgrades as $g) {
                if ($g >= $passing_grade) {
                    $passed_students++;
                }
            }
            $pass_rate = $total_students > 0
                ? ($passed_students / $total_students) * 100
                : 0.0;
        }

        // Estadísticas por caso de prueba. Antes eran DOS consultas por caso dentro de un
        // bucle (MDL-07); ahora una sola agrupada. El LEFT JOIN conserva los casos que todavía
        // no tienen ninguna ejecución, que antes salían con total 0 y deben seguir saliendo.
        $testcase_stats = [];
        $rows = $DB->get_records_sql(
            "SELECT tc.id, tc.name, tc.ordering,
                    COUNT(r.id) AS total,
                    SUM(CASE WHEN r.status = 'passed' THEN 1 ELSE 0 END) AS passed
               FROM {codelab_testcases} tc
          LEFT JOIN {codelab_submission_results} r ON r.testcase_id = tc.id
              WHERE tc.codelabid = :codelabid
           GROUP BY tc.id, tc.name, tc.ordering
           ORDER BY tc.ordering, tc.id",
            ['codelabid' => $codelabid]
        );

        foreach ($rows as $row) {
            $total  = (int)$row->total;
            $passed = (int)$row->passed;
            $testcase_stats[] = [
                'name'   => $row->name,
                'passed' => $passed,
                'failed' => $total - $passed,
                'total'  => $total,
            ];
        }

        return [
            'total_students'    => $total_students,
            'total_submissions' => $total_submissions,
            'avg_grade'         => $avg_grade,
            'passing_grade'     => $passing_grade,   // null = sin configurar en el libro de notas.
            'passed_students'   => $passed_students, // null = no calculable.
            'pass_rate'         => $pass_rate,       // null = no calculable.
            'testcase_stats'    => $testcase_stats,
        ];
    }
}
