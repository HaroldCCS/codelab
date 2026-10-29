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
 * Pruebas de execution_result (comparación de salida y serialización).
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_codelab\executor\execution_result
 */

namespace mod_codelab;

use mod_codelab\executor\execution_result;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/executor/base_executor.php');

final class execution_result_test extends \basic_testcase {

    public function test_exact_match(): void {
        $r = new execution_result('accepted', '10');
        $this->assertTrue($r->matches_expected('10'));
        $this->assertFalse($r->matches_expected('11'));
    }

    public function test_trailing_whitespace_and_newlines_are_ignored(): void {
        $r = new execution_result('accepted', "10\n");
        $this->assertTrue($r->matches_expected('10'));
        $this->assertTrue($r->matches_expected("10\n\n"));

        $r2 = new execution_result('accepted', "  hola mundo  \n");
        $this->assertTrue($r2->matches_expected('hola mundo'));
    }

    public function test_crlf_is_normalised(): void {
        // Salida con saltos de línea de Windows debe compararse igual que Unix.
        $r = new execution_result('accepted', "linea1\r\nlinea2\r\n");
        $this->assertTrue($r->matches_expected("linea1\nlinea2"));
    }

    public function test_internal_whitespace_matters(): void {
        // Solo se normalizan extremos y fin de línea; los espacios internos cuentan.
        $r = new execution_result('accepted', 'a  b');
        $this->assertFalse($r->matches_expected('a b'));
    }

    public function test_to_array_contains_all_fields(): void {
        $r = new execution_result('runtime_error', 'out', 'err', 'compile', 0.5, 2048, 'boom');
        $arr = $r->to_array();
        $this->assertSame('runtime_error', $arr['status']);
        $this->assertSame('out', $arr['stdout']);
        $this->assertSame('err', $arr['stderr']);
        $this->assertSame('compile', $arr['compile_output']);
        $this->assertEqualsWithDelta(0.5, $arr['time'], 0.0001);
        $this->assertSame(2048, $arr['memory']);
        $this->assertSame('boom', $arr['message']);
    }
}
