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
 * Configuración de administración del sitio para mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_codelab/judge0_heading',
        get_string('admin_settings', 'mod_codelab'),
        get_string('pluginname', 'mod_codelab')
    ));

    // URL de la API. Por política del proyecto el motor es AUTO-HOSPEDADO (Docker),
    // nunca un servicio gestionado de terceros. Default = servidor del overlay
    // docker-compose.judge0.yml (ajústalo a la URL de tu host Judge0).
    $settings->add(new admin_setting_configtext(
        'mod_codelab/judge0_api_url',
        get_string('judge0_api_url', 'mod_codelab'),
        get_string('judge0_api_url_desc', 'mod_codelab'),
        'http://judge0_server:2358',
        PARAM_RAW
    ));

    // Token del motor (cabecera X-Auth-Token). Vacío si el servidor propio no exige token.
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_codelab/judge0_api_key',
        get_string('judge0_api_key', 'mod_codelab'),
        get_string('judge0_api_key_desc', 'mod_codelab'),
        ''
    ));

    // Se retiraron los ajustes «Host de la API» y «Instancia auto-alojada» (SEC-02): existían
    // solo para hablar con un servicio gestionado de terceros, que la política del proyecto
    // (contexto.md §5.1) prohíbe. El modo auto-hospedado ya no es opcional.

    // ─────────────────────────────────────────────────────────────────────────
    // Recomendaciones por IA (retroalimentación cualitativa, NO afecta la nota).
    // ─────────────────────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'mod_codelab/ai_heading',
        get_string('ai_settings', 'mod_codelab'),
        get_string('ai_settings_desc', 'mod_codelab')
    ));

    // Interruptor maestro: habilitar la función a nivel de sitio.
    $settings->add(new admin_setting_configcheckbox(
        'mod_codelab/ai_enabled',
        get_string('ai_enabled', 'mod_codelab'),
        get_string('ai_enabled_desc', 'mod_codelab'),
        0
    ));

    // Proveedor de IA a utilizar.
    $settings->add(new admin_setting_configselect(
        'mod_codelab/ai_provider',
        get_string('ai_provider', 'mod_codelab'),
        get_string('ai_provider_desc', 'mod_codelab'),
        'claude',
        [
            'claude' => get_string('ai_provider_claude', 'mod_codelab'),
            'gemini' => get_string('ai_provider_gemini', 'mod_codelab'),
            'openai' => get_string('ai_provider_openai', 'mod_codelab'),
        ]
    ));

    // Clave de API del proveedor seleccionado.
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_codelab/ai_api_key',
        get_string('ai_api_key', 'mod_codelab'),
        get_string('ai_api_key_desc', 'mod_codelab'),
        ''
    ));

    // Modelo a utilizar (opcional; vacío = modelo por defecto de cada proveedor).
    $settings->add(new admin_setting_configtext(
        'mod_codelab/ai_model',
        get_string('ai_model', 'mod_codelab'),
        get_string('ai_model_desc', 'mod_codelab'),
        '',
        PARAM_RAW_TRIMMED
    ));
}
