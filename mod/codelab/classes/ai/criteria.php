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
 * Catálogo de criterios cualitativos que la IA puede evaluar.
 *
 * Los criterios son fijos y el docente los activa por actividad. Cada criterio
 * tiene una clave estable (se guarda en BD) y una descripción que se inyecta en
 * el prompt para orientar a la IA.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Define y describe los criterios cualitativos disponibles.
 */
class criteria {

    /**
     * Instrucciones internas por criterio (en español), usadas en el prompt.
     * La clave es estable y no debe cambiarse (se persiste en la BD).
     *
     * @var array<string,string>
     */
    private const PROMPT_HINTS = [
        'readability'    => 'Legibilidad y estilo: claridad del código, indentación, longitud de funciones y uso de comentarios útiles.',
        'efficiency'     => 'Eficiencia y complejidad: uso de estructuras de datos y algoritmos adecuados; identifica cuellos de botella o cálculos redundantes.',
        'best_practices' => 'Buenas prácticas del lenguaje: idiomatismos, funciones de la librería estándar y patrones recomendados en el lenguaje usado.',
        'naming'         => 'Nombres: qué tan descriptivos y consistentes son los nombres de variables, funciones y clases.',
        'error_handling' => 'Manejo de errores y casos borde: validación de entradas, valores límite y situaciones no cubiertas por los casos de prueba.',
        'modularity'     => 'Modularidad y estructura: separación de responsabilidades, reutilización y organización general de la solución.',
    ];

    /**
     * Devuelve las claves de todos los criterios disponibles.
     *
     * @return string[]
     */
    public static function all_keys(): array {
        return array_keys(self::PROMPT_HINTS);
    }

    /**
     * Devuelve la etiqueta traducible de un criterio (para la UI).
     */
    public static function label(string $key): string {
        return get_string('ai_criterion_' . $key, 'mod_codelab');
    }

    /**
     * Devuelve el mapa clave => etiqueta para pintar los checkboxes del formulario.
     *
     * @return array<string,string>
     */
    public static function options(): array {
        $options = [];
        foreach (self::all_keys() as $key) {
            $options[$key] = self::label($key);
        }
        return $options;
    }

    /**
     * Filtra una lista de claves dejando solo las válidas (defensa ante datos viejos).
     *
     * @param string[] $keys
     * @return string[]
     */
    public static function sanitize(array $keys): array {
        return array_values(array_intersect($keys, self::all_keys()));
    }

    /**
     * Construye el fragmento del prompt con las instrucciones de los criterios activos.
     *
     * @param string[] $keys Claves de criterios activados.
     * @return string Texto listo para insertar en el prompt (una línea por criterio).
     */
    public static function build_prompt_section(array $keys): string {
        $lines = [];
        foreach (self::sanitize($keys) as $key) {
            $lines[] = '- ' . self::PROMPT_HINTS[$key];
        }
        return implode("\n", $lines);
    }
}
