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
 * Script de actualización de base de datos para mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_codelab_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026042702) {
        $table = new xmldb_table('codelab');
        $field = new xmldb_field(
            'runner_code',
            XMLDB_TYPE_TEXT,
            null,   // length
            null,   // unsigned
            false,  // notnull
            null,   // sequence
            null,   // default
            'solution_code'  // after this field
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026042702, 'codelab');
    }

    if ($oldversion < 2026070600) {
        // Campos de configuración de recomendaciones IA en la tabla de actividad.
        $table = new xmldb_table('codelab');

        $field = new xmldb_field('ai_feedback_enabled', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '0', 'runner_code');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('ai_criteria', XMLDB_TYPE_TEXT,
            null, null, null, null, null, 'ai_feedback_enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Campos para almacenar la recomendación por entrega.
        $subtable = new xmldb_table('codelab_submissions');

        $field = new xmldb_field('ai_feedback', XMLDB_TYPE_TEXT,
            null, null, null, null, null, 'feedback');
        if (!$dbman->field_exists($subtable, $field)) {
            $dbman->add_field($subtable, $field);
        }

        $field = new xmldb_field('ai_feedback_status', XMLDB_TYPE_CHAR, '20',
            null, XMLDB_NOTNULL, null, 'none', 'ai_feedback');
        if (!$dbman->field_exists($subtable, $field)) {
            $dbman->add_field($subtable, $field);
        }

        $field = new xmldb_field('ai_feedback_time', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, null, '0', 'ai_feedback_status');
        if (!$dbman->field_exists($subtable, $field)) {
            $dbman->add_field($subtable, $field);
        }

        upgrade_mod_savepoint(true, 2026070600, 'codelab');
    }

    if ($oldversion < 2026091801) {
        // Índice sobre la clave foránea `testcase_id` (MDL-08). Es la tabla que más crece —una
        // fila por caso de prueba, por entrega, por estudiante— y las estadísticas del docente
        // la filtran por esa columna. Sin índice, cada consulta recorre la tabla entera.
        $table = new xmldb_table('codelab_submission_results');
        $index = new xmldb_index('idx_testcase', XMLDB_INDEX_NOTUNIQUE, ['testcase_id']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026091801, 'codelab');
    }

    return true;
}
