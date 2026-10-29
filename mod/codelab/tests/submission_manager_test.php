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
 * Pruebas de la gestión de entregas (submission_manager).
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\submission_manager
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/codelab/classes/submission_manager.php');
require_once($CFG->dirroot . '/mod/codelab/classes/grader.php');

final class submission_manager_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $codelab;
    /** @var \context_module */
    private $context;
    /** @var \stdClass */
    private $user;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $this->course->id, 'max_attempts' => 0]);
        $cm = get_coursemodule_from_instance('codelab', $this->codelab->id);
        $this->context = \context_module::instance($cm->id);
        $this->user = $this->getDataGenerator()->create_user();
    }

    private function manager(): submission_manager {
        return new submission_manager($this->codelab, $this->context);
    }

    public function test_save_draft_creates_then_updates(): void {
        global $DB;
        $mgr = $this->manager();

        $draft = $mgr->save_draft($this->user->id, 'print(1)', 'python');
        $this->assertSame('draft', $draft->status);
        $this->assertSame(1, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));

        // Guardar de nuevo actualiza el mismo borrador (no crea otro).
        $draft2 = $mgr->save_draft($this->user->id, 'print(2)', 'python');
        // insert_record() devuelve int y get_record() cadena: se compara el valor, no el tipo.
        $this->assertSame((int)$draft->id, (int)$draft2->id);
        $this->assertSame(1, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
        $this->assertSame('print(2)', $DB->get_field('codelab_submissions', 'code', ['id' => $draft->id]));
    }

    public function test_finalize_submission_marks_submitted(): void {
        global $DB;
        $mgr = $this->manager();
        $draft = $mgr->save_draft($this->user->id, 'x', 'python');

        $mgr->finalize_submission($draft->id);

        $this->assertSame('submitted', $DB->get_field('codelab_submissions', 'status', ['id' => $draft->id]));
    }

    public function test_count_attempts_only_counts_submitted(): void {
        $mgr = $this->manager();

        $d1 = $mgr->save_draft($this->user->id, 'a', 'python');
        $this->assertSame(0, $mgr->count_attempts($this->user->id)); // borrador no cuenta
        $mgr->finalize_submission($d1->id);
        $this->assertSame(1, $mgr->count_attempts($this->user->id));
    }

    public function test_can_submit_respects_max_attempts(): void {
        global $DB;
        // Reconfigurar la actividad con máximo 1 intento.
        $DB->set_field('codelab', 'max_attempts', 1, ['id' => $this->codelab->id]);
        $this->codelab->max_attempts = 1;
        $mgr = $this->manager();

        $this->assertTrue($mgr->can_submit($this->user->id));
        $d = $mgr->save_draft($this->user->id, 'a', 'python');
        $mgr->finalize_submission($d->id);
        $this->assertFalse($mgr->can_submit($this->user->id)); // alcanzó el máximo
    }

    public function test_unlimited_attempts_when_max_zero(): void {
        $mgr = $this->manager();
        for ($i = 0; $i < 5; $i++) {
            $d = $mgr->save_draft($this->user->id, "code$i", 'python');
            $mgr->finalize_submission($d->id);
        }
        $this->assertTrue($mgr->can_submit($this->user->id));
    }

    public function test_get_best_and_last_submission(): void {
        global $DB;
        $mgr = $this->manager();

        // Tres entregas con notas 30, 90, 60 en orden temporal creciente.
        $ids = [];
        foreach ([30, 90, 60] as $i => $g) {
            $s = new \stdClass();
            $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = $i + 1;
            $s->code = "c$i"; $s->language = 'python'; $s->status = 'submitted';
            $s->grade = $g; $s->tests_passed = 0; $s->tests_total = 0;
            $s->timecreated = time() + $i; $s->timemodified = time() + $i;
            $ids[$g] = $DB->insert_record('codelab_submissions', $s);
        }

        $best = $mgr->get_best_submission($this->user->id);
        $this->assertEqualsWithDelta(90.0, (float)$best->grade, 0.001);

        $last = $mgr->get_last_submission($this->user->id);
        $this->assertSame($ids[60], (int)$last->id); // la última por tiempo
    }

    public function test_get_best_submission_desempata_por_id_si_grade_y_timemodified_empatan(): void {
        global $DB;
        $mgr = $this->manager();

        // Mismo grade Y mismo timemodified: sin el desempate por id, el orden dependía del
        // criterio no garantizado del motor de base de datos (el mismo problema que motivó
        // el desempate en grader::compute_final_grade()).
        $empate = time();
        $ids = [];
        foreach ([0, 1] as $i) {
            $s = new \stdClass();
            $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = $i + 1;
            $s->code = "c$i"; $s->language = 'python'; $s->status = 'submitted';
            $s->grade = 80; $s->tests_passed = 0; $s->tests_total = 0;
            $s->timecreated = $empate; $s->timemodified = $empate;
            $ids[] = $DB->insert_record('codelab_submissions', $s);
        }

        $best = $mgr->get_best_submission($this->user->id);
        $this->assertSame(
            max($ids),
            (int) $best->id,
            'Con grade y timemodified empatados, get_best_submission() debe desempatar por id (el mayor)'
        );
    }

    public function test_get_last_submission_desempata_por_id_si_timemodified_empata(): void {
        global $DB;
        $mgr = $this->manager();

        // Mismo timemodified, notas distintas: get_last_submission() no mira la nota, así
        // que el desempate por id es la única forma de obtener un resultado determinista.
        $empate = time();
        $ids = [];
        foreach ([40, 95] as $i => $g) {
            $s = new \stdClass();
            $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = $i + 1;
            $s->code = "c$i"; $s->language = 'python'; $s->status = 'submitted';
            $s->grade = $g; $s->tests_passed = 0; $s->tests_total = 0;
            $s->timecreated = $empate; $s->timemodified = $empate;
            $ids[] = $DB->insert_record('codelab_submissions', $s);
        }

        $last = $mgr->get_last_submission($this->user->id);
        $this->assertSame(
            max($ids),
            (int) $last->id,
            'Con timemodified empatado, get_last_submission() debe desempatar por id (el mayor), sin mirar la nota'
        );
    }

    public function test_get_all_latest_submissions_no_duplica_entregas_con_mismo_timemodified(): void {
        global $DB;
        $mgr = $this->manager();
        $otrousuario = $this->getDataGenerator()->create_user();

        // Control: un segundo estudiante con una sola entrega, para comprobar que la lista
        // sigue trayendo exactamente una fila por estudiante y esta prueba no aprueba por
        // accidente aunque el NOT EXISTS estuviera mal.
        $control = new \stdClass();
        $control->codelabid = $this->codelab->id; $control->userid = $otrousuario->id; $control->attempt_number = 1;
        $control->code = 'c'; $control->language = 'python'; $control->status = 'submitted';
        $control->grade = 50; $control->tests_passed = 0; $control->tests_total = 0;
        $control->timecreated = time(); $control->timemodified = time();
        $DB->insert_record('codelab_submissions', $control);

        // El caso exacto que motivó el cambio: DOS entregas del MISMO estudiante con el
        // MISMO timemodified (el mismo segundo). El JOIN anterior emparejaba por
        // MAX(timemodified): como las dos igualan el máximo, las dos pasaban el filtro y el
        // estudiante aparecía duplicado en la lista del docente, cada fila con una nota
        // distinta. Esto no era un problema de orden: era un dato incorrecto.
        $empate = time();
        $ids = [];
        foreach ([20, 88] as $i => $g) {
            $s = new \stdClass();
            $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = $i + 1;
            $s->code = "c$i"; $s->language = 'python'; $s->status = 'submitted';
            $s->grade = $g; $s->tests_passed = 0; $s->tests_total = 0;
            $s->timecreated = $empate; $s->timemodified = $empate;
            $ids[] = $DB->insert_record('codelab_submissions', $s);
        }

        $latest = $mgr->get_all_latest_submissions();

        $delusuario = array_values(array_filter(
            $latest,
            fn ($s) => (int) $s->userid === (int) $this->user->id
        ));
        $this->assertCount(
            1,
            $delusuario,
            'El estudiante con dos entregas empatadas en timemodified debe aparecer UNA sola vez en la '
            . 'lista del docente, no duplicado'
        );
        $this->assertSame(
            max($ids),
            (int) $delusuario[0]->id,
            'La fila superviviente debe ser la de mayor id, según el desempate del NOT EXISTS'
        );
        $this->assertCount(
            2,
            $latest,
            'Dos estudiantes en total deben producir dos filas: ni una de más (duplicado) ni de menos'
        );
    }

    public function test_get_last_submission_puede_diferir_del_orden_real_de_envio_tras_calificacion_manual(): void {
        global $DB;
        $DB->set_field('codelab', 'grading_method', 'last', ['id' => $this->codelab->id]);
        $this->codelab->grading_method = 'last';
        $mgr = $this->manager();

        // Dos intentos reales, en orden de envío: el 1 antes, el 2 después. Eso es lo único
        // que "última entrega" debería significar desde el punto de vista del estudiante.
        $t0 = time() - 1000;
        $intento1 = new \stdClass();
        $intento1->codelabid = $this->codelab->id; $intento1->userid = $this->user->id; $intento1->attempt_number = 1;
        $intento1->code = 'c1'; $intento1->language = 'python'; $intento1->status = 'submitted';
        $intento1->grade = 40; $intento1->tests_passed = 0; $intento1->tests_total = 0;
        $intento1->timecreated = $t0; $intento1->timemodified = $t0;
        $intento1->id = $DB->insert_record('codelab_submissions', $intento1);

        $intento2 = new \stdClass();
        $intento2->codelabid = $this->codelab->id; $intento2->userid = $this->user->id; $intento2->attempt_number = 2;
        $intento2->code = 'c2'; $intento2->language = 'python'; $intento2->status = 'submitted';
        $intento2->grade = 95; $intento2->tests_passed = 0; $intento2->tests_total = 0;
        $intento2->timecreated = $t0 + 100; $intento2->timemodified = $t0 + 100;
        $intento2->id = $DB->insert_record('codelab_submissions', $intento2);

        // grader::compute_final_grade() ordena por timecreated (inmutable): con
        // grading_method='last' da 95, la nota del intento 2, que es en efecto el último
        // enviado.
        $this->assertSame(
            95.0,
            grader::compute_final_grade($this->codelab, $this->user->id),
            'grader usa timecreated: el último intento REAL es el 2 (95)'
        );

        // Mucho después, un docente corrige el intento 1 a mano. grade.php hace exactamente
        // esto: toma un id concreto por su parámetro sid y le pone timemodified = time(),
        // sin que eso tenga ninguna relación con el orden de los intentos del estudiante.
        $DB->set_field('codelab_submissions', 'timemodified', $t0 + 5000, ['id' => $intento1->id]);

        // get_last_submission() ordena por timemodified: ahora devuelve el intento 1, que ya
        // NO es la última entrega del estudiante, sino la última que TOCÓ el docente.
        $last = $mgr->get_last_submission($this->user->id);
        $this->assertSame(
            $intento1->id,
            (int) $last->id,
            'get_last_submission() usa timemodified: tras calificar a mano el intento 1, lo devuelve a '
            . 'el, aunque el intento 2 sigue siendo el último que el estudiante realmente envió'
        );

        // Misma actividad, la misma pregunta ("¿cuál es la última entrega?"), dos respuestas
        // distintas según a quién se le pregunte: grader dice intento 2 (95),
        // get_last_submission() dice intento 1 (40). Ninguna de las dos funciones está mal
        // por sí sola: el problema es que asumen una noción distinta de "última" bajo el
        // mismo nombre. Mi lectura: timecreated es la respuesta correcta, porque
        // timemodified puede moverlo una acción del docente que no tiene nada que ver con
        // el orden de los intentos (como aquí); timecreated no cambia nunca después de
        // creado el registro.
    }

    public function test_statistics_basic_counts(): void {
        global $DB;
        $mgr = $this->manager();

        $u2 = $this->getDataGenerator()->create_user();
        foreach ([[$this->user->id, 80], [$u2->id, 40]] as [$uid, $g]) {
            $s = new \stdClass();
            $s->codelabid = $this->codelab->id; $s->userid = $uid; $s->attempt_number = 1;
            $s->code = 'x'; $s->language = 'python'; $s->status = 'submitted';
            $s->grade = $g; $s->tests_passed = 0; $s->tests_total = 0;
            $s->timecreated = time(); $s->timemodified = time();
            $DB->insert_record('codelab_submissions', $s);
        }

        // get_statistics() lee el umbral de aprobado del libro de notas (gradepass): ya no lo
        // inventa (INC-102), así que hay que configurarlo para que la tasa sea calculable.
        // Se usa 60 —el mismo valor que antes estaba escondido a fuego en el código— para que
        // el resultado numérico de esta prueba no cambie, pero ahora viene de un ajuste real.
        $this->set_gradepass(60.0);

        $stats = $mgr->get_statistics();
        $this->assertSame(2, $stats['total_students']);
        $this->assertSame(2, $stats['total_submissions']);
        $this->assertEqualsWithDelta(60.0, $stats['avg_grade'], 0.001);
        $this->assertEqualsWithDelta(60.0, $stats['passing_grade'], 0.001);
        // Con gradepass=60, solo el estudiante con 80 aprueba: 1 de 2 → 50%.
        $this->assertEqualsWithDelta(50.0, $stats['pass_rate'], 0.001);
    }

    public function test_statistics_pass_rate_es_null_sin_gradepass_configurado(): void {
        global $DB;
        $mgr = $this->manager();

        $s = new \stdClass();
        $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = 1;
        $s->code = 'x'; $s->language = 'python'; $s->status = 'submitted';
        $s->grade = 80; $s->tests_passed = 0; $s->tests_total = 0;
        $s->timecreated = time(); $s->timemodified = time();
        $DB->insert_record('codelab_submissions', $s);

        // Nadie configuró un umbral de aprobado en el libro de notas: get_statistics() no debe
        // inventar uno. Antes de INC-102, un 60% escondido en el código llenaba este hueco con
        // un número que no correspondía a ningún ajuste real. Esta prueba es la que impide que
        // alguien reintroduzca un porcentaje por defecto "para que no salga vacío": la prueba
        // anterior (test_statistics_basic_counts) por sí sola no lo detectaría, porque configura
        // gradepass explícitamente.
        $stats = $mgr->get_statistics();
        $this->assertNull($stats['passing_grade']);
        $this->assertNull($stats['passed_students']);
        $this->assertNull($stats['pass_rate']);
    }

    public function test_statistics_avg_usa_grading_method_last_no_el_mejor_intento(): void {
        global $DB;
        $DB->set_field('codelab', 'grading_method', 'last', ['id' => $this->codelab->id]);
        $this->codelab->grading_method = 'last';
        $mgr = $this->manager();

        // Tres entregas del mismo estudiante: 30, 90, 60, en ese orden temporal. Con
        // grading_method='last' la nota efectiva del estudiante es 60 (el ÚLTIMO intento),
        // no 90 (el mejor). Antes de INC-102, get_statistics() calculaba "mejor intento" a
        // fuego (MAX por usuario) sin mirar el método configurado en la actividad: con este
        // mismo escenario habría dado 90.0 en vez de 60.0.
        foreach ([30, 90, 60] as $i => $g) {
            $s = new \stdClass();
            $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = $i + 1;
            $s->code = "c$i"; $s->language = 'python'; $s->status = 'submitted';
            $s->grade = $g; $s->tests_passed = 0; $s->tests_total = 0;
            $s->timecreated = time() + $i; $s->timemodified = time() + $i;
            $DB->insert_record('codelab_submissions', $s);
        }

        $this->set_gradepass(50.0);

        $stats = $mgr->get_statistics();
        $this->assertSame(1, $stats['total_students']);
        $this->assertEqualsWithDelta(
            60.0,
            $stats['avg_grade'],
            0.001,
            'Con grading_method=last la media debe usar el último intento (60), no el mejor (90)'
        );
        $this->assertSame(1, $stats['passed_students']); // 60 >= 50
        $this->assertEqualsWithDelta(100.0, $stats['pass_rate'], 0.001);
    }

    public function test_statistics_avg_conserva_el_grade_override_del_docente(): void {
        global $DB;
        $mgr = $this->manager();

        // Nota automática 40 (reprobado), corregida a mano por el docente a 95 (aprobado).
        // get_statistics() debe reflejar la nota EFECTIVA (con el override), no la bruta: usa
        // la misma fuente de verdad (grader::compute_final_grade()) que califica de verdad al
        // estudiante, así que no puede mostrarle al docente un panel que contradiga la nota
        // real que ese estudiante tiene en el libro de calificaciones.
        $s = new \stdClass();
        $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = 1;
        $s->code = 'x'; $s->language = 'python'; $s->status = 'submitted';
        $s->grade = 40; $s->grade_override = 95; $s->tests_passed = 0; $s->tests_total = 0;
        $s->timecreated = time(); $s->timemodified = time();
        $DB->insert_record('codelab_submissions', $s);

        $this->set_gradepass(50.0);

        $stats = $mgr->get_statistics();
        $this->assertEqualsWithDelta(
            95.0,
            $stats['avg_grade'],
            0.001,
            'Sin el override, la media habría usado 40 (reprobado) en vez de 95 (aprobado)'
        );
        $this->assertSame(1, $stats['passed_students']);
        $this->assertEqualsWithDelta(100.0, $stats['pass_rate'], 0.001);
    }

    public function test_statistics_testcase_sin_ejecuciones_aparece_con_total_cero(): void {
        global $DB;
        $mgr = $this->manager();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_codelab');

        // Dos casos de prueba: uno con una ejecución registrada, otro sin ninguna. El segundo
        // es el que un INNER JOIN habría hecho desaparecer en silencio del panel del docente
        // (la pantalla seguiría pareciendo correcta, solo que sin esa fila).
        $tc_con_datos = $gen->create_testcase(['codelabid' => $this->codelab->id, 'name' => 'Con datos', 'ordering' => 0]);
        $tc_sin_datos = $gen->create_testcase(['codelabid' => $this->codelab->id, 'name' => 'Sin datos', 'ordering' => 1]);

        $s = new \stdClass();
        $s->codelabid = $this->codelab->id; $s->userid = $this->user->id; $s->attempt_number = 1;
        $s->code = 'x'; $s->language = 'python'; $s->status = 'submitted';
        $s->grade = 100; $s->tests_passed = 1; $s->tests_total = 1;
        $s->timecreated = time(); $s->timemodified = time();
        $s->id = $DB->insert_record('codelab_submissions', $s);

        $r = new \stdClass();
        $r->submission_id = $s->id;
        $r->testcase_id   = $tc_con_datos->id;
        $r->status        = 'passed';
        $r->timecreated   = time();
        $DB->insert_record('codelab_submission_results', $r);

        $stats = $mgr->get_statistics();

        $porcaso = [];
        foreach ($stats['testcase_stats'] as $row) {
            $porcaso[$row['name']] = $row;
        }

        $this->assertArrayHasKey(
            'Sin datos',
            $porcaso,
            'Un caso de prueba sin ejecuciones debe seguir apareciendo: LEFT JOIN, no INNER JOIN'
        );
        $this->assertSame(0, $porcaso['Sin datos']['total']);
        $this->assertSame(0, $porcaso['Sin datos']['passed']);
        $this->assertSame(0, $porcaso['Sin datos']['failed']);

        $this->assertSame(1, $porcaso['Con datos']['total']);
        $this->assertSame(1, $porcaso['Con datos']['passed']);
        $this->assertSame(0, $porcaso['Con datos']['failed']);
    }

    /**
     * Configura el gradepass del ítem de calificación de la actividad, como haría un docente
     * desde Calificaciones → Configuración de calificaciones → editar el ítem.
     */
    private function set_gradepass(float $value): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'codelab',
            'iteminstance' => $this->codelab->id,
            'itemnumber'   => 0,
            'courseid'     => $this->course->id,
        ]);
        $this->assertNotNull($gradeitem, 'El ítem de calificación debe existir tras crear la actividad');
        $gradeitem->gradepass = $value;
        $gradeitem->update();
    }
}
