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
 * Proveedor de IA basado en la API de Google (Gemini).
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Cliente para la API de Google Generative Language (Gemini).
 */
class gemini_provider extends ai_provider {

    /** Base del endpoint; el modelo y la clave se añaden en generate(). */
    private const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    protected function default_model(): string {
        return 'gemini-1.5-flash';
    }

    public function generate(string $prompt): string {
        // La clave viaja como parámetro de consulta; el modelo forma parte de la ruta.
        $url = self::ENDPOINT_BASE . rawurlencode($this->model())
             . ':generateContent?key=' . rawurlencode($this->api_key);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
        ];

        $data = $this->post_json($url, $payload, []);

        // Respuesta: { "candidates": [ { "content": { "parts": [ { "text": "..." } ] } } ] }
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (trim($text) === '') {
            throw new \moodle_exception('ai_response_error', 'mod_codelab');
        }

        return trim($text);
    }
}
