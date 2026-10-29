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
 * Pruebas del catálogo de criterios cualitativos de la IA.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\ai\criteria
 */

namespace mod_codelab;

use mod_codelab\ai\criteria;

defined('MOODLE_INTERNAL') || die();

final class ai_criteria_test extends \advanced_testcase {

    public function test_all_keys_are_stable_and_nonempty(): void {
        $keys = criteria::all_keys();
        $this->assertNotEmpty($keys);
        // Claves esperadas (son estables porque se persisten en BD).
        $this->assertContains('readability', $keys);
        $this->assertContains('efficiency', $keys);
        $this->assertContains('best_practices', $keys);
        $this->assertContains('naming', $keys);
        $this->assertContains('error_handling', $keys);
        $this->assertContains('modularity', $keys);
    }

    public function test_options_maps_every_key_to_a_label(): void {
        $options = criteria::options();
        $this->assertSameSize(criteria::all_keys(), $options);
        foreach (criteria::all_keys() as $key) {
            $this->assertArrayHasKey($key, $options);
            $this->assertNotEmpty($options[$key]);
        }
    }

    public function test_sanitize_drops_unknown_keys(): void {
        $input  = ['readability', 'inventado', 'efficiency', '', 'DROP TABLE'];
        $result = criteria::sanitize($input);
        $this->assertSame(['readability', 'efficiency'], $result);
    }

    public function test_sanitize_empty_returns_empty(): void {
        $this->assertSame([], criteria::sanitize([]));
    }

    public function test_build_prompt_section_lists_only_active_criteria(): void {
        $section = criteria::build_prompt_section(['readability', 'efficiency']);
        // Una línea (viñeta) por criterio activo.
        $lines = array_filter(explode("\n", $section));
        $this->assertCount(2, $lines);
        // El texto de la pista debe estar presente y ser en español.
        $this->assertStringContainsStringIgnoringCase('Legibilidad', $section);
        $this->assertStringContainsStringIgnoringCase('Eficiencia', $section);
        // No debe filtrarse un criterio no seleccionado.
        $this->assertStringNotContainsStringIgnoringCase('Modularidad', $section);
    }

    public function test_build_prompt_section_ignores_invalid_keys(): void {
        $section = criteria::build_prompt_section(['readability', 'no_existe']);
        $lines = array_filter(explode("\n", $section));
        $this->assertCount(1, $lines);
    }
}
