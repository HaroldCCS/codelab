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
 * Pruebas del motor de ejecución (parte que NO requiere red).
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\executor\base_executor
 * @covers     \mod_codelab\executor\judge0_executor
 */

namespace mod_codelab;

use mod_codelab\executor\mock_executor;
use mod_codelab\executor\judge0_executor;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/executor/base_executor.php');
require_once(__DIR__ . '/../classes/executor/judge0_executor.php');
require_once(__DIR__ . '/fixtures/mock_executor.php');

final class executor_test extends \advanced_testcase {

    public function test_supported_languages(): void {
        $exec = new mock_executor();
        foreach (['python', 'javascript', 'java', 'c', 'cpp', 'php', 'csharp', 'ruby', 'go', 'kotlin'] as $lang) {
            $this->assertTrue($exec->is_language_supported($lang), "Debe soportar $lang");
        }
    }

    public function test_unsupported_language(): void {
        $exec = new mock_executor();
        $this->assertFalse($exec->is_language_supported('cobol'));
        $this->assertFalse($exec->is_language_supported(''));
    }

    public function test_resource_limits_are_stored(): void {
        // Los límites de tiempo/memoria deben viajar al ejecutor, que luego los
        // aplica al sandbox. Verificamos que se conservan tras la construcción.
        $exec = new mock_executor(10, 256);
        $this->assertSame(10, $exec->configured_time_limit);
        $this->assertSame(256, $exec->configured_memory_limit);
    }

    public function test_language_id_mapping(): void {
        // Subclase anónima para exponer el método protegido get_language_id().
        $probe = new class extends \mod_codelab\executor\base_executor {
            public function execute(string $code, string $language, string $stdin = ''): \mod_codelab\executor\execution_result {
                return new \mod_codelab\executor\execution_result('accepted');
            }
            public function id_for(string $lang): int {
                return $this->get_language_id($lang);
            }
        };
        $this->assertSame(71, $probe->id_for('python'));
        $this->assertSame(63, $probe->id_for('javascript'));
        $this->assertSame(62, $probe->id_for('java'));
        // Lenguaje desconocido cae en Python (71) como valor por defecto.
        $this->assertSame(71, $probe->id_for('desconocido'));
    }

    public function test_execute_sin_judge0_configurado_devuelve_error_interno_sin_red(): void {
        $this->resetAfterTest();

        // Judge0 sin configurar: el mismo estado con el que arranca un Moodle recién
        // instalado, antes de que un administrador entre a los ajustes de CodeLab.
        set_config('judge0_api_url', '', 'mod_codelab');
        set_config('judge0_api_key', '', 'mod_codelab');

        $exec = new judge0_executor(5, 128);

        $inicio = microtime(true);
        $result = $exec->execute('print(1)', 'python', '');
        $duracion = microtime(true) - $inicio;

        // SEC-02 / INC-58: antes de la corrección, un ajuste vacío caía por "?:" a
        // https://judge0-ce.p.rapidapi.com y el código del estudiante salía hacia un
        // servicio gestionado de terceros. judge0_executor llama a curl_init()
        // directamente (no hay un doble de HTTP inyectable), así que aquí se comprueba
        // con señales indirectas en vez de interceptar la petición:

        // 1) El mensaje es EXACTAMENTE el de "no configurado", no el de "error de
        //    conexión" ni el de "tiempo agotado". Si el guardia de execute() se quita o
        //    se sustituye por un valor por defecto no vacío, el código intenta una
        //    petición real y cae en una de esas otras ramas, con otro mensaje.
        $this->assertSame('internal_error', $result->status);
        $this->assertSame(
            get_string('executor_not_configured', 'mod_codelab'),
            $result->message,
            'El mensaje debe ser exactamente "no configurado"; si cambia, sospecha que '
            . 'execute() dejó de cortar antes de la red (SEC-02 / INC-58)'
        );

        // 2) No llegó ningún dato de una respuesta real: ni salida, ni compilación, ni
        //    tiempo, ni memoria. Una llamada real a Judge0 -aunque fallara- dejaría
        //    rastro en alguno de estos campos.
        $this->assertSame('', $result->stdout);
        $this->assertSame('', $result->stderr);
        $this->assertNull($result->compile_output);
        $this->assertNull($result->time);
        $this->assertNull($result->memory);

        // 3) No hubo ninguna petición HTTP: una llamada real, incluso si fallara,
        //    tardaría por los timeouts de curl (15-30s) o la espera de sondeo
        //    (800ms); cortar por "no configurado" es una comparación de cadena y
        //    vuelve en milisegundos.
        $this->assertLessThan(
            1.0,
            $duracion,
            'execute() no debería tardar nada si corta antes de la red; si esto falla, '
            . 'sospecha que se está intentando una petición HTTP real'
        );
    }
}
