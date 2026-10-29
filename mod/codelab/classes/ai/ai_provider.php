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
 * Interfaz base para proveedores de IA (retroalimentación cualitativa).
 *
 * Cada proveedor concreto (Claude, Gemini, OpenAI) implementa generate(). Así el
 * resto del plugin no depende del proveedor: se elige en la configuración del
 * sitio y aquí se instancia con una fábrica.
 *
 * IMPORTANTE: estas recomendaciones son cualitativas y NUNCA calculan ni alteran
 * la nota del estudiante.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Base común para los proveedores de IA.
 */
abstract class ai_provider {

    /** @var string Clave de API del proveedor. */
    protected string $api_key;
    /** @var string Modelo a utilizar (o '' para el modelo por defecto del proveedor). */
    protected string $model;

    public function __construct(string $api_key, string $model = '') {
        $this->api_key = $api_key;
        $this->model   = trim($model);
    }

    /**
     * Genera texto a partir de un prompt.
     *
     * @param string $prompt Prompt completo en lenguaje natural.
     * @return string Texto generado.
     * @throws \moodle_exception Si la llamada falla o la respuesta es inválida.
     */
    abstract public function generate(string $prompt): string;

    /**
     * Modelo por defecto del proveedor si el administrador no especificó uno.
     */
    abstract protected function default_model(): string;

    /**
     * Modelo efectivo (configurado o por defecto).
     */
    protected function model(): string {
        return $this->model !== '' ? $this->model : $this->default_model();
    }

    /**
     * Fábrica: crea el proveedor configurado en el sitio.
     *
     * @return ai_provider|null null si la IA está deshabilitada o falta la API key.
     */
    public static function make(): ?ai_provider {
        if (!get_config('mod_codelab', 'ai_enabled')) {
            return null;
        }

        $key = (string)get_config('mod_codelab', 'ai_api_key');
        if (trim($key) === '') {
            return null;
        }

        $provider = (string)get_config('mod_codelab', 'ai_provider') ?: 'claude';
        $model    = (string)get_config('mod_codelab', 'ai_model');

        return match ($provider) {
            'gemini' => new gemini_provider($key, $model),
            'openai' => new openai_provider($key, $model),
            default  => new claude_provider($key, $model),
        };
    }

    /**
     * Realiza una petición HTTP POST con cuerpo JSON y devuelve la respuesta decodificada.
     *
     * @param string $url
     * @param array  $payload Cuerpo que se codificará a JSON.
     * @param array  $headers Cabeceras HTTP adicionales.
     * @return array Respuesta JSON decodificada como array asociativo.
     * @throws \moodle_exception En error de red o respuesta HTTP no exitosa.
     */
    protected function post_json(string $url, array $payload, array $headers): array {
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error !== '') {
            debugging('CodeLab IA error de red: ' . $error, DEBUG_DEVELOPER);
            throw new \moodle_exception('ai_connection_error', 'mod_codelab');
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            debugging('CodeLab IA HTTP ' . $httpcode . ': ' . (string)$response, DEBUG_DEVELOPER);
            throw new \moodle_exception('ai_response_error', 'mod_codelab');
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data)) {
            throw new \moodle_exception('ai_response_error', 'mod_codelab');
        }

        return $data;
    }
}
