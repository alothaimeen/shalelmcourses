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
 * Handles a student admission application.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

$programid = required_param('programid', PARAM_INT);
require_login();
$context = context_system::instance();
$program = \local_shomokh_admissions\program_repository::get($programid);
if (
    empty(get_config('local_shomokh_admissions', 'enabled'))
        || empty($program->enabled) || empty($program->registrationopen)
) {
    throw new moodle_exception('error:programunavailable', 'local_shomokh_admissions');
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/apply.php', ['programid' => $programid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('applicationtitle', 'local_shomokh_admissions'));
$PAGE->set_heading(format_string($program->name));

$existing = $DB->get_record('local_shadm_application', [
    'userid' => $USER->id,
    'targetprogramid' => $programid,
]);
if ($existing) {
    redirect(
        new moodle_url('/local/shomokh_admissions/status.php'),
        get_string('applicationduplicate', 'local_shomokh_admissions'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$external = $program->programtype === \local_shomokh_admissions\program_repository::TYPE_SPECIALIST
    && !\local_shomokh_admissions\eligibility_service::completed_foundation((int)$USER->id);
$maxbytes = max(1024, (int)get_config('local_shomokh_admissions', 'maxcertificatebytes'));
$fileoptions = [
    'accepted_types' => ['.pdf', '.jpg', '.jpeg', '.png'],
    'maxbytes' => $maxbytes,
    'maxfiles' => 1,
    'subdirs' => 0,
];
$draftitemid = file_get_submitted_draft_itemid('certificate');
$form = new \local_shomokh_admissions\form\application_form(null, [
    'program' => $program,
    'external' => $external,
    'fileoptions' => $fileoptions,
]);
$form->set_data((object)[
    'programid' => $programid,
    'external' => $external ? 1 : 0,
    'certificate' => $draftitemid,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/shomokh_admissions/index.php'));
}
if ($data = $form->get_data()) {
    $application = \local_shomokh_admissions\application_service::submit(
        (int)$USER->id,
        $programid,
        $external,
        $external ? (int)$data->certificate : null
    );
    redirect(
        new moodle_url('/local/shomokh_admissions/status.php'),
        get_string('applicationreceived', 'local_shomokh_admissions'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
$form->display();
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
