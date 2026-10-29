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
 * Interfaz base para motores de ejecución de código.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\executor;

defined('MOODLE_INTERNAL') || die();

/**
 * Resultado de una ejecución de código.
 */
class execution_result {
    /** @var string Estado: accepted|wrong_answer|time_limit_exceeded|runtime_error|compile_error|internal_error */
    public string $status;
    /** @var string Salida estándar del programa */
    public string $stdout;
    /** @var string Salida de error estándar */
    public string $stderr;
    /** @var string|null Salida del compilador */
    public ?string $compile_output;
    /** @var float|null Tiempo de ejecución en segundos */
    public ?float $time;
    /** @var int|null Memoria usada en KB */
    public ?int $memory;
    /** @var string Mensaje de error legible (si aplica) */
    public string $message;

    public function __construct(
        string $status,
        string $stdout = '',
        string $stderr = '',
        ?string $compile_output = null,
        ?float $time = null,
        ?int $memory = null,
        string $message = ''
    ) {
        $this->status         = $status;
        $this->stdout         = $stdout;
        $this->stderr         = $stderr;
        $this->compile_output = $compile_output;
        $this->time           = $time;
        $this->memory         = $memory;
        $this->message        = $message;
    }

    /**
     * Compara la salida con la esperada (normaliza saltos de línea y espacios).
     */
    public function matches_expected(string $expected): bool {
        $actual   = $this->normalize_output($this->stdout);
        $expected = $this->normalize_output($expected);
        return $actual === $expected;
    }

    /**
     * Normaliza la salida: trim y normalización de fin de línea.
     */
    private function normalize_output(string $output): string {
        $output = str_replace("\r\n", "\n", $output);
        return trim($output);
    }

    /**
     * Convierte el resultado a array para JSON.
     */
    public function to_array(): array {
        return [
            'status'         => $this->status,
            'stdout'         => $this->stdout,
            'stderr'         => $this->stderr,
            'compile_output' => $this->compile_output,
            'time'           => $this->time,
            'memory'         => $this->memory,
            'message'        => $this->message,
        ];
    }
}

/**
 * Interfaz abstracta para ejecutores de código.
 */
abstract class base_executor {

    /** @var int Tiempo límite en segundos */
    protected int $time_limit;
    /** @var int Límite de memoria en MB */
    protected int $memory_limit;

    /**
     * Mapeo de nombre de lenguaje a ID de Judge0.
     * https://ce.judge0.com/languages/
     */
    protected const JUDGE0_LANGUAGE_IDS = [
        'python'     => 71,   // Python 3.8.1
        'javascript' => 63,   // JavaScript (Node.js 12.14.0)
        'java'       => 62,   // Java (OpenJDK 13.0.1)
        'c'          => 50,   // C (GCC 9.2.0)
        'cpp'        => 54,   // C++ (GCC 9.2.0)
        'php'        => 68,   // PHP (7.4.1)
        'csharp'     => 51,   // C# (Mono 6.6.0.161)
        'ruby'       => 72,   // Ruby (2.7.0)
        'go'         => 60,   // Go (1.13.5)
        'kotlin'     => 78,   // Kotlin (1.3.70)
    ];

    public function __construct(int $time_limit = 5, int $memory_limit = 128) {
        $this->time_limit   = $time_limit;
        $this->memory_limit = $memory_limit;
    }

    /**
     * Ejecuta código contra una entrada estándar.
     *
     * @param string $code       Código fuente
     * @param string $language   Nombre del lenguaje
     * @param string $stdin      Entrada estándar
     * @return execution_result
     */
    abstract public function execute(string $code, string $language, string $stdin = ''): execution_result;

    /**
     * Obtiene el ID de Judge0 para un lenguaje.
     */
    protected function get_language_id(string $language): int {
        return self::JUDGE0_LANGUAGE_IDS[$language] ?? 71; // Python como fallback.
    }

    /**
     * Verifica si el lenguaje es soportado.
     */
    public function is_language_supported(string $language): bool {
        return array_key_exists($language, self::JUDGE0_LANGUAGE_IDS);
    }
}
