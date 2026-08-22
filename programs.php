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
 * Lists admission programs and their readiness state.
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
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/programs.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('programs', 'local_shomokh_admissions'));
$PAGE->set_heading(get_string('programs', 'local_shomokh_admissions'));

echo $OUTPUT->header();
echo html_writer::start_tag('div', ['class' => 'local-shadm-grid']);
foreach (\local_shomokh_admissions\program_repository::get_all() as $program) {
    $errors = \local_shomokh_admissions\readiness_service::check_program($program);
    $courses = \local_shomokh_admissions\program_repository::get_courses((int)$program->id);
    $content = html_writer::tag('h2', format_string($program->name));
    $content .= html_writer::tag('p', s($program->code), ['class' => 'text-muted']);
    $content .= html_writer::tag('p', get_string('courses', 'local_shomokh_admissions') . ': ' . count($courses));
    $content .= html_writer::tag('p', get_string('programreadiness', 'local_shomokh_admissions') . ': '
        . get_string($errors ? 'notready' : 'ready', 'local_shomokh_admissions'), [
            'class' => $errors ? 'text-danger' : 'text-success',
        ]);
    if ($errors) {
        $items = array_map(static fn($error): string => html_writer::tag('li', s($error)), $errors);
        $content .= html_writer::tag('ul', implode('', $items));
    }
    $content .= html_writer::link(new moodle_url('/local/shomokh_admissions/program.php', [
        'id' => $program->id,
    ]), get_string('editprogram', 'local_shomokh_admissions'), ['class' => 'btn btn-primary']);
    echo html_writer::tag('article', $content, ['class' => 'local-shadm-option']);
}
echo html_writer::end_tag('div');
echo $OUTPUT->footer();
