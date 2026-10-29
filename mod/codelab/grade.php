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
 * Procesa la calificación manual de una entrega.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/classes/grade_request.php');

// Envoltura HTTP: lee los parámetros y delega en \mod_codelab\grade_request, que es donde
// viven las comprobaciones (login, capacidad, sesskey) y la escritura de la nota.
$cmid = required_param('id', PARAM_INT);
$sid  = required_param('sid', PARAM_INT);

$result = (new \mod_codelab\grade_request([
    'id'             => $cmid,
    'sid'            => $sid,
    'grade_override' => optional_param('grade_override', null, PARAM_FLOAT),
    'feedback'       => optional_param('feedback', '', PARAM_TEXT),
    'sesskey'        => optional_param('sesskey', '', PARAM_RAW),
]))->handle();

// Se dice qué nota quedó en el libro y con qué método, porque con «mejor», «primer» o «promedio»
// el valor final puede no ser el que el docente acaba de teclear, y callarlo sería peor.
$message = get_string('grade_saved', 'mod_codelab', (object)[
    'grade'  => $result['grade'] !== null ? format_float($result['grade'], 2) : '-',
    'method' => get_string('grading_' . $result['method'], 'mod_codelab'),
]);

redirect(
    new moodle_url('/mod/codelab/submission.php', ['id' => $cmid, 'sid' => $sid]),
    $message,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
