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
 * Dashboard card renderer.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shomokh_admissions;

/**
 * Renders one lightweight dashboard card for students or admissions staff.
 *
 * @package local_shomokh_admissions
 */
final class dashboard_card {
    /**
     * Renders the relevant card.
     *
     * @return string
     */
    public static function render(): string {
        global $DB, $USER;
        if (!isloggedin() || isguestuser()) {
            return '';
        }
        $context = \context_system::instance();
        if (has_capability('local/shomokh_admissions:viewreports', $context)) {
            $pending = $DB->count_records('local_shadm_application', [
                'status' => application_service::STATUS_PENDING,
            ]);
            $attention = $DB->count_records('local_shadm_application', [
                'status' => application_service::STATUS_NEEDS_ATTENTION,
            ]);
            if (!empty(get_config('local_shomokh_admissions', 'enabled'))) {
                $state = get_string('ready', 'local_shomokh_admissions');
            } else if (!empty(get_config('local_shomokh_admissions', 'publicpreview'))) {
                $state = get_string('state:publicpreview', 'local_shomokh_admissions');
            } else {
                $state = get_string('disabled', 'local_shomokh_admissions');
            }
            $content = \html_writer::tag('h2', get_string('admindashboard', 'local_shomokh_admissions'));
            $content .= \html_writer::tag('p', s($state));
            $content .= \html_writer::tag('p', get_string('dashboardpending', 'local_shomokh_admissions')
                . ': ' . $pending . ' — ' . get_string('dashboardfailed', 'local_shomokh_admissions') . ': ' . $attention);
            $content .= \html_writer::link(
                new \moodle_url('/local/shomokh_admissions/manage.php'),
                get_string('admindashboard', 'local_shomokh_admissions'),
                ['class' => 'btn btn-primary']
            );
            return \html_writer::tag('section', $content, [
                'class' => 'local-shadm-card',
                'aria-label' => get_string('admindashboard', 'local_shomokh_admissions'),
            ]);
        }
        $enabled = !empty(get_config('local_shomokh_admissions', 'enabled'));
        $publicpreview = !empty(get_config('local_shomokh_admissions', 'publicpreview'));
        if (!$enabled && !$publicpreview) {
            return '';
        }

        if (!$enabled) {
            $intro = get_config('local_shomokh_admissions', 'introhtml')
                ?: get_string('welcomedefault', 'local_shomokh_admissions');
            $content = \html_writer::tag('h2', get_string('admission', 'local_shomokh_admissions'));
            $content .= \html_writer::tag('p', s($intro));
            $content .= \html_writer::tag('h3', get_string('previewclosedtitle', 'local_shomokh_admissions'));
            $content .= \html_writer::tag('p', get_string('previewclosedmessage', 'local_shomokh_admissions'));
            $content .= \html_writer::link(
                new \moodle_url('/local/shomokh_admissions/index.php'),
                get_string('previewdetails', 'local_shomokh_admissions'),
                ['class' => 'btn btn-secondary']
            );
            return \html_writer::tag('section', $content, [
                'class' => 'local-shadm-card',
                'aria-label' => get_string('admission', 'local_shomokh_admissions'),
            ]);
        }

        recognition_service::recognise_user((int)$USER->id);
        $applications = application_service::get_for_user((int)$USER->id);
        $content = \html_writer::tag('h2', get_string('admission', 'local_shomokh_admissions'));
        if (!$applications) {
            $intro = get_config('local_shomokh_admissions', 'introhtml')
                ?: get_string('welcomedefault', 'local_shomokh_admissions');
            $content .= \html_writer::tag('p', s($intro));
            $content .= \html_writer::link(
                new \moodle_url('/local/shomokh_admissions/index.php'),
                get_string('startapplication', 'local_shomokh_admissions'),
                ['class' => 'btn btn-primary']
            );
        } else {
            $items = [];
            foreach ($applications as $application) {
                $label = format_string($application->enrolledname ?: $application->targetname);
                $status = application_service::status_label($application->status);
                $items[] = \html_writer::tag('li', \html_writer::tag('strong', $label) . ': ' . s($status));
            }
            $content .= \html_writer::tag('ul', implode('', $items), ['class' => 'local-shadm-status']);
            $content .= \html_writer::link(
                new \moodle_url('/local/shomokh_admissions/status.php'),
                get_string('viewstatus', 'local_shomokh_admissions'),
                ['class' => 'btn btn-primary']
            );
            $content .= ' ' . \html_writer::link(
                new \moodle_url('/local/shomokh_admissions/index.php'),
                get_string('viewoptions', 'local_shomokh_admissions'),
                ['class' => 'btn btn-secondary']
            );
        }
        return \html_writer::tag('section', $content, [
            'class' => 'local-shadm-card',
            'aria-label' => get_string('admission', 'local_shomokh_admissions'),
        ]);
    }
}
