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
 * Program persistence helpers.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shomokh_admissions;

/**
 * Persistence helpers for configured admission programs.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class program_repository {
    /** @var string Foundation program type. */
    public const TYPE_FOUNDATION = 'foundation';

    /** @var string Specialist program type. */
    public const TYPE_SPECIALIST = 'specialist';

    /**
     * Returns a program or throws a Moodle record exception.
     *
     * @param int $id Program ID.
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_shadm_program', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Returns all programs in display order.
     *
     * @param bool $enabledonly Whether to return enabled programs only.
     * @return array
     */
    public static function get_all(bool $enabledonly = false): array {
        global $DB;
        $conditions = $enabledonly ? ['enabled' => 1] : [];
        return $DB->get_records('local_shadm_program', $conditions, 'sortorder ASC, id ASC');
    }

    /**
     * Returns enabled and open programs by type.
     *
     * @param string $type Program type.
     * @return array
     */
    public static function get_open_by_type(string $type): array {
        global $DB;
        return $DB->get_records('local_shadm_program', [
            'programtype' => $type,
            'enabled' => 1,
            'registrationopen' => 1,
        ], 'sortorder ASC, id ASC');
    }

    /**
     * Gets the configured default foundation fallback.
     *
     * @return \stdClass|null
     */
    public static function get_default_fallback(): ?\stdClass {
        global $DB;
        $program = $DB->get_record('local_shadm_program', [
            'programtype' => self::TYPE_FOUNDATION,
            'defaultfallback' => 1,
            'enabled' => 1,
        ], '*', IGNORE_MULTIPLE);
        return $program ?: null;
    }

    /**
     * Returns course mappings with course names.
     *
     * @param int $programid Program ID.
     * @return array
     */
    public static function get_courses(int $programid): array {
        global $DB;
        $sql = "SELECT pc.*, c.fullname, c.shortname, c.visible, c.enablecompletion
                  FROM {local_shadm_progcourse} pc
                  JOIN {course} c ON c.id = pc.courseid
                 WHERE pc.programid = :programid
              ORDER BY pc.sortorder ASC, c.fullname ASC";
        return $DB->get_records_sql($sql, ['programid' => $programid]);
    }

    /**
     * Returns all Moodle courses that are not linked to the program.
     *
     * @param int $programid Program ID.
     * @return array Available course records keyed by course ID.
     */
    public static function get_available_courses(int $programid): array {
        global $DB;
        self::get($programid);
        $sql = "SELECT c.id, c.fullname, c.shortname, cc.name AS categoryname
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id <> :siteid
                   AND NOT EXISTS (
                        SELECT 1
                          FROM {local_shadm_progcourse} pc
                         WHERE pc.programid = :programid
                           AND pc.courseid = c.id
                   )
              ORDER BY cc.sortorder ASC, c.sortorder ASC, c.fullname ASC";
        return $DB->get_records_sql($sql, [
            'siteid' => SITEID,
            'programid' => $programid,
        ]);
    }

    /**
     * Returns numeric grade items that can represent completion of a full level.
     *
     * Only calculated items are included so a single course total, assignment or quiz
     * cannot accidentally qualify a student for a specialist pathway.
     *
     * @return array Grade item records keyed by grade item ID.
     */
    public static function get_level_completion_grade_items(): array {
        global $DB;

        $sql = "SELECT gi.id, gi.courseid, gi.itemname, gi.itemtype, gi.grademin, gi.grademax,
                       c.fullname AS coursename, c.shortname, cc.name AS categoryname
                  FROM {grade_items} gi
                  JOIN {course} c ON c.id = gi.courseid
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id <> :siteid
                   AND gi.gradetype = :gradetype
                   AND gi.calculation IS NOT NULL
              ORDER BY cc.sortorder ASC, c.sortorder ASC, gi.sortorder ASC, gi.id ASC";
        return $DB->get_records_sql($sql, [
            'siteid' => SITEID,
            'gradetype' => 1,
        ]);
    }

    /**
     * Saves editable program fields.
     *
     * @param \stdClass $data Validated form data.
     * @return \stdClass
     */
    public static function save(\stdClass $data): \stdClass {
        global $DB;

        $program = self::get((int)$data->id);
        $transaction = $DB->start_delegated_transaction();
        if (!empty($data->defaultfallback)) {
            $DB->set_field('local_shadm_program', 'defaultfallback', 0, [
                'programtype' => self::TYPE_FOUNDATION,
            ]);
        }

        $program->name = trim($data->name);
        $program->description = trim((string)($data->description ?? '')) ?: null;
        $program->requirements = trim((string)($data->requirements ?? '')) ?: null;
        // Program type and pathway are installation invariants, not editable form data.
        $program->batchname = trim((string)($data->batchname ?? '')) ?: null;
        if ($program->programtype === self::TYPE_FOUNDATION) {
            $program->eligibilitygradeitemid = !empty($data->eligibilitygradeitemid)
                ? (int)$data->eligibilitygradeitemid
                : null;
            $program->eligibilitymingrade = isset($data->eligibilitymingrade)
                ? (float)$data->eligibilitymingrade
                : 1;
        } else {
            $program->eligibilitygradeitemid = null;
            $program->eligibilitymingrade = 1;
        }
        $program->cohortid = !empty($data->cohortid) ? (int)$data->cohortid : null;
        $program->telegramurl = trim((string)($data->telegramurl ?? '')) ?: null;
        $program->enabled = empty($data->enabled) ? 0 : 1;
        $program->registrationopen = empty($data->registrationopen) ? 0 : 1;
        $program->recognizeexisting = empty($data->recognizeexisting) ? 0 : 1;
        $program->defaultfallback = (
            $program->programtype === self::TYPE_FOUNDATION && !empty($data->defaultfallback)
        ) ? 1 : 0;
        $program->timemodified = time();
        $DB->update_record('local_shadm_program', $program);
        self::assert_site_ready_if_live();
        $transaction->allow_commit();
        return self::get((int)$program->id);
    }

    /**
     * Links a course to a program without changing the Moodle course.
     *
     * @param int $programid Program ID.
     * @param int $courseid Course ID.
     * @return void
     */
    public static function add_course(int $programid, int $courseid): void {
        global $DB;
        self::get($programid);
        $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);
        if (
            $DB->record_exists('local_shadm_progcourse', [
            'programid' => $programid,
            'courseid' => $courseid,
            ])
        ) {
            return;
        }
        $maxsort = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {local_shadm_progcourse} WHERE programid = :programid',
            ['programid' => $programid]
        );
        $DB->insert_record('local_shadm_progcourse', (object)[
            'programid' => $programid,
            'courseid' => $courseid,
            'eligibilityrequired' => 0,
            'sortorder' => $maxsort + 10,
        ]);
    }

    /**
     * Removes a course mapping only.
     *
     * @param int $programid Program ID.
     * @param int $courseid Course ID.
     * @return void
     */
    public static function remove_course(int $programid, int $courseid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_shadm_progcourse', [
            'programid' => $programid,
            'courseid' => $courseid,
        ]);
        self::assert_site_ready_if_live();
        $transaction->allow_commit();
    }

    /**
     * Prevents a live configuration from being saved in an unsafe state.
     */
    private static function assert_site_ready_if_live(): void {
        if (
            !empty(get_config('local_shomokh_admissions', 'enabled'))
                && readiness_service::check_global()
        ) {
            throw new \moodle_exception('cannotenable', 'local_shomokh_admissions');
        }
    }
}
