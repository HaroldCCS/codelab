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
 * Proveedor de privacidad para GDPR - mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_codelab\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        // La declaración debe cubrir TODOS los campos con datos del estudiante que guarda
        // db/install.xml, no solo los principales: la página de política de datos del sitio se
        // construye a partir de aquí y es la que responde al derecho de información.
        $collection->add_database_table(
            'codelab_submissions',
            [
                'userid'             => 'privacy:metadata:codelab_submissions:userid',
                'attempt_number'     => 'privacy:metadata:codelab_submissions:attempt_number',
                'code'               => 'privacy:metadata:codelab_submissions:code',
                'language'           => 'privacy:metadata:codelab_submissions:language',
                'status'             => 'privacy:metadata:codelab_submissions:status',
                'grade'              => 'privacy:metadata:codelab_submissions:grade',
                'grade_override'     => 'privacy:metadata:codelab_submissions:grade_override',
                'feedback'           => 'privacy:metadata:codelab_submissions:feedback',
                'ai_feedback'        => 'privacy:metadata:codelab_submissions:ai_feedback',
                'ai_feedback_status' => 'privacy:metadata:codelab_submissions:ai_feedback_status',
                'ai_feedback_time'   => 'privacy:metadata:codelab_submissions:ai_feedback_time',
                'tests_passed'       => 'privacy:metadata:codelab_submissions:tests_passed',
                'tests_total'        => 'privacy:metadata:codelab_submissions:tests_total',
                'timecreated'        => 'privacy:metadata:codelab_submissions:timecreated',
                'timemodified'       => 'privacy:metadata:codelab_submissions:timemodified',
            ],
            'privacy:metadata:codelab_submissions'
        );

        // Resultado por caso de prueba. `actual_output` y `stderr` son la salida que produjo el
        // programa escrito por el estudiante: dato personal, no metadato técnico. El borrado ya
        // los eliminaba, pero no estaban declarados ni se exportaban.
        $collection->add_database_table(
            'codelab_submission_results',
            [
                'status'         => 'privacy:metadata:codelab_submission_results:status',
                'actual_output'  => 'privacy:metadata:codelab_submission_results:actual_output',
                'stderr'         => 'privacy:metadata:codelab_submission_results:stderr',
                'compile_output' => 'privacy:metadata:codelab_submission_results:compile_output',
                'execution_time' => 'privacy:metadata:codelab_submission_results:execution_time',
                'memory_used'    => 'privacy:metadata:codelab_submission_results:memory_used',
                'timecreated'    => 'privacy:metadata:codelab_submission_results:timecreated',
            ],
            'privacy:metadata:codelab_submission_results'
        );

        $collection->add_external_location_link(
            'judge0_api',
            [
                'code'  => 'privacy:metadata:judge0_api:code',
                'stdin' => 'privacy:metadata:judge0_api:stdin',
            ],
            'privacy:metadata:judge0_api'
        );

        // El código y el enunciado se envían al proveedor de IA solo si el docente
        // activa las recomendaciones en la actividad.
        $collection->add_external_location_link(
            'ai_api',
            [
                'code'      => 'privacy:metadata:ai_api:code',
                'statement' => 'privacy:metadata:ai_api:statement',
            ],
            'privacy:metadata:ai_api'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'codelab'
                  JOIN {codelab_submissions} s ON s.codelabid = cm.instance
                 WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT s.userid
                  FROM {codelab_submissions} s
                  JOIN {course_modules} cm ON cm.instance = s.codelabid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'codelab'
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('codelab', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Orden explícito: dos exportaciones de los mismos datos deben producir el mismo
            // documento. Es una evidencia de cumplimiento, no un listado cualquiera.
            $submissions = $DB->get_records('codelab_submissions', [
                'codelabid' => $cm->instance,
                'userid'    => $userid,
            ], \mod_codelab\grader::ORDER_ATTEMPTS_ASC);

            if (!empty($submissions)) {
                // El derecho de acceso de la Ley 1581 alcanza a todo lo almacenado: sin esto el
                // estudiante recibía su código y su nota, pero no las salidas que produjo su
                // programa, que sí siguen guardadas en codelab_submission_results.
                foreach ($submissions as $submission) {
                    $submission->results = array_values($DB->get_records(
                        'codelab_submission_results',
                        ['submission_id' => $submission->id],
                        'id ASC'
                    ));
                }

                writer::with_context($context)->export_data(
                    [get_string('privacy:path:submissions', 'mod_codelab')],
                    (object)['submissions' => array_values($submissions)]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('codelab', $context->instanceid);
        if (!$cm) {
            return;
        }

        $submission_ids = $DB->get_fieldset_select(
            'codelab_submissions', 'id', 'codelabid = ?', [$cm->instance]
        );
        if ($submission_ids) {
            $DB->delete_records_list('codelab_submission_results', 'submission_id', $submission_ids);
        }
        $DB->delete_records('codelab_submissions', ['codelabid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('codelab', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $submission_ids = $DB->get_fieldset_select(
                'codelab_submissions', 'id',
                'codelabid = ? AND userid = ?',
                [$cm->instance, $userid]
            );
            if ($submission_ids) {
                $DB->delete_records_list('codelab_submission_results', 'submission_id', $submission_ids);
            }
            $DB->delete_records('codelab_submissions', ['codelabid' => $cm->instance, 'userid' => $userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('codelab', $context->instanceid);
        if (!$cm) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $submission_ids = $DB->get_fieldset_select(
                'codelab_submissions', 'id',
                'codelabid = ? AND userid = ?',
                [$cm->instance, $userid]
            );
            if ($submission_ids) {
                $DB->delete_records_list('codelab_submission_results', 'submission_id', $submission_ids);
            }
            $DB->delete_records('codelab_submissions', ['codelabid' => $cm->instance, 'userid' => $userid]);
        }
    }
}
