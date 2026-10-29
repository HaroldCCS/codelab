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
 * Pruebas de la fábrica y configuración de proveedores de IA.
 *
 * No hacen llamadas de red: solo verifican la selección de proveedor según la
 * configuración del sitio y la lógica de modelo por defecto.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\ai\ai_provider
 */

namespace mod_codelab;

use mod_codelab\ai\ai_provider;
use mod_codelab\ai\claude_provider;
use mod_codelab\ai\gemini_provider;
use mod_codelab\ai\openai_provider;

defined('MOODLE_INTERNAL') || die();

final class ai_provider_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Configura el sitio con la IA activada.
     */
    private function enable_ai(string $provider = 'claude', string $key = 'sk-test', string $model = ''): void {
        set_config('ai_enabled', 1, 'mod_codelab');
        set_config('ai_provider', $provider, 'mod_codelab');
        set_config('ai_api_key', $key, 'mod_codelab');
        set_config('ai_model', $model, 'mod_codelab');
    }

    public function test_make_returns_null_when_disabled(): void {
        set_config('ai_enabled', 0, 'mod_codelab');
        set_config('ai_api_key', 'sk-test', 'mod_codelab');
        $this->assertNull(ai_provider::make());
    }

    public function test_make_returns_null_when_no_api_key(): void {
        set_config('ai_enabled', 1, 'mod_codelab');
        set_config('ai_api_key', '', 'mod_codelab');
        $this->assertNull(ai_provider::make());
    }

    public function test_make_returns_null_when_key_is_whitespace(): void {
        set_config('ai_enabled', 1, 'mod_codelab');
        set_config('ai_api_key', '   ', 'mod_codelab');
        $this->assertNull(ai_provider::make());
    }

    public function test_make_selects_claude(): void {
        $this->enable_ai('claude');
        $this->assertInstanceOf(claude_provider::class, ai_provider::make());
    }

    public function test_make_selects_gemini(): void {
        $this->enable_ai('gemini');
        $this->assertInstanceOf(gemini_provider::class, ai_provider::make());
    }

    public function test_make_selects_openai(): void {
        $this->enable_ai('openai');
        $this->assertInstanceOf(openai_provider::class, ai_provider::make());
    }

    public function test_make_defaults_to_claude_for_unknown_provider(): void {
        $this->enable_ai('proveedor_raro');
        $this->assertInstanceOf(claude_provider::class, ai_provider::make());
    }

    public function test_model_uses_default_when_unset(): void {
        $provider = new claude_provider('sk-test', '');
        $this->assertNotEmpty($this->call_model($provider));
    }

    public function test_model_uses_configured_value_when_set(): void {
        $provider = new claude_provider('sk-test', 'modelo-personalizado');
        $this->assertSame('modelo-personalizado', $this->call_model($provider));
    }

    /**
     * Invoca el método protegido model() por reflexión (solo para pruebas).
     */
    private function call_model(ai_provider $provider): string {
        $method = new \ReflectionMethod(ai_provider::class, 'model');
        $method->setAccessible(true);
        return $method->invoke($provider);
    }
}
