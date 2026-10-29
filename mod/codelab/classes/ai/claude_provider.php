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
 * Proveedor de IA basado en la API de mensajes de Anthropic (Claude).
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Cliente para la API de Anthropic (Claude).
 */
class claude_provider extends ai_provider {

    /** Endpoint de la API de mensajes. */
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    /** Versión de la API requerida en la cabecera. */
    private const API_VERSION = '2023-06-01';

    protected function default_model(): string {
        return 'claude-3-5-haiku-latest';
    }

    public function generate(string $prompt): string {
        $payload = [
            'model'      => $this->model(),
            'max_tokens' => 1024,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $headers = [
            'x-api-key: ' . $this->api_key,
            'anthropic-version: ' . self::API_VERSION,
        ];

        $data = $this->post_json(self::ENDPOINT, $payload, $headers);

        // Respuesta: { "content": [ { "type": "text", "text": "..." } ] }
        $text = $data['content'][0]['text'] ?? '';
        if (trim($text) === '') {
            throw new \moodle_exception('ai_response_error', 'mod_codelab');
        }

        return trim($text);
    }
}
