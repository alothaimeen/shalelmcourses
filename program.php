<?php
// This file is part of Moodle - https://moodle.org/.
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Program configuration and course linking page.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$programid = required_param('id', PARAM_INT);
require_login();
$context = context_system::instance();
require_capability('local/shomokh_admissions:manageprograms', $context);
$program = \local_shomokh_admissions\program_repository::get($programid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/program.php', ['id' => $programid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(format_string($program->name));
$PAGE->set_heading(format_string($program->name));

$action = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($action !== '') {
    require_sesskey();
    if ($action === 'remove' && $courseid) {
        \local_shomokh_admissions\program_repository::remove_course($programid, $courseid);
        redirect(
            new moodle_url('/local/shomokh_admissions/program.php', ['id' => $programid]),
            get_string('courseremoved', 'local_shomokh_admissions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if ($action === 'required' && $courseid) {
        \local_shomokh_admissions\program_repository::toggle_required($programid, $courseid);
        redirect(
            new moodle_url('/local/shomokh_admissions/program.php', ['id' => $programid]),
            get_string('courseupdated', 'local_shomokh_admissions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$programform = new \local_shomokh_admissions\form\program_form(null, ['program' => $program]);
$programform->set_data($program);
if ($programform->is_cancelled()) {
    redirect(new moodle_url('/local/shomokh_admissions/programs.php'));
}
if ($data = $programform->get_data()) {
    \local_shomokh_admissions\program_repository::save($data);
    redirect(
        new moodle_url('/local/shomokh_admissions/program.php', ['id' => $programid]),
        get_string('programsaved', 'local_shomokh_admissions'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$availablecourses = \local_shomokh_admissions\program_repository::get_available_courses($programid);
$courseoptions = [];
foreach ($availablecourses as $course) {
    $courseoptions[(int)$course->id] = format_string($course->fullname)
        . ' (' . s($course->shortname) . ') — ' . format_string($course->categoryname);
}
$courseform = null;
if ($courseoptions) {
    $courseform = new \local_shomokh_admissions\form\course_add_form(null, [
        'programid' => $programid,
        'options' => $courseoptions,
    ]);
    if ($data = $courseform->get_data()) {
        \local_shomokh_admissions\program_repository::add_course($programid, (int)$data->courseid);
        redirect(
            new moodle_url('/local/shomokh_admissions/program.php', ['id' => $programid]),
            get_string('courseadded', 'local_shomokh_admissions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
$programform->display();
echo html_writer::end_tag('section');

echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
echo html_writer::tag('h2', get_string('courses', 'local_shomokh_admissions'));
$courses = \local_shomokh_admissions\program_repository::get_courses($programid);
if (!$courses) {
    echo $OUTPUT->notification(get_string('nocourses', 'local_shomokh_admissions'), 'info');
} else {
    echo html_writer::start_tag('ul', ['class' => 'list-unstyled local-shadm-course-list']);
    foreach ($courses as $course) {
        $label = format_string($course->fullname) . ' (' . s($course->shortname) . ')';
        if (!empty($course->eligibilityrequired)) {
            $label .= ' — ' . get_string('eligibilityrequired', 'local_shomokh_admissions');
        }
        $actions = $OUTPUT->single_button(new moodle_url('/local/shomokh_admissions/program.php', [
            'id' => $programid,
            'action' => 'required',
            'courseid' => $course->courseid,
        ]), get_string('toggleeligibility', 'local_shomokh_admissions'), 'post', [
            'class' => 'btn btn-sm btn-secondary',
        ]);
        $actions .= $OUTPUT->single_button(new moodle_url('/local/shomokh_admissions/program.php', [
            'id' => $programid,
            'action' => 'remove',
            'courseid' => $course->courseid,
        ]), get_string('removecourse', 'local_shomokh_admissions'), 'post', [
            'class' => 'btn btn-sm btn-outline-danger',
        ]);
        echo html_writer::tag('li', html_writer::tag('span', $label)
            . html_writer::tag('span', $actions, ['class' => 'local-shadm-actions']));
    }
    echo html_writer::end_tag('ul');
}
if ($courseform) {
    echo html_writer::tag('h3', get_string('addcourse', 'local_shomokh_admissions'));
    echo html_writer::tag('p', get_string('filtercourseshelp', 'local_shomokh_admissions'));
    $courseform->display();
} else {
    echo $OUTPUT->notification(get_string('noavailablecourses', 'local_shomokh_admissions'), 'info');
}
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
