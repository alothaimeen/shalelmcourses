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
 * Core callbacks for the Shomokh admissions plugin.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the student admissions entry to the primary navigation.
 *
 * @param global_navigation $navigation Moodle navigation.
 * @return void
 */
function local_shomokh_admissions_extend_navigation(global_navigation $navigation): void {
    if (
        !isloggedin() || isguestuser()
            || empty(get_config('local_shomokh_admissions', 'enabled'))
    ) {
        return;
    }
    $navigation->add(
        get_string('admission', 'local_shomokh_admissions'),
        new moodle_url('/local/shomokh_admissions/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_shomokh_admissions'
    );
}

/**
 * Serves private certificate files only to their owner or an authorised reviewer.
 *
 * @param stdClass $course Course record.
 * @param stdClass|null $cm Course module record.
 * @param context $context File context.
 * @param string $filearea File area.
 * @param array $args Path arguments.
 * @param bool $forcedownload Force download.
 * @param array $options File serving options.
 * @return bool|void
 */
function local_shomokh_admissions_pluginfile(
    $course,
    $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
) {
    global $DB, $USER;

    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'certificate') {
        return false;
    }
    require_login();
    $itemid = (int)array_shift($args);
    $application = $DB->get_record('local_shadm_application', ['id' => $itemid], 'id,userid');
    if (!$application) {
        return false;
    }
    $canreview = has_capability('local/shomokh_admissions:review', $context);
    if (!$canreview && (int)$application->userid !== (int)$USER->id) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'local_shomokh_admissions',
        'certificate',
        $itemid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options + ['filter' => 0]);
}
