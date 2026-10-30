<?php
// Prueba de extremo a extremo en el Moodle desplegado: Moodle -> plugin -> Judge0 -> libro.
//
// Se ejecuta en el nodo Moodle, dentro del contenedor, DESPUÉS de scripts/seed-demo.php:
//     docker exec -u www-data moodle php /tmp/pruebas-moodle-e2e.php
//
// Actúa como el estudiante de demostración (sin contraseña: la sesión se fija desde la
// CLI) y usa \mod_codelab\execution_request, la misma clase que atiende execute.php, con
// el motor REAL configurado en el sitio. Imprime JSON para las evidencias.

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/mod/codelab/classes/execution_request.php');
require_once($CFG->libdir . '/gradelib.php');

global $DB, $USER;

$salida = ['fecha_utc' => gmdate('Y-m-d\TH:i:s\Z')];
$salida['moodle'] = $CFG->release;
$salida['php'] = PHP_VERSION;
$salida['plugin'] = get_config('mod_codelab', 'release') ?: (string)get_config('mod_codelab', 'version');
$salida['motor_configurado'] = get_config('mod_codelab', 'judge0_api_url');
$salida['token_configurado'] = get_config('mod_codelab', 'judge0_api_key') !== '' ? 'sí' : 'no';

$codelab = $DB->get_record('codelab', ['name' => 'Suma de dos números'], '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('codelab', $codelab->id, 0, false, MUST_EXIST);
$student = $DB->get_record('user', ['username' => 'estudiante'], '*', MUST_EXIST);

\core\session\manager::set_user($student);

$peticion = function (string $codigo, string $accion) use ($cm): array {
    $t0 = microtime(true);
    try {
        $r = (new \mod_codelab\execution_request([
            'cmid' => $cm->id, 'code' => $codigo, 'language' => 'python',
            'action' => $accion, 'sesskey' => sesskey(),
        ]))->handle();
        $r['segundos'] = round(microtime(true) - $t0, 2);
        unset($r['results']);   // el detalle por caso no hace falta en el resumen
        return $r;
    } catch (\Throwable $e) {
        return ['excepcion' => get_class($e), 'mensaje' => $e->getMessage(),
                'segundos' => round(microtime(true) - $t0, 2)];
    }
};

$salida['1_ejecutar_codigo_correcto']   = $peticion("a, b = map(int, input().split())\nprint(a + b)\n", 'run');
$salida['2_ejecutar_codigo_incorrecto'] = $peticion("print(0)\n", 'run');
$salida['3_entregar_codigo_correcto']   = $peticion("a, b = map(int, input().split())\nprint(a + b)\n", 'submit');

$salida['4_entregas_en_bd'] = array_values(array_map(fn($s) => [
    'id' => (int)$s->id, 'estado' => $s->status, 'nota' => (float)$s->grade,
    'casos' => "{$s->tests_passed}/{$s->tests_total}",
], $DB->get_records('codelab_submissions', ['codelabid' => $codelab->id, 'userid' => $student->id], 'id')));

$g = grade_get_grades($codelab->course, 'mod', 'codelab', $codelab->id, $student->id);
$item = reset($g->items);
$salida['5_libro_de_calificaciones'] = $item ? [
    'item' => $item->name, 'nota' => $item->grades[$student->id]->grade ?? null, 'max' => $item->grademax,
] : null;

echo json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
