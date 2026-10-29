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
 * Prueba de db/upgrade.php: el primer cambio de esquema desde julio.
 *
 * Todos los bumps de versión anteriores a 1.5.0 fueron sin esquema, así que este archivo
 * nunca se había ejecutado de verdad en este proyecto (ni siquiera implícitamente: instalar
 * el plugin desde cero corre db/install.xml, no db/upgrade.php). Un Moodle NUEVO nunca pasa
 * por aquí; el único que lo hace es uno YA instalado con una versión anterior, que es
 * exactamente el escenario que el entorno de pruebas no reproduce por sí solo, así que hay
 * que simularlo: borrar el índice que se supone que ya existe (por venir de install.xml) y
 * comprobar que el paso de actualización lo repone.
 *
 * @package    mod_codelab
 * @category   test
 * @copyright  2026 ProyectoEditorMoodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

namespace mod_codelab;

defined('MOODLE_INTERNAL') || die();

final class upgrade_test extends \advanced_testcase {

    public function test_xmldb_codelab_upgrade_agrega_el_indice_de_testcase_id_si_falta(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        require_once($CFG->libdir . '/upgradelib.php');          // upgrade_mod_savepoint().
        require_once($CFG->dirroot . '/mod/codelab/db/upgrade.php');
        // upgrade_mod_savepoint() aborta con downgrade_exception si la versión registrada ya es
        // igual o mayor que la del paso: el entorno de pruebas registra la versión ACTUAL del
        // complemento, así que hay que retrasarla igual que lo estaría en un sitio antiguo.
        set_config('version', 2026091800, 'mod_codelab');

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('codelab_submission_results');
        $index = new \xmldb_index('idx_testcase', XMLDB_INDEX_NOTUNIQUE, ['testcase_id']);

        // Simular un Moodle instalado ANTES de MDL-08 (2026091801): el entorno de pruebas
        // instala siempre desde install.xml, así que el índice ya existe: hay que quitarlo a
        // propósito para reproducir el estado real de una instalación antigua.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $this->assertFalse($dbman->index_exists($table, $index), 'El índice debe estar ausente antes de la prueba');

        // 2026091800: justo antes del paso que añade el índice. Los pasos más antiguos del
        // archivo (campos de la actividad, de IA) no se ejecutan porque sus condiciones ya
        // son falsas para este oldversion.
        $ok = xmldb_codelab_upgrade(2026091800);
        $this->assertTrue($ok);

        $this->assertTrue(
            $dbman->index_exists($table, $index),
            'El paso de actualización 2026091801 debe crear idx_testcase cuando falta'
        );
    }

    public function test_xmldb_codelab_upgrade_es_idempotente_si_el_indice_ya_existe(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        require_once($CFG->libdir . '/upgradelib.php');          // upgrade_mod_savepoint().
        require_once($CFG->dirroot . '/mod/codelab/db/upgrade.php');
        // upgrade_mod_savepoint() aborta con downgrade_exception si la versión registrada ya es
        // igual o mayor que la del paso: el entorno de pruebas registra la versión ACTUAL del
        // complemento, así que hay que retrasarla igual que lo estaría en un sitio antiguo.
        set_config('version', 2026091800, 'mod_codelab');

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('codelab_submission_results');
        $index = new \xmldb_index('idx_testcase', XMLDB_INDEX_NOTUNIQUE, ['testcase_id']);

        // Aquí SÍ se aprovecha que el entorno de pruebas ya trae el índice de install.xml: es
        // el caso de una instalación que ya pasó por este paso (o que nació después de él), y
        // que un futuro cambio en upgrade.php no debe romper con un error de "índice duplicado"
        // si vuelve a ejecutarse.
        $this->assertTrue($dbman->index_exists($table, $index), 'El entorno de pruebas debe traer el índice de install.xml');

        $ok = xmldb_codelab_upgrade(2026091800);
        $this->assertTrue($ok);
        $this->assertTrue($dbman->index_exists($table, $index));
    }
}
