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
 * Proveedor de IA simulado (test double) para las pruebas.
 *
 * Registra el prompt recibido y devuelve una respuesta predefinida (o lanza una
 * excepción) sin hacer ninguna llamada de red. Así se prueba la lógica de
 * feedback_generator de forma determinista.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\ai;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../classes/ai/ai_provider.php');

/**
 * Proveedor de IA que devuelve texto fijo o simula un fallo.
 */
class mock_ai_provider extends ai_provider {

    /** @var string[] Prompts recibidos, en orden (para verificaciones). */
    public array $prompts = [];

    /** @var string Texto que devolverá generate(). */
    private string $canned;

    /** @var bool Si true, generate() lanza una excepción (simula fallo del servicio). */
    private bool $should_fail;

    public function __construct(string $canned = 'Sugerencia de prueba.', bool $should_fail = false) {
        parent::__construct('fake-key', '');
        $this->canned      = $canned;
        $this->should_fail = $should_fail;
    }

    protected function default_model(): string {
        return 'mock-model';
    }

    public function generate(string $prompt): string {
        $this->prompts[] = $prompt;

        if ($this->should_fail) {
            throw new \moodle_exception('ai_response_error', 'mod_codelab');
        }

        return $this->canned;
    }
}
