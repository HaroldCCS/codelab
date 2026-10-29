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
 * Ejecutor de código usando la API de Judge0.
 *
 * Judge0 CE es un sistema de ejecución de código open-source:
 * https://github.com/judge0/judge0
 *
 * Política del proyecto (contexto.md §5.1): el motor es SIEMPRE auto-hospedado vía Docker,
 * en local o en un host propio. Este cliente NO contempla servicios gestionados de terceros:
 * si no hay servidor configurado, la ejecución falla con un mensaje claro en lugar de salir
 * a internet por su cuenta.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\executor;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_executor.php');

class judge0_executor extends base_executor {

    /** @var string URL base del servidor Judge0 auto-hospedado. Cadena vacía = sin configurar. */
    private string $api_url;
    /** @var string Token del motor (cabecera X-Auth-Token). Vacío si el servidor no exige token. */
    private string $api_key;

    /** Margen en segundos que se suma al límite de la actividad para cubrir cola y compilación. */
    private const POLL_OVERHEAD_S = 10;
    /** Tiempo de espera entre reintentos (microsegundos) — 800ms */
    private const POLL_INTERVAL_US = 800000;

    public function __construct(int $time_limit = 5, int $memory_limit = 128) {
        parent::__construct($time_limit, $memory_limit);

        // No hay valor por defecto hacia ningún servicio de terceros. get_config() devuelve
        // false cuando el ajuste no existe todavía, así que se normaliza a cadena: un valor
        // vacío significa "motor sin configurar" y lo detecta execute(), no lo sustituye nada.
        $this->api_url = trim((string)get_config('mod_codelab', 'judge0_api_url'));
        $this->api_key = trim((string)get_config('mod_codelab', 'judge0_api_key'));
    }

    /**
     * Lenguajes que usan JVM y necesitan más memoria mínima.
     */
    private const JVM_LANGUAGES = ['java', 'kotlin', 'scala'];

    /**
     * Memoria mínima garantizada para lenguajes JVM (en MB).
     * La JVM necesita al menos 256 MB para arrancar correctamente.
     */
    private const JVM_MIN_MEMORY_MB = 256;

    /**
     * Ejecuta el código en Judge0 y devuelve el resultado.
     * En caso de compilation timeout (cold start de JVM), reintenta una vez automáticamente.
     */
    public function execute(string $code, string $language, string $stdin = ''): execution_result {
        // Sin servidor configurado no se ejecuta nada: antes se caía a un servicio gestionado
        // de terceros y el código del estudiante salía de la infraestructura propia en silencio.
        if ($this->api_url === '') {
            return new execution_result(
                'internal_error',
                message: get_string('executor_not_configured', 'mod_codelab')
            );
        }

        $language_id = $this->get_language_id($language);

        // Los lenguajes JVM (Java, Kotlin) necesitan más memoria para arrancar la JVM.
        $effective_memory = in_array($language, self::JVM_LANGUAGES)
            ? max($this->memory_limit, self::JVM_MIN_MEMORY_MB)
            : $this->memory_limit;

        $payload = [
            'source_code'     => base64_encode($code),
            'language_id'     => $language_id,
            'stdin'           => base64_encode($stdin),
            'cpu_time_limit'  => $this->time_limit > 0 ? $this->time_limit : 10,
            'memory_limit'    => $effective_memory * 1024, // Judge0 usa KB
        ];

        // Enviar la tarea de ejecución.
        $token = $this->submit_code($payload);
        if (!$token) {
            return new execution_result(
                'internal_error',
                message: get_string('executor_connection_error', 'mod_codelab')
            );
        }

        // Esperar el resultado.
        $result = $this->poll_result($token);

        // Reintento automático si hay compilation timeout (ocurre en el cold start de JVM en Docker).
        // El segundo intento siempre es rápido porque los archivos ya están en caché del SO.
        if ($result->status === 'compile_error'
            && strpos($result->compile_output ?? '', 'Compilation time limit exceeded') !== false
        ) {
            usleep(1500000); // Esperar 1.5s antes de reintentar
            $token2 = $this->submit_code($payload);
            if ($token2) {
                $result = $this->poll_result($token2);
            }
        }

        return $result;
    }

    /**
     * Envía el código a Judge0 y retorna el token de la tarea.
     */
    private function submit_code(array $payload): ?string {
        $url = rtrim($this->api_url, '/') . '/submissions?base64_encoded=true&wait=false';

        $response = $this->http_post($url, json_encode($payload));
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['token'] ?? null;
    }

    /**
     * Consulta el resultado hasta obtener respuesta final.
     */
    private function poll_result(string $token): execution_result {
        $url = rtrim($this->api_url, '/') . '/submissions/' . $token . '?base64_encoded=true';

        // El plugin debe esperar al menos lo que el docente configuró en la actividad; con un
        // tope fijo de 24 s, los límites de 15 s y 30 s del formulario eran inalcanzables.
        $maxattempts = (int)ceil(
            (($this->time_limit > 0 ? $this->time_limit : 10) + self::POLL_OVERHEAD_S)
            / (self::POLL_INTERVAL_US / 1000000)
        );

        for ($i = 0; $i < $maxattempts; $i++) {
            usleep(self::POLL_INTERVAL_US);

            $response = $this->http_get($url);
            if (!$response) {
                continue;
            }

            $data = json_decode($response, true);
            $status_id = $data['status']['id'] ?? 0;

            // Status IDs de Judge0:
            // 1 = In Queue, 2 = Processing, 3 = Accepted, 4 = Wrong Answer
            // 5 = TLE, 6 = Compilation Error, 7-12 = Runtime Errors
            if ($status_id <= 2) {
                continue; // Aún procesando.
            }

            return $this->parse_judge0_response($data);
        }

        return new execution_result('internal_error', message: get_string('executor_timeout', 'mod_codelab'));
    }

    /**
     * Parsea la respuesta de Judge0 a un execution_result.
     */
    private function parse_judge0_response(array $data): execution_result {
        $status_id = $data['status']['id'] ?? 0;
        $stdout    = $this->decode_base64($data['stdout'] ?? '');
        $stderr    = $this->decode_base64($data['stderr'] ?? '');
        $compile   = $this->decode_base64($data['compile_output'] ?? '');
        $time      = isset($data['time']) ? (float)$data['time'] : null;
        $memory    = isset($data['memory']) ? (int)$data['memory'] : null;

        $status_map = [
            3  => 'accepted',
            4  => 'wrong_answer',
            5  => 'time_limit_exceeded',
            6  => 'compile_error',
            7  => 'runtime_error',
            8  => 'runtime_error',
            9  => 'runtime_error',
            10 => 'runtime_error',
            11 => 'runtime_error',
            12 => 'runtime_error',
        ];

        $status = $status_map[$status_id] ?? 'internal_error';
        $message = $data['status']['description'] ?? '';

        return new execution_result($status, $stdout, $stderr, $compile ?: null, $time, $memory, $message);
    }

    /**
     * Realiza una petición HTTP POST.
     */
    private function http_post(string $url, string $body): ?string {
        $headers = $this->get_headers();
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            debugging('CodeLab Judge0 POST error: ' . $error, DEBUG_DEVELOPER);
            return null;
        }
        return $response ?: null;
    }

    /**
     * Realiza una petición HTTP GET.
     */
    private function http_get(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->get_headers(),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            debugging('CodeLab Judge0 GET error: ' . $error, DEBUG_DEVELOPER);
            return null;
        }
        return $response ?: null;
    }

    /**
     * Construye los headers HTTP. Solo hay un modo: servidor propio con X-Auth-Token opcional.
     */
    private function get_headers(): array {
        $headers = ['Accept: application/json'];
        if ($this->api_key !== '') {
            $headers[] = 'X-Auth-Token: ' . $this->api_key;
        }
        return $headers;
    }

    /**
     * Decodifica base64 de forma segura.
     */
    private function decode_base64(string $value): string {
        if (empty($value)) {
            return '';
        }
        $decoded = base64_decode($value, true);
        return $decoded !== false ? $decoded : $value;
    }
}
