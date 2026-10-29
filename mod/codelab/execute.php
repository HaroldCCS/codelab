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
 * Endpoint AJAX para ejecutar código y calificar con casos de prueba.
 *
 * Recibe: POST JSON con { cmid, code, language, action: 'run'|'submit', sesskey }
 * Devuelve: JSON con resultados de ejecución y calificación.
 *
 * Este archivo es SOLO la envoltura HTTP: lee el cuerpo de la petición, lo decodifica y delega
 * en \mod_codelab\execution_request, que es donde vive la lógica y las comprobaciones. La razón
 * es que la entrada sea inyectable: mientras la lógica leía de php://input, ninguna prueba podía
 * alcanzarla, porque bajo la CLI de PHPUnit ese flujo siempre está vacío (INC-98).
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/execution_request.php');

// Leer el body JSON. Si no es un objeto JSON válido se trata como petición vacía, y la
// validación de parámetros de execution_request la rechazará con el mensaje de siempre.
$rawbody = file_get_contents('php://input');
$decoded = json_decode($rawbody, true);
$input   = is_array($decoded) ? $decoded : [];

// Compatibilidad: si a confirm_sesskey() se le llama sin argumento, cae a required_param() sobre
// $_POST/$_GET. Aquí siempre se le pasa el valor explícito, pero se mantiene la exposición para
// no cambiar el comportamiento de nada que dependa de ella.
if (!empty($input['sesskey'])) {
    $_POST['sesskey'] = $input['sesskey'];
}

header('Content-Type: application/json');

try {
    $response = (new \mod_codelab\execution_request($input))->handle();
} catch (\mod_codelab\execution_exception $e) {
    // Mismo formato de error que devolvía el antiguo send_json_error().
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
