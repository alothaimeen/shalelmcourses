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

namespace local_shomokh_admissions\task;

/**
 * Processes retryable enrolment operations.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class process_enrolments extends \core\task\scheduled_task {
    /**
     * Returns the translated task name.
     *
     * @return string Task name.
     */
    public function get_name(): string {
        return get_string('task:processenrolments', 'local_shomokh_admissions');
    }

    /**
     * Executes one bounded batch.
     */
    public function execute(): void {
        if (empty(get_config('local_shomokh_admissions', 'enabled'))) {
            return;
        }
        $count = \local_shomokh_admissions\enrolment_service::process_due(50);
        mtrace('Shomokh admissions enrolment operations attempted: ' . $count);
    }
}
