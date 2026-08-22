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
 * General admissions settings page.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/shomokh_admissions:manageprograms', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/config.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('generalsettings', 'local_shomokh_admissions'));
$PAGE->set_heading(get_string('generalsettings', 'local_shomokh_admissions'));
$form = new \local_shomokh_admissions\form\config_form();
$form->set_data((object)[
    'publicpreview' => (int)get_config('local_shomokh_admissions', 'publicpreview'),
    'enabled' => (int)get_config('local_shomokh_admissions', 'enabled'),
    'introhtml' => get_config('local_shomokh_admissions', 'introhtml'),
    'maxcertificatebytes' => (int)get_config('local_shomokh_admissions', 'maxcertificatebytes'),
    'retentiondays' => (int)get_config('local_shomokh_admissions', 'retentiondays'),
    'retrylimit' => (int)get_config('local_shomokh_admissions', 'retrylimit'),
    'recognitionbatch' => (int)get_config('local_shomokh_admissions', 'recognitionbatch'),
]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/shomokh_admissions/manage.php'));
}
if ($data = $form->get_data()) {
    foreach (
        ['publicpreview', 'enabled', 'introhtml', 'maxcertificatebytes', 'retentiondays',
            'retrylimit', 'recognitionbatch'] as $key
    ) {
        set_config($key, $data->{$key}, 'local_shomokh_admissions');
    }
    redirect(
        new moodle_url('/local/shomokh_admissions/config.php'),
        get_string('configsaved', 'local_shomokh_admissions'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
$programs = \local_shomokh_admissions\program_repository::get_all();
$errors = \local_shomokh_admissions\readiness_service::check_all_programs();
echo html_writer::tag('h2', get_string('readinessreport', 'local_shomokh_admissions'));
if (!$errors) {
    echo $OUTPUT->notification(get_string('allprogramsready', 'local_shomokh_admissions'), 'success');
} else {
    $sections = '';
    foreach ($errors as $programid => $programerrors) {
        $programname = isset($programs[$programid])
            ? format_string($programs[$programid]->name)
            : get_string('program', 'local_shomokh_admissions');
        $items = array_map(static function (string $error): string {
            return html_writer::tag('li', s($error));
        }, $programerrors);
        $sections .= html_writer::tag('h3', $programname);
        $sections .= html_writer::tag('ul', implode('', $items));
    }
    echo $OUTPUT->notification($sections, 'warning');
}
$form->display();
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
