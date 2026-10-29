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
 * Prueba de INTEGRACIÓN REAL contra un motor Judge0 (opcional, lenta).
 *
 * Solo se ejecuta si hay un Judge0 disponible, indicado por la variable de
 * entorno CODELAB_JUDGE0_URL. En caso contrario, la prueba se omite para no
 * depender de la red en CI. Está en el grupo @large.
 *
 *   CODELAB_JUDGE0_URL=http://localhost:2358 vendor/bin/phpunit \
 *       mod/codelab/tests/executor_live_test.php
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\executor\judge0_executor
 * @group      mod_codelab
 * @group      large
 */

namespace mod_codelab;

use mod_codelab\executor\judge0_executor;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/executor/base_executor.php');
require_once(__DIR__ . '/../classes/executor/judge0_executor.php');

final class executor_live_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $url = getenv('CODELAB_JUDGE0_URL');
        if (empty($url)) {
            $this->markTestSkipped('Define CODELAB_JUDGE0_URL para ejecutar la prueba real contra Judge0.');
        }
        $this->resetAfterTest();
        set_config('judge0_api_url', $url, 'mod_codelab');
        set_config('judge0_api_key', getenv('CODELAB_JUDGE0_KEY') ?: '', 'mod_codelab');
    }

    public function test_python_sum_executes_and_matches(): void {
        $exec = new judge0_executor(10, 128);
        $result = $exec->execute("a,b=map(int,input().split())\nprint(a+b)", 'python', '3 7');

        $this->assertSame('accepted', $result->status, 'La ejecución debe ser aceptada');
        $this->assertTrue($result->matches_expected('10'), 'La salida debe coincidir con 10');
    }

    public function test_infinite_loop_times_out(): void {
        // Verificación de seguridad: un bucle infinito debe cortarse por tiempo.
        $exec = new judge0_executor(3, 128);
        $result = $exec->execute("while True:\n    pass", 'python', '');
        $this->assertSame('time_limit_exceeded', $result->status);
    }
}
