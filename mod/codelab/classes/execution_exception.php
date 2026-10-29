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
 * Error de validación del endpoint de ejecución.
 *
 * Sustituye al antiguo send_json_error(), que imprimía y hacía exit(): un exit() dentro de la
 * lógica la vuelve imposible de probar, porque mata el proceso de PHPUnit. Al lanzar una
 * excepción, cada comprobación se puede ejercitar por separado y quien invoque decide cómo
 * representar el fallo (JSON en el endpoint, aserción en una prueba).
 *
 * Hereda de moodle_exception para conservar $errorcode y la traducción de la cadena: el mensaje
 * que ve el estudiante es exactamente el mismo de antes.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

class execution_exception extends \moodle_exception {
}
