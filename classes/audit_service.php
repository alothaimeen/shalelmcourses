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

namespace local_shomokh_admissions;

/**
 * Writes compact, non-sensitive workflow audit entries.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audit_service {
    /**
     * Records an action.
     *
     * @param int|null $applicationid Application ID.
     * @param int|null $actorid Actor user ID, null for system actions.
     * @param string $action Stable action code.
     * @param string|null $oldstatus Previous status.
     * @param string|null $newstatus New status.
     * @param string|null $detailcode Non-sensitive detail code.
     * @return void
     */
    public static function record(
        ?int $applicationid,
        ?int $actorid,
        string $action,
        ?string $oldstatus = null,
        ?string $newstatus = null,
        ?string $detailcode = null
    ): void {
        global $DB;
        $DB->insert_record('local_shadm_audit', (object)[
            'applicationid' => $applicationid,
            'actorid' => $actorid,
            'action' => clean_param($action, PARAM_ALPHANUMEXT),
            'oldstatus' => $oldstatus,
            'newstatus' => $newstatus,
            'detailcode' => $detailcode ? clean_param($detailcode, PARAM_ALPHANUMEXT) : null,
            'timecreated' => time(),
        ]);
    }
}
