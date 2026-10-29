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
 * Lista todas las instancias de codelab en un curso.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/codelab/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(context_course::instance($course->id));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_codelab'));

if (!$codelabs = get_all_instances_in_course('codelab', $course)) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_codelab')),
           "$CFG->wwwroot/course/view.php?id=$course->id");
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$header = [
    get_string('name'),
    get_string('languages', 'mod_codelab'),
    get_string('duedate', 'mod_codelab'),
];
$table->head  = $header;
$table->align = ['left', 'center', 'center'];

foreach ($codelabs as $codelab) {
    $link = html_writer::link(
        new moodle_url('/mod/codelab/view.php', ['id' => $codelab->coursemodule]),
        format_string($codelab->name)
    );

    $langs = !empty($codelab->languages_allowed)
        ? implode(', ', array_map('strtoupper', json_decode($codelab->languages_allowed, true) ?? []))
        : strtoupper($codelab->default_language ?? 'python');

    $due = !empty($codelab->duedate) ? userdate($codelab->duedate) : '-';

    $table->data[] = [$link, $langs, $due];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
