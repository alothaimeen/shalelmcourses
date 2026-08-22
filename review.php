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
 * Reviews one external qualification application.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$applicationid = optional_param('id', 0, PARAM_INT);
if (!$applicationid) {
    $applicationid = required_param('applicationid', PARAM_INT);
}
require_login();
$context = context_system::instance();
require_capability('local/shomokh_admissions:review', $context);

$application = \local_shomokh_admissions\application_service::get($applicationid);
$student = $DB->get_record('user', ['id' => $application->userid], '*', MUST_EXIST);
$program = \local_shomokh_admissions\program_repository::get((int)$application->targetprogramid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/review.php', ['id' => $applicationid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('reviewtitle', 'local_shomokh_admissions', fullname($student)));
$PAGE->set_heading(get_string('reviewtitle', 'local_shomokh_admissions', fullname($student)));
$form = new \local_shomokh_admissions\form\review_form(
    new moodle_url('/local/shomokh_admissions/review.php', ['id' => $applicationid])
);
$form->set_data((object)[
    'applicationid' => $applicationid,
    'recordversion' => $application->recordversion,
]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/shomokh_admissions/manage.php'));
}
if ($data = $form->get_data()) {
    \local_shomokh_admissions\application_service::review(
        $applicationid,
        $data->decision,
        $data->decisionnote ?? '',
        $data->studentmessage ?? '',
        (int)$USER->id,
        (int)$data->recordversion
    );
    redirect(
        new moodle_url('/local/shomokh_admissions/manage.php'),
        get_string('reviewed', 'local_shomokh_admissions'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
echo html_writer::tag('p', html_writer::tag('strong', get_string('program', 'local_shomokh_admissions') . ': ')
    . format_string($program->name));
echo html_writer::tag('p', html_writer::tag('strong', get_string('submittedat', 'local_shomokh_admissions') . ': ')
    . userdate($application->timecreated));
$files = get_file_storage()->get_area_files(
    $context->id,
    'local_shomokh_admissions',
    'certificate',
    $applicationid,
    'filename ASC',
    false
);
if ($files) {
    $file = reset($files);
    $fileurl = moodle_url::make_pluginfile_url(
        $context->id,
        'local_shomokh_admissions',
        'certificate',
        $applicationid,
        $file->get_filepath(),
        $file->get_filename(),
        true
    );
    echo html_writer::link($fileurl, get_string('reviewcertificate', 'local_shomokh_admissions'), [
        'class' => 'btn btn-secondary mb-3',
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
    ]);
} else {
    echo $OUTPUT->notification(get_string('certificateunavailable', 'local_shomokh_admissions'), 'warning');
}
$form->display();
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
