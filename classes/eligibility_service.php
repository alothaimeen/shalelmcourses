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
 * Resolves internal foundation completion from the configured level result.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligibility_service {
    /**
     * Finds one fully completed configured foundation level.
     *
     * @param int $userid User ID.
     * @return \stdClass|null Completed foundation program.
     */
    public static function completed_foundation(int $userid): ?\stdClass {
        global $DB;

        foreach (program_repository::get_all(true) as $program) {
            if (
                $program->programtype !== program_repository::TYPE_FOUNDATION
                || empty($program->eligibilitygradeitemid)
            ) {
                continue;
            }
            $item = $DB->get_record('grade_items', [
                'id' => (int)$program->eligibilitygradeitemid,
                'gradetype' => 1,
            ], 'id, calculation');
            if (!$item || $item->calculation === null) {
                continue;
            }
            $finalgrade = $DB->get_field('grade_grades', 'finalgrade', [
                'itemid' => (int)$program->eligibilitygradeitemid,
                'userid' => $userid,
            ]);
            if (
                $finalgrade !== false && $finalgrade !== null
                && (float)$finalgrade >= (float)$program->eligibilitymingrade
            ) {
                return $program;
            }
        }
        return null;
    }
}
