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
 * Displays the current student's admission status.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/status.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('applicationstatus', 'local_shomokh_admissions'));
$PAGE->set_heading(get_string('applicationstatus', 'local_shomokh_admissions'));
\local_shomokh_admissions\recognition_service::recognise_user((int)$USER->id);
$applications = \local_shomokh_admissions\application_service::get_for_user((int)$USER->id);
$offer = null;
foreach ($applications as $application) {
    if ($application->status === \local_shomokh_admissions\application_service::STATUS_REJECTED_OFFER) {
        $offer = $application;
        break;
    }
}
$fallbackform = null;
if ($offer) {
    $fallbackform = new \local_shomokh_admissions\form\fallback_form();
    $fallbackform->set_data(['applicationid' => $offer->id]);
    if ($data = $fallbackform->get_data()) {
        $accepted = !empty($data->accept);
        \local_shomokh_admissions\application_service::respond_to_fallback(
            (int)$offer->id,
            (int)$USER->id,
            $accepted
        );
        redirect(new moodle_url('/local/shomokh_admissions/status.php'), get_string(
            $accepted ? 'fallbackaccepted' : 'fallbackdeclined',
            'local_shomokh_admissions'
        ), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
echo html_writer::tag('p', get_string('statusintro', 'local_shomokh_admissions'));
if (!$applications) {
    echo $OUTPUT->notification(get_string('noapplications', 'local_shomokh_admissions'), 'info');
    echo html_writer::link(
        new moodle_url('/local/shomokh_admissions/index.php'),
        get_string('startapplication', 'local_shomokh_admissions'),
        ['class' => 'btn btn-primary']
    );
} else {
    foreach ($applications as $application) {
        $programname = $application->enrolledname ?: $application->targetname;
        $content = html_writer::tag('h2', format_string($programname));
        $content .= html_writer::tag('p', s(\local_shomokh_admissions\application_service::status_label(
            $application->status
        )), ['class' => 'lead']);
        if (!empty($application->studentmessage)) {
            $content .= html_writer::tag('p', s($application->studentmessage));
        }
        if (
            in_array($application->status, [
            \local_shomokh_admissions\application_service::STATUS_APPROVED,
            \local_shomokh_admissions\application_service::STATUS_FALLBACK_ACCEPTED,
            ], true)
        ) {
            $content .= html_writer::tag('p', get_string('preparingcourses', 'local_shomokh_admissions'));
        }
        if (
            in_array($application->status, [
            \local_shomokh_admissions\application_service::STATUS_ENROLLED,
            \local_shomokh_admissions\application_service::STATUS_RECOGNISED,
            ], true)
        ) {
            $content .= html_writer::start_tag('div', ['class' => 'local-shadm-actions']);
            $content .= html_writer::link(
                new moodle_url('/my/courses.php'),
                get_string('mycourses', 'local_shomokh_admissions'),
                ['class' => 'btn btn-primary']
            );
            if (
                !empty($application->telegramurl)
                    && \local_shomokh_admissions\readiness_service::valid_telegram_url($application->telegramurl)
            ) {
                $content .= html_writer::link(
                    $application->telegramurl,
                    get_string('telegramchannel', 'local_shomokh_admissions'),
                    [
                        'class' => 'btn btn-success',
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ]
                );
                $content .= html_writer::tag('small', get_string('telegramnotice', 'local_shomokh_admissions'), [
                    'class' => 'text-muted d-block',
                ]);
            }
            $content .= html_writer::end_tag('div');
        }
        if ($offer && (int)$offer->id === (int)$application->id) {
            $content .= html_writer::tag('h3', get_string('fallbackofferheading', 'local_shomokh_admissions'));
            $content .= html_writer::tag('p', get_string('fallbackoffertext', 'local_shomokh_admissions'));
            ob_start();
            $fallbackform->display();
            $content .= ob_get_clean();
        }
        echo html_writer::tag('article', $content, ['class' => 'local-shadm-option mb-3']);
    }
}
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
