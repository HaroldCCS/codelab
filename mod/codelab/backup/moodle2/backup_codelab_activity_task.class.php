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
 * Tarea de backup para mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/codelab/backup/moodle2/backup_codelab_stepslib.php');

class backup_codelab_activity_task extends backup_activity_task {

    protected function define_my_settings(): void {
    }

    protected function define_my_steps(): void {
        $this->add_step(new backup_codelab_activity_structure_step('codelab_structure', 'codelab.xml'));
    }

    // Sin tipo en $content: el padre (backup_activity_task) no lo declara y PHP
    // no admite estrechar un parámetro heredado; con "string" la clase da un
    // error fatal al cargarse y ninguna copia de seguridad del curso termina.
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Vista de la actividad.
        $pattern     = "/({$base}\/mod\/codelab\/view\.php\?id=)([0-9]+)/";
        $replacement = '$@CODELABVIEWBYID*$2@$';
        $content = preg_replace($pattern, $replacement, $content);

        return $content;
    }
}
