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
 * Persistence for independent specialist eligibility groups.
 *
 * A student is eligible when every condition in any one enabled group passes.
 * Keeping these rules separate from admission destinations prevents a current
 * foundation batch from becoming specialist-eligible merely because it is a
 * recognised enrolment destination.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligibility_repository {
    /** Required legacy graduation groups. */
    public const LEGACY_BATCH_1 = 'foundation_graduates_b1';
    /** Required legacy graduation groups. */
    public const LEGACY_BATCH_2 = 'foundation_graduates_b2';

    /**
     * Returns eligibility groups in display order.
     *
     * @param bool $enabledonly Return only enabled groups.
     * @return array
     */
    public static function get_groups(bool $enabledonly = false): array {
        global $DB;

        $conditions = $enabledonly ? ['enabled' => 1] : [];
        return $DB->get_records('local_shadm_eliggroup', $conditions, 'sortorder ASC, id ASC');
    }

    /**
     * Returns one group.
     *
     * @param int $groupid Group ID.
     * @return \stdClass
     */
    public static function get_group(int $groupid): \stdClass {
        global $DB;

        return $DB->get_record('local_shadm_eliggroup', ['id' => $groupid], '*', MUST_EXIST);
    }

    /**
     * Returns one group by stable code.
     *
     * @param string $code Group code.
     * @return \stdClass|null
     */
    public static function get_group_by_code(string $code): ?\stdClass {
        global $DB;

        $group = $DB->get_record('local_shadm_eliggroup', ['code' => $code]);
        return $group ?: null;
    }

    /**
     * Returns a group's conditions with readable Moodle grade metadata.
     *
     * @param int $groupid Group ID.
     * @return array
     */
    public static function get_items(int $groupid): array {
        global $DB;

        $sql = "SELECT ei.*, gi.itemname, gi.gradetype, gi.grademin, gi.grademax, gi.calculation,
                       c.fullname AS coursename, c.shortname, cc.name AS categoryname
                  FROM {local_shadm_eligitem} ei
             LEFT JOIN {grade_items} gi ON gi.id = ei.gradeitemid
             LEFT JOIN {course} c ON c.id = gi.courseid
             LEFT JOIN {course_categories} cc ON cc.id = c.category
                 WHERE ei.groupid = :groupid
              ORDER BY ei.sortorder ASC, ei.id ASC";
        return $DB->get_records_sql($sql, ['groupid' => $groupid]);
    }

    /**
     * Creates a disabled group so incomplete rules can never grant admission.
     *
     * @param string $name Staff-facing group name.
     * @return \stdClass
     */
    public static function create_group(string $name): \stdClass {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            throw new \invalid_parameter_exception('Eligibility group name is required.');
        }
        $now = time();
        $sortorder = (int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {local_shadm_eliggroup}');
        $id = $DB->insert_record('local_shadm_eliggroup', (object)[
            'code' => 'custom_' . strtolower(random_string(16)),
            'name' => $name,
            'enabled' => 0,
            'sortorder' => $sortorder + 10,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return self::get_group((int)$id);
    }

    /**
     * Enables or disables a complete eligibility group.
     *
     * @param int $groupid Group ID.
     * @param bool $enabled New state.
     * @return void
     */
    public static function set_group_enabled(int $groupid, bool $enabled): void {
        global $DB;

        self::get_group($groupid);
        if ($enabled && eligibility_service::group_errors(self::get_group($groupid))) {
            throw new \moodle_exception('eligibility:invalidgroup', 'local_shomokh_admissions');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('local_shadm_eliggroup', 'enabled', $enabled ? 1 : 0, ['id' => $groupid]);
        $DB->set_field('local_shadm_eliggroup', 'timemodified', time(), ['id' => $groupid]);
        program_repository::assert_site_ready_if_live();
        $transaction->allow_commit();
    }

    /**
     * Adds one calculated grade condition.
     *
     * @param int $groupid Group ID.
     * @param int $gradeitemid Moodle grade item ID.
     * @param float $mingrade Minimum passing value.
     * @return void
     */
    public static function add_item(int $groupid, int $gradeitemid, float $mingrade): void {
        global $DB;

        self::get_group($groupid);
        $item = $DB->get_record('grade_items', ['id' => $gradeitemid]);
        if (!$item || (int)$item->gradetype !== 1 || $item->calculation === null) {
            throw new \moodle_exception('readiness:missinggradeitem', 'local_shomokh_admissions');
        }
        if (
            $mingrade < (float)$item->grademin
            || $mingrade > (float)$item->grademax
        ) {
            throw new \moodle_exception('readiness:invalidthreshold', 'local_shomokh_admissions');
        }
        if (
            $DB->record_exists('local_shadm_eligitem', [
                'groupid' => $groupid,
                'gradeitemid' => $gradeitemid,
            ])
        ) {
            return;
        }
        $now = time();
        $sortorder = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {local_shadm_eligitem} WHERE groupid = :groupid',
            ['groupid' => $groupid]
        );
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('local_shadm_eligitem', (object)[
            'groupid' => $groupid,
            'gradeitemid' => $gradeitemid,
            'mingrade' => $mingrade,
            'sortorder' => $sortorder + 10,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        program_repository::assert_site_ready_if_live();
        $transaction->allow_commit();
    }

    /**
     * Removes one condition and disables its group to fail closed.
     *
     * @param int $itemid Eligibility item ID.
     * @return void
     */
    public static function remove_item(int $itemid): void {
        global $DB;

        $item = $DB->get_record('local_shadm_eligitem', ['id' => $itemid], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_shadm_eligitem', ['id' => $itemid]);
        $DB->set_field('local_shadm_eliggroup', 'enabled', 0, ['id' => $item->groupid]);
        $DB->set_field('local_shadm_eliggroup', 'timemodified', time(), ['id' => $item->groupid]);
        program_repository::assert_site_ready_if_live();
        $transaction->allow_commit();
    }
}
