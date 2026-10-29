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
 * Ejecutor simulado (test double) para las pruebas.
 *
 * Permite programar la respuesta que "devolvería" el motor de ejecución sin
 * hacer ninguna llamada de red a Judge0. Así las pruebas del grader y del flujo
 * de entrega son deterministas y rápidas.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\executor;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../classes/executor/base_executor.php');

/**
 * Motor simulado que devuelve resultados predefinidos.
 */
class mock_executor extends base_executor {

    /** @var execution_result[] Cola de resultados a devolver, en orden. */
    private array $queue = [];

    /** @var execution_result|null Resultado por defecto si la cola está vacía. */
    private ?execution_result $default = null;

    /** @var array<int, array{code:string, language:string, stdin:string}> Registro de llamadas. */
    public array $calls = [];

    /** @var int Límite de tiempo con el que se construyó (para verificaciones). */
    public int $configured_time_limit;

    /** @var int Límite de memoria con el que se construyó (para verificaciones). */
    public int $configured_memory_limit;

    public function __construct(int $time_limit = 5, int $memory_limit = 128) {
        parent::__construct($time_limit, $memory_limit);
        $this->configured_time_limit   = $time_limit;
        $this->configured_memory_limit = $memory_limit;
    }

    /**
     * Programa el siguiente resultado que devolverá execute().
     */
    public function push_result(execution_result $result): void {
        $this->queue[] = $result;
    }

    /**
     * Atajo: programa una ejecución "aceptada" con la salida dada.
     */
    public function push_accepted(string $stdout): void {
        $this->push_result(new execution_result('accepted', $stdout));
    }

    /**
     * Atajo: programa una ejecución con estado arbitrario (p. ej. wrong_answer).
     */
    public function push_status(string $status, string $stdout = '', string $stderr = ''): void {
        $this->push_result(new execution_result($status, $stdout, $stderr));
    }

    /**
     * Fija un resultado por defecto para cuando la cola se vacía.
     */
    public function set_default(execution_result $result): void {
        $this->default = $result;
    }

    public function execute(string $code, string $language, string $stdin = ''): execution_result {
        $this->calls[] = ['code' => $code, 'language' => $language, 'stdin' => $stdin];

        if (!empty($this->queue)) {
            return array_shift($this->queue);
        }
        if ($this->default !== null) {
            return $this->default;
        }
        // Sin nada programado: por defecto, respuesta interna de error.
        return new execution_result('internal_error', '', 'mock_executor: sin resultado programado');
    }
}
