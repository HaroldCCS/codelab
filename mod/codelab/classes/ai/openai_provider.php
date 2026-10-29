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
 * Proveedor de IA basado en la API de chat de OpenAI.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Cliente para la API de OpenAI (Chat Completions).
 */
class openai_provider extends ai_provider {

    /** Endpoint de chat completions. */
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    protected function default_model(): string {
        return 'gpt-4o-mini';
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
            'Authorization: Bearer ' . $this->api_key,
        ];

        $data = $this->post_json(self::ENDPOINT, $payload, $headers);

        // Respuesta: { "choices": [ { "message": { "content": "..." } } ] }
        $text = $data['choices'][0]['message']['content'] ?? '';
        if (trim($text) === '') {
            throw new \moodle_exception('ai_response_error', 'mod_codelab');
        }

        return trim($text);
    }
}
