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
 * Pruebas de \mod_codelab\execution_request: la lógica del endpoint de ejecución.
 *
 * Antes de INC-98, `execute.php` leía su entrada directamente de `php://input`, que bajo la
 * CLI de PHPUnit siempre está vacío: la comprobación de sesskey cortaba con exit() antes de
 * llegar a ningún camino interesante, y nada de esto era probable. Tras el refactor de
 * `dev-agent` (execute.php pasó a ser solo la envoltura HTTP; la lógica vive aquí, con la
 * entrada inyectable por constructor), estas siete comprobaciones ya se pueden ejercitar
 * directamente, sin tocar la red ni el script.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\execution_request
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/codelab/classes/execution_request.php');
require_once($CFG->dirroot . '/mod/codelab/classes/execution_exception.php');
require_once($CFG->dirroot . '/mod/codelab/tests/fixtures/mock_executor.php');

use mod_codelab\executor\execution_result;
use mod_codelab\executor\mock_executor;

final class execution_request_test extends \advanced_testcase {

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
    /** @var mock_executor Motor simulado: ninguna prueba depende de que haya un Judge0 escuchando. */
    private $motor;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')
            ->create_instance(['course' => $this->course->id, 'grade' => 100]);
        $this->cm      = get_coursemodule_from_instance('codelab', $this->codelab->id);
        $this->context = \context_module::instance($this->cm->id);

        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
        $this->setUser($this->student);

        // Todas las ejecuciones responden «aceptado, 10», que es lo que espera create_testcase().
        $this->motor = new mock_executor();
        $this->motor->set_default(new execution_result('accepted', '10'));
    }

    /** Construye la petición con el motor simulado, como haría execute.php con el real. */
    private function peticion(array $input): execution_request {
        return new execution_request($input, $this->motor);
    }

    private function create_testcase(): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_codelab')->create_testcase([
            'codelabid'       => $this->codelab->id,
            'expected_output' => '10',
            'points'          => 100,
        ]);
    }

    /**
     * Petición mínima válida (login/capacidad/sesskey/lenguaje/tamaño correctos), que las
     * pruebas individuales alteran para forzar la comprobación que quieren fijar.
     */
    private function peticion_valida(array $overrides = []): array {
        return array_merge([
            'cmid'     => $this->cm->id,
            'code'     => "print('10')",
            'language' => 'python',
            'action'   => 'run',
            'sesskey'  => sesskey(),
        ], $overrides);
    }

    public function test_sin_capacidad_execute_lanza_required_capability_exception(): void {
        global $DB;

        // Por defecto (db/access.php) mod/codelab:execute SÍ lo tiene un estudiante: hay que
        // prohibírselo explícitamente para reproducir el caso de un usuario sin permiso.
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        assign_capability('mod/codelab:execute', CAP_PROHIBIT, $studentrole->id, $this->context->id, true);
        \accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\required_capability_exception::class);
        ($this->peticion($this->peticion_valida()))->handle();
    }

    public function test_sesskey_invalida_lanza_execution_exception_invalidsesskey(): void {
        try {
            ($this->peticion($this->peticion_valida(['sesskey' => 'sesskey-falsa'])))->handle();
            $this->fail('Se esperaba execution_exception por sesskey inválida');
        } catch (execution_exception $e) {
            $this->assertSame('invalidsesskey', $e->errorcode);
        }
    }

    public function test_lenguaje_no_permitido_lanza_execution_exception_language_not_allowed(): void {
        // El generador deja languages_allowed = ["python","javascript"] por defecto; ruby no está.
        try {
            ($this->peticion($this->peticion_valida(['language' => 'ruby'])))->handle();
            $this->fail('Se esperaba execution_exception por lenguaje no permitido');
        } catch (execution_exception $e) {
            $this->assertSame('language_not_allowed', $e->errorcode);
        }
    }

    public function test_codigo_mayor_a_64kb_lanza_execution_exception_code_too_large(): void {
        // RNF-14: 64 KB es el límite. Un byte más debe rechazarse.
        $codigo_enorme = str_repeat('a', execution_request::MAX_CODE_BYTES + 1);

        try {
            ($this->peticion($this->peticion_valida(['code' => $codigo_enorme])))->handle();
            $this->fail('Se esperaba execution_exception por código demasiado grande');
        } catch (execution_exception $e) {
            $this->assertSame('code_too_large', $e->errorcode);
        }
    }

    public function test_sin_casos_de_prueba_lanza_execution_exception_no_testcases(): void {
        // No se crea ningún caso de prueba para esta actividad: debe rechazarse antes de
        // intentar calificar nada.
        try {
            ($this->peticion($this->peticion_valida()))->handle();
            $this->fail('Se esperaba execution_exception por ausencia de casos de prueba');
        } catch (execution_exception $e) {
            $this->assertSame('no_testcases', $e->errorcode);
        }
    }

    public function test_maximo_de_intentos_alcanzado_lanza_execution_exception_max_attempts_reached(): void {
        global $DB;

        $this->create_testcase();
        $DB->set_field('codelab', 'max_attempts', 1, ['id' => $this->codelab->id]);

        // El estudiante ya agotó su único intento permitido.
        $s = new \stdClass();
        $s->codelabid = $this->codelab->id;
        $s->userid = $this->student->id;
        $s->attempt_number = 1;
        $s->code = "print('10')";
        $s->language = 'python';
        $s->status = 'submitted';
        $s->grade = 100;
        $s->tests_passed = 1;
        $s->tests_total = 1;
        $s->timecreated = time();
        $s->timemodified = time();
        $DB->insert_record('codelab_submissions', $s);

        try {
            ($this->peticion($this->peticion_valida(['action' => 'submit'])))->handle();
            $this->fail('Se esperaba execution_exception por máximo de intentos alcanzado');
        } catch (execution_exception $e) {
            $this->assertSame('max_attempts_reached', $e->errorcode);
            $this->assertCount(0, $this->motor->calls, 'Una entrega rechazada no debe llegar al motor');
        }

        // No debe haberse creado una segunda entrega.
        $this->assertSame(1, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
    }

    public function test_fecha_de_corte_pasada_lanza_execution_exception_past_cutoff(): void {
        global $DB;

        $this->create_testcase();
        $DB->set_field('codelab', 'cutoffdate', time() - 3600, ['id' => $this->codelab->id]);

        try {
            ($this->peticion($this->peticion_valida(['action' => 'submit'])))->handle();
            $this->fail('Se esperaba execution_exception por fecha de corte pasada');
        } catch (execution_exception $e) {
            $this->assertSame('past_cutoff', $e->errorcode);
            $this->assertCount(0, $this->motor->calls, 'Una entrega rechazada no debe llegar al motor');
        }

        // No debe haberse guardado ninguna entrega tras la fecha de corte.
        $this->assertSame(0, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
    }

    public function test_fecha_de_permitir_desde_en_el_futuro_lanza_before_allow_from_al_entregar(): void {
        global $DB;

        // El ajuste «Permitir entregas desde» se guardaba y nadie lo leía: el docente creía
        // que la actividad estaba cerrada hasta esa fecha y el estudiante entregaba igual.
        // Esta es la mitad «bloqueada» de la comprobación: con la fecha en el futuro, debe
        // cortar. La otra mitad (permitir con la fecha en el pasado o en 0) va en pruebas
        // aparte: una prueba que solo mirara este lado pasaría igual con el signo de la
        // comparación invertido.
        $this->create_testcase();
        $fecha = time() + 3600;
        $DB->set_field('codelab', 'allow_submissions_from_date', $fecha, ['id' => $this->codelab->id]);

        try {
            ($this->peticion($this->peticion_valida(['action' => 'submit'])))->handle();
            $this->fail('Se esperaba execution_exception por fecha de apertura futura');
        } catch (execution_exception $e) {
            $this->assertSame('before_allow_from', $e->errorcode);
            $this->assertCount(0, $this->motor->calls, 'Una entrega rechazada no debe llegar al motor');

            // El {$a} de la cadena lleva la fecha. dev-agent detectó en su primer intento que,
            // sin pasar el cuarto argumento de moodle_exception, el estudiante habría visto el
            // marcador «{$a}» literal en vez de una fecha. Se fija aquí para que no se repita.
            $mensaje = $e->getMessage();
            $this->assertStringContainsString(
                userdate($fecha),
                $mensaje,
                'El mensaje debe llevar la fecha real, no el marcador sin resolver'
            );
            $this->assertStringNotContainsString('{$a}', $mensaje);
        }

        // No debe haberse guardado ninguna entrega antes de que abra el plazo.
        $this->assertSame(0, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
    }

    public function test_fecha_de_permitir_desde_en_el_pasado_permite_entregar(): void {
        global $DB;

        // La otra mitad de la comprobación: con la fecha de apertura ya pasada, entregar debe
        // funcionar con normalidad. Sin esta prueba, un error de signo que bloqueara SIEMPRE
        // (en vez de solo antes de la fecha) habría pasado la prueba anterior igual de bien.
        $this->create_testcase();
        $DB->set_field('codelab', 'allow_submissions_from_date', time() - 3600, ['id' => $this->codelab->id]);

        $resultado = ($this->peticion($this->peticion_valida(['action' => 'submit'])))->handle();

        $this->assertTrue($resultado['submitted']);
        $this->assertSame(1, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
    }

    public function test_fecha_de_permitir_desde_en_cero_permite_entregar(): void {
        global $DB;

        // 0 significa «sin restricción» (el mismo convenio que ya usa cutoffdate): la
        // condición de producción es `> 0 && time() < fecha`, así que en 0 el primer término
        // ya es falso y el segundo ni se evalúa. Es el valor por defecto del generador, pero
        // se fija aquí explícitamente para que la intención quede escrita.
        $this->create_testcase();
        $DB->set_field('codelab', 'allow_submissions_from_date', 0, ['id' => $this->codelab->id]);

        $resultado = ($this->peticion($this->peticion_valida(['action' => 'submit'])))->handle();

        $this->assertTrue($resultado['submitted']);
        $this->assertSame(1, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
    }

    public function test_fecha_de_permitir_desde_en_el_futuro_no_bloquea_action_run(): void {
        global $DB;

        // Decisión de producto de dev-agent, no un descuido: «Ejecutar y probar» no registra
        // nada y solo corre el código contra los casos visibles, así que impedirlo antes de
        // la fecha de apertura sería más restrictivo de lo que el ajuste promete («Permitir
        // ENTREGAS desde»). La comprobación en execution_request.php vive dentro del bloque
        // `if ($action === 'submit')`, así que action=run nunca debería llegar a ella; esta
        // prueba deja escrito ese criterio para que no se revierta sin querer.
        $this->create_testcase();
        $DB->set_field('codelab', 'allow_submissions_from_date', time() + 3600, ['id' => $this->codelab->id]);

        $resultado = ($this->peticion($this->peticion_valida(['action' => 'run'])))->handle();

        $this->assertTrue($resultado['success']);
        $this->assertFalse($resultado['submitted']);
        $this->assertSame(0, $DB->count_records('codelab_submissions', ['codelabid' => $this->codelab->id]));
    }
}
