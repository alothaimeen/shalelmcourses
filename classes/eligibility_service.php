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
 * Resolves internal foundation completion from independent graduation rules.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligibility_service {
    /**
     * Finds one fully completed configured foundation diploma group.
     *
     * @param int $userid User ID.
     * Each enabled group is an alternative (OR); every grade item inside a
     * group is required (AND). This models, for example, completion of all
     * final results for either batch one or batch two without granting access
     * to a currently active third batch.
     *
     * @return \stdClass|null Matched eligibility group.
     */
    public static function completed_foundation(int $userid): ?\stdClass {
        global $DB;

        foreach (eligibility_repository::get_groups(true) as $group) {
            $items = eligibility_repository::get_items((int)$group->id);
            if (!$items || self::group_errors($group, $items)) {
                continue;
            }

            $passed = true;
            foreach ($items as $item) {
                $finalgrade = $DB->get_field('grade_grades', 'finalgrade', [
                    'itemid' => (int)$item->gradeitemid,
                    'userid' => $userid,
                ]);
                if (
                    $finalgrade === false || $finalgrade === null
                    || (float)$finalgrade < (float)$item->mingrade
                ) {
                    $passed = false;
                    break;
                }
            }
            if ($passed) {
                return $group;
            }
        }
        return null;
    }

    /**
     * Returns configuration errors for one group.
     *
     * @param \stdClass $group Eligibility group.
     * @param array|null $items Optional preloaded items.
     * @return array
     */
    public static function group_errors(\stdClass $group, ?array $items = null): array {
        $items = $items ?? eligibility_repository::get_items((int)$group->id);
        if (!$items) {
            return [get_string('eligibility:emptygroup', 'local_shomokh_admissions')];
        }

        $errors = [];
        foreach ($items as $item) {
            if ((int)$item->gradetype !== 1 || $item->calculation === null) {
                $errors[] = get_string('readiness:missinggradeitem', 'local_shomokh_admissions');
                continue;
            }
            if ((float)$item->mingrade < (float)$item->grademin || (float)$item->mingrade > (float)$item->grademax) {
                $errors[] = get_string('readiness:invalidthreshold', 'local_shomokh_admissions');
            }
        }
        return array_values(array_unique($errors));
    }

    /**
     * Rechecks pending external applications after internal results change.
     *
     * @param int $limit Maximum records inspected per run.
     * @return int Number converted to internal eligibility.
     */
    public static function reassess_pending(int $limit = 100): int {
        global $DB;

        $sql = "SELECT a.id
                  FROM {local_shadm_application} a
                  JOIN {local_shadm_program} p ON p.id = a.targetprogramid
                 WHERE a.status = :status
                   AND p.programtype = :programtype
              ORDER BY a.id ASC";
        $applications = $DB->get_records_sql($sql, [
            'status' => application_service::STATUS_PENDING,
            'programtype' => program_repository::TYPE_SPECIALIST,
        ], 0, max(1, $limit));
        $converted = 0;
        foreach ($applications as $application) {
            if (application_service::approve_internal_if_eligible((int)$application->id)) {
                $converted++;
            }
        }
        return $converted;
    }
}
