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
 * Generador de recomendaciones cualitativas por IA para una entrega.
 *
 * Orquesta: toma la actividad, el código del estudiante y el resultado de los
 * casos de prueba, construye un prompt orientado por los criterios activados por
 * el docente, y delega la generación al proveedor de IA configurado.
 *
 * Regla dura del proyecto: la salida es SOLO retroalimentación cualitativa.
 * Nunca calcula ni modifica la nota.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Construye el prompt y obtiene la recomendación del proveedor.
 */
class feedback_generator {

    /** @var ai_provider Proveedor de IA ya instanciado. */
    private ai_provider $provider;

    /** Límite de código enviado al proveedor (evita prompts enormes). */
    private const MAX_CODE_CHARS = 12000;

    public function __construct(ai_provider $provider) {
        $this->provider = $provider;
    }

    /**
     * Crea el generador usando el proveedor configurado en el sitio.
     *
     * @return self|null null si la IA está deshabilitada o mal configurada.
     */
    public static function create(): ?self {
        $provider = ai_provider::make();
        return $provider ? new self($provider) : null;
    }

    /**
     * Indica si una actividad tiene la función activada y criterios seleccionados.
     */
    public static function is_enabled_for(\stdClass $codelab): bool {
        if (empty($codelab->ai_feedback_enabled)) {
            return false;
        }
        $criteria = self::decode_criteria($codelab);
        return !empty($criteria);
    }

    /**
     * Genera la recomendación para una entrega concreta.
     *
     * @param \stdClass $codelab      Instancia de la actividad.
     * @param string    $student_code Código ORIGINAL del estudiante (sin envolver).
     * @param string    $language     Lenguaje de programación.
     * @param array     $grading_result Resultado devuelto por el grader.
     * @return string Recomendación en formato Markdown.
     * @throws \moodle_exception Si el proveedor falla.
     */
    public function generate(\stdClass $codelab, string $student_code, string $language, array $grading_result): string {
        $prompt = $this->build_prompt($codelab, $student_code, $language, $grading_result);
        return $this->provider->generate($prompt);
    }

    /**
     * Genera la recomendación de una entrega y la persiste, aislando cualquier fallo.
     *
     * Punto de entrada usado por la entrega (execute.php). La nota YA está calculada
     * y guardada antes de llamar aquí: pase lo que pase con la IA, la calificación
     * no se toca. Si el proveedor no está configurado o falla, solo se registra el
     * estado (`disabled`/`error`) y se devuelve null.
     *
     * @param \stdClass $codelab
     * @param int       $submission_id
     * @param string    $student_code   Código ORIGINAL del estudiante.
     * @param string    $language
     * @param array     $grading_result
     * @param self|null $generator      Inyectable en pruebas; si es null se usa create().
     * @return string|null Texto de la recomendación o null si no se pudo generar.
     */
    public static function run_for_submission(
        \stdClass $codelab,
        int $submission_id,
        string $student_code,
        string $language,
        array $grading_result,
        ?self $generator = null
    ): ?string {
        global $DB;

        $generator = $generator ?? self::create();
        if (!$generator) {
            // La actividad lo pide, pero el sitio no tiene la IA configurada.
            $DB->set_field('codelab_submissions', 'ai_feedback_status', 'disabled', ['id' => $submission_id]);
            return null;
        }

        try {
            $text = $generator->generate($codelab, $student_code, $language, $grading_result);

            $record = $DB->get_record('codelab_submissions', ['id' => $submission_id]);
            $record->ai_feedback        = $text;
            $record->ai_feedback_status = 'ok';
            $record->ai_feedback_time   = time();
            $DB->update_record('codelab_submissions', $record);

            return $text;
        } catch (\Throwable $e) {
            debugging('CodeLab: fallo generando recomendación IA: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $DB->set_field('codelab_submissions', 'ai_feedback_status', 'error', ['id' => $submission_id]);
            return null;
        }
    }

    /**
     * Construye el prompt en español a partir del contexto de la entrega.
     */
    private function build_prompt(\stdClass $codelab, string $student_code, string $language, array $grading_result): string {
        $criteria = self::decode_criteria($codelab);
        $criteria_section = criteria::build_prompt_section($criteria);

        // Enunciado del ejercicio (texto plano, sin HTML).
        $statement = trim(html_to_text($codelab->intro ?? '', 0, false));
        if ($statement === '') {
            $statement = '(No se proporcionó enunciado.)';
        }

        // Resumen de casos de prueba (solo conteo; no se filtran salidas ocultas).
        $passed = (int)($grading_result['tests_passed'] ?? 0);
        $total  = (int)($grading_result['tests_total'] ?? 0);

        // Recortar código muy largo por seguridad del prompt.
        $code = $student_code;
        if (mb_strlen($code) > self::MAX_CODE_CHARS) {
            $code = mb_substr($code, 0, self::MAX_CODE_CHARS) . "\n/* ...código truncado... */";
        }

        return <<<PROMPT
Eres un tutor de programación que da retroalimentación breve y constructiva a un estudiante.
Escribe SIEMPRE en español, en segunda persona (tutéalo) y con tono alentador.

Enunciado del ejercicio:
{$statement}

Lenguaje de programación: {$language}
Resultado de las pruebas automáticas: {$passed} de {$total} casos correctos.

Evalúa el código ÚNICAMENTE según estos criterios:
{$criteria_section}

Código del estudiante:
```{$language}
{$code}
```

Instrucciones de salida (obligatorias):
- Da entre 2 y 5 recomendaciones concretas y accionables, en viñetas Markdown.
- Enfócate solo en los criterios listados; ignora otros aspectos.
- NO asignes ni menciones ninguna nota o puntaje; eso lo calcula el sistema, no tú.
- NO reescribas la solución completa; a lo sumo muestra fragmentos cortos como ejemplo.
- Si el código ya es bueno en algún criterio, reconócelo brevemente.
- Sé conciso: máximo unas 180 palabras en total.
PROMPT;
    }

    /**
     * Decodifica y sanea la lista de criterios guardada en la actividad.
     *
     * @return string[]
     */
    private static function decode_criteria(\stdClass $codelab): array {
        $raw = json_decode($codelab->ai_criteria ?? '[]', true);
        if (!is_array($raw)) {
            return [];
        }
        return criteria::sanitize($raw);
    }
}
