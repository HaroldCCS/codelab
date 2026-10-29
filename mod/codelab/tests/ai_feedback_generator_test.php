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
 * Pruebas del generador de recomendaciones por IA.
 *
 * Usa un proveedor simulado (sin red). Verifica el armado del prompt, el aislamiento
 * de fallos y —lo más importante— que la nota del estudiante NUNCA cambia por la IA.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\ai\feedback_generator
 */

namespace mod_codelab;

use mod_codelab\ai\feedback_generator;
use mod_codelab\ai\mock_ai_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/codelab/tests/fixtures/mock_ai_provider.php');

final class ai_feedback_generator_test extends \advanced_testcase {

    // ── is_enabled_for (unitario, sin BD) ──────────────────────────────────

    public function test_is_enabled_for_false_when_flag_off(): void {
        $codelab = (object)['ai_feedback_enabled' => 0, 'ai_criteria' => '["readability"]'];
        $this->assertFalse(feedback_generator::is_enabled_for($codelab));
    }

    public function test_is_enabled_for_false_when_no_criteria(): void {
        $codelab = (object)['ai_feedback_enabled' => 1, 'ai_criteria' => '[]'];
        $this->assertFalse(feedback_generator::is_enabled_for($codelab));
    }

    public function test_is_enabled_for_false_when_criteria_null(): void {
        $codelab = (object)['ai_feedback_enabled' => 1, 'ai_criteria' => null];
        $this->assertFalse(feedback_generator::is_enabled_for($codelab));
    }

    public function test_is_enabled_for_true_when_enabled_with_criteria(): void {
        $codelab = (object)['ai_feedback_enabled' => 1, 'ai_criteria' => '["readability","efficiency"]'];
        $this->assertTrue(feedback_generator::is_enabled_for($codelab));
    }

    // ── Armado del prompt (unitario, proveedor simulado) ───────────────────

    public function test_prompt_contains_context_and_returns_provider_text(): void {
        $mock = new mock_ai_provider('★ Recomendación generada ★');
        $generator = new feedback_generator($mock);

        $codelab = (object)[
            'intro'       => 'Escribe una función que sume dos números',
            'ai_criteria' => json_encode(['readability', 'efficiency']),
        ];
        $code = "def suma(a, b):\n    return a + b";

        $result = $generator->generate($codelab, $code, 'python', [
            'tests_passed' => 2,
            'tests_total'  => 3,
        ]);

        // Devuelve exactamente lo del proveedor.
        $this->assertSame('★ Recomendación generada ★', $result);

        // El prompt enviado contiene el contexto esperado.
        $this->assertCount(1, $mock->prompts);
        $prompt = $mock->prompts[0];
        $this->assertStringContainsString('sume dos números', $prompt);
        $this->assertStringContainsString('python', $prompt);
        $this->assertStringContainsString('2 de 3', $prompt);
        $this->assertStringContainsString('def suma', $prompt);
        // Los criterios activos guían el prompt.
        $this->assertStringContainsStringIgnoringCase('Legibilidad', $prompt);
        $this->assertStringContainsStringIgnoringCase('Eficiencia', $prompt);
        // Instrucción dura: la IA no debe dar nota.
        $this->assertStringContainsString('NO asignes', $prompt);
    }

    public function test_prompt_truncates_very_long_code(): void {
        $mock = new mock_ai_provider('ok');
        $generator = new feedback_generator($mock);

        $codelab = (object)['intro' => 'x', 'ai_criteria' => json_encode(['readability'])];
        $longcode = str_repeat('a', 13000);

        $generator->generate($codelab, $longcode, 'python', ['tests_passed' => 0, 'tests_total' => 1]);

        $this->assertStringContainsString('código truncado', $mock->prompts[0]);
    }

    // ── run_for_submission + persistencia (con BD) ─────────────────────────

    public function test_run_for_submission_stores_feedback_and_keeps_grade(): void {
        global $DB;
        $this->resetAfterTest();

        [$codelab, $submission] = $this->create_activity_and_submission(77.0);

        $generator = new feedback_generator(new mock_ai_provider('Buen uso de nombres claros.'));

        $text = feedback_generator::run_for_submission(
            $codelab, $submission->id, 'codigo', 'python',
            ['tests_passed' => 1, 'tests_total' => 1], $generator
        );

        $this->assertSame('Buen uso de nombres claros.', $text);

        $updated = $DB->get_record('codelab_submissions', ['id' => $submission->id]);
        $this->assertSame('Buen uso de nombres claros.', $updated->ai_feedback);
        $this->assertSame('ok', $updated->ai_feedback_status);
        $this->assertGreaterThan(0, (int)$updated->ai_feedback_time);
        // La nota NO cambió.
        $this->assertEqualsWithDelta(77.0, (float)$updated->grade, 0.001);
    }

    public function test_run_for_submission_failure_keeps_grade_and_marks_error(): void {
        global $DB;
        $this->resetAfterTest();

        [$codelab, $submission] = $this->create_activity_and_submission(77.0);

        // Proveedor que falla (simula caída del servicio de IA).
        $generator = new feedback_generator(new mock_ai_provider('', true));

        $text = feedback_generator::run_for_submission(
            $codelab, $submission->id, 'codigo', 'python',
            ['tests_passed' => 1, 'tests_total' => 1], $generator
        );

        // Falla de forma segura: null, sin excepción hacia arriba, y deja rastro para el
        // administrador (debugging) en vez de callarlo.
        $this->assertNull($text);
        $this->assertDebuggingCalled();

        $updated = $DB->get_record('codelab_submissions', ['id' => $submission->id]);
        $this->assertSame('error', $updated->ai_feedback_status);
        $this->assertEmpty($updated->ai_feedback);
        // Lo crítico: la nota sigue intacta pese al fallo de la IA.
        $this->assertEqualsWithDelta(77.0, (float)$updated->grade, 0.001);
    }

    public function test_run_for_submission_marks_disabled_when_not_configured(): void {
        global $DB;
        $this->resetAfterTest();

        // Sin configurar la IA a nivel de sitio: create() devolverá null.
        set_config('ai_enabled', 0, 'mod_codelab');

        [$codelab, $submission] = $this->create_activity_and_submission(50.0);

        $text = feedback_generator::run_for_submission(
            $codelab, $submission->id, 'codigo', 'python',
            ['tests_passed' => 1, 'tests_total' => 1] // sin inyectar generador
        );

        $this->assertNull($text);

        $updated = $DB->get_record('codelab_submissions', ['id' => $submission->id]);
        $this->assertSame('disabled', $updated->ai_feedback_status);
        $this->assertEqualsWithDelta(50.0, (float)$updated->grade, 0.001);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Crea una actividad con IA activada y una entrega con nota fijada.
     *
     * @return array{0:\stdClass,1:\stdClass} [codelab, submission]
     */
    private function create_activity_and_submission(float $grade): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $codelab = $this->getDataGenerator()->get_plugin_generator('mod_codelab')->create_instance([
            'course'              => $course->id,
            'ai_feedback_enabled' => 1,
            'ai_criteria'         => json_encode(['readability']),
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $s = new \stdClass();
        $s->codelabid      = $codelab->id;
        $s->userid         = $user->id;
        $s->attempt_number = 1;
        $s->code           = 'print(1)';
        $s->language       = 'python';
        $s->status         = 'submitted';
        $s->grade          = $grade;
        $s->tests_passed   = 1;
        $s->tests_total    = 1;
        $s->timecreated    = time();
        $s->timemodified   = time();
        $s->id = $DB->insert_record('codelab_submissions', $s);

        return [$codelab, $s];
    }
}
