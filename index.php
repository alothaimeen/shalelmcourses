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
 * Student admissions landing page.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('admission', 'local_shomokh_admissions'));
$PAGE->set_heading(get_string('admission', 'local_shomokh_admissions'));

echo $OUTPUT->header();
$enabled = !empty(get_config('local_shomokh_admissions', 'enabled'));
$publicpreview = !empty(get_config('local_shomokh_admissions', 'publicpreview'));
if (!$enabled && !$publicpreview) {
    echo $OUTPUT->notification(get_string('disabled', 'local_shomokh_admissions'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

if (!$enabled) {
    $intro = get_config('local_shomokh_admissions', 'introhtml')
        ?: get_string('welcomedefault', 'local_shomokh_admissions');
    echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
    echo html_writer::tag('h2', get_string('welcomeheading', 'local_shomokh_admissions'));
    echo html_writer::tag('p', s($intro));
    echo $OUTPUT->notification(
        html_writer::tag('strong', get_string('previewclosedtitle', 'local_shomokh_admissions'))
            . html_writer::tag('p', get_string('previewclosedmessage', 'local_shomokh_admissions')),
        'info'
    );
    echo html_writer::end_tag('section');
    echo $OUTPUT->footer();
    exit;
}

\local_shomokh_admissions\recognition_service::recognise_user((int)$USER->id);
$existing = \local_shomokh_admissions\application_service::get_for_user((int)$USER->id);
$appliedprogramids = [];
$hasspecialistapplication = false;
if ($existing) {
    echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
    echo html_writer::tag('h2', get_string('applicationstatus', 'local_shomokh_admissions'));
    echo html_writer::start_tag('ul', ['class' => 'local-shadm-status']);
    foreach ($existing as $application) {
        $appliedprogramids[(int)$application->targetprogramid] = true;
        if ($application->programtype === \local_shomokh_admissions\program_repository::TYPE_SPECIALIST) {
            $hasspecialistapplication = true;
        }
        echo html_writer::tag('li', html_writer::tag('strong', format_string($application->targetname))
            . ': ' . s(\local_shomokh_admissions\application_service::status_label($application->status)));
    }
    echo html_writer::end_tag('ul');
    echo html_writer::link(
        new moodle_url('/local/shomokh_admissions/status.php'),
        get_string('viewstatus', 'local_shomokh_admissions'),
        ['class' => 'btn btn-primary']
    );
    echo html_writer::end_tag('section');
}

$intro = get_config('local_shomokh_admissions', 'introhtml')
    ?: get_string('welcomedefault', 'local_shomokh_admissions');
echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
echo html_writer::tag('h2', get_string('welcomeheading', 'local_shomokh_admissions'));
echo html_writer::tag('p', s($intro));
echo html_writer::tag('h3', get_string('chooseprogramtype', 'local_shomokh_admissions'));
echo html_writer::tag('p', get_string('readconditions', 'local_shomokh_admissions'));

$programs = \local_shomokh_admissions\program_repository::get_all(true);
$available = array_filter($programs, static function ($program) use (
    $appliedprogramids,
    $hasspecialistapplication
): bool {
    if (
        $hasspecialistapplication
            && $program->programtype === \local_shomokh_admissions\program_repository::TYPE_SPECIALIST
    ) {
        return false;
    }
    return !empty($program->registrationopen) && empty($appliedprogramids[(int)$program->id]);
});
if (!$available) {
    echo $OUTPUT->notification(get_string('notopen', 'local_shomokh_admissions'), 'info');
} else {
    echo html_writer::start_tag('div', ['class' => 'local-shadm-grid']);
    foreach ($available as $program) {
        $content = html_writer::tag('h3', format_string($program->name));
        if (!empty($program->description)) {
            $content .= html_writer::tag('div', format_text($program->description, FORMAT_MOODLE));
        }
        if (!empty($program->requirements)) {
            $content .= html_writer::tag('h4', get_string('requirements', 'local_shomokh_admissions'));
            $content .= html_writer::tag('div', format_text($program->requirements, FORMAT_MOODLE));
        }
        $content .= html_writer::link(
            new moodle_url('/local/shomokh_admissions/apply.php', ['programid' => $program->id]),
            get_string('selectprogram', 'local_shomokh_admissions'),
            ['class' => 'btn btn-primary stretched-link']
        );
        echo html_writer::tag('article', $content, ['class' => 'local-shadm-option position-relative']);
    }
    echo html_writer::end_tag('div');
}
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
