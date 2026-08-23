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
 * Readiness validation service.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shomokh_admissions;

/**
 * Validates configuration before student-facing activation.
 *
 * @package    local_shomokh_admissions
 */
final class readiness_service {
    /**
     * Returns user-facing readiness errors for one program.
     *
     * @param \stdClass $program Program record.
     * @return array Array of translated error strings.
     */
    public static function check_program(\stdClass $program): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');
        $errors = [];
        $courses = program_repository::get_courses((int)$program->id);
        if (!$courses) {
            $errors[] = get_string('readiness:nocourses', 'local_shomokh_admissions');
        }

        foreach ($courses as $course) {
            if (empty($course->visible)) {
                $errors[] = get_string('readiness:hiddencourse', 'local_shomokh_admissions', $course->fullname);
            }
            $instances = enrol_get_instances((int)$course->courseid, true);
            $hasmanual = false;
            foreach ($instances as $instance) {
                if ($instance->enrol === 'manual' && (int)$instance->status === ENROL_INSTANCE_ENABLED) {
                    $hasmanual = true;
                    break;
                }
            }
            if (!$hasmanual) {
                $errors[] = get_string('readiness:nomanual', 'local_shomokh_admissions', $course->fullname);
            }
        }

        if ($program->programtype === program_repository::TYPE_FOUNDATION && !empty($program->eligibilitygradeitemid)) {
            $item = $DB->get_record('grade_items', ['id' => (int)$program->eligibilitygradeitemid]);
            if (!$item || (int)$item->gradetype !== 1 || $item->calculation === null) {
                $errors[] = get_string('readiness:missinggradeitem', 'local_shomokh_admissions');
            } else if (
                (float)$program->eligibilitymingrade < (float)$item->grademin
                || (float)$program->eligibilitymingrade > (float)$item->grademax
            ) {
                $errors[] = get_string('readiness:invalidthreshold', 'local_shomokh_admissions');
            }
        }
        if (!empty($program->cohortid) && !$DB->record_exists('cohort', ['id' => $program->cohortid])) {
            $errors[] = get_string('readiness:missingcohort', 'local_shomokh_admissions');
        }
        if (!self::valid_telegram_url((string)$program->telegramurl)) {
            $errors[] = get_string('readiness:telegram', 'local_shomokh_admissions');
        }
        return array_values(array_unique($errors));
    }

    /**
     * Checks every configured program, including disabled programs.
     *
     * This report is informational and helps staff finish configuration before
     * any program is enabled or opened.
     *
     * @return array Map of program ID to readiness errors.
     */
    public static function check_all_programs(): array {
        $errors = [];
        foreach (program_repository::get_all() as $program) {
            $programerrors = self::check_program($program);
            if ($programerrors) {
                $errors[(int)$program->id] = $programerrors;
            }
        }
        return $errors;
    }

    /**
     * Checks all enabled and open programs plus the fallback rule.
     *
     * @return array Map of program ID or global key to errors.
     */
    public static function check_global(): array {
        $errors = [];
        $hasopen = false;
        foreach (program_repository::get_all(true) as $program) {
            if (empty($program->registrationopen) && empty($program->recognizeexisting)) {
                continue;
            }
            $hasopen = true;
            $programerrors = self::check_program($program);
            if ($programerrors) {
                $errors[(int)$program->id] = $programerrors;
            }
        }
        if (!$hasopen) {
            $errors['global'][] = get_string('notconfigured', 'local_shomokh_admissions');
        }
        $specialistopen = program_repository::get_open_by_type(program_repository::TYPE_SPECIALIST);
        if ($specialistopen && !self::has_valid_completion_source()) {
            $errors['global'][] = get_string('readiness:nocompletionsource', 'local_shomokh_admissions');
        }
        if ($specialistopen && !program_repository::get_default_fallback()) {
            $errors['global'][] = get_string('readiness:nofallback', 'local_shomokh_admissions');
        }
        return $errors;
    }

    /**
     * Whether at least one enabled foundation program has a valid level result source.
     *
     * @return bool
     */
    private static function has_valid_completion_source(): bool {
        foreach (program_repository::get_all(true) as $program) {
            if (
                $program->programtype === program_repository::TYPE_FOUNDATION
                && !empty($program->eligibilitygradeitemid)
                && !self::grade_source_errors($program)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns only errors concerning a configured level result source.
     *
     * @param \stdClass $program Foundation program.
     * @return array
     */
    private static function grade_source_errors(\stdClass $program): array {
        global $DB;

        if (empty($program->eligibilitygradeitemid)) {
            return [get_string('readiness:nocompletionsource', 'local_shomokh_admissions')];
        }
        $item = $DB->get_record('grade_items', ['id' => (int)$program->eligibilitygradeitemid]);
        if (!$item || (int)$item->gradetype !== 1 || $item->calculation === null) {
            return [get_string('readiness:missinggradeitem', 'local_shomokh_admissions')];
        }
        if (
            (float)$program->eligibilitymingrade < (float)$item->grademin
            || (float)$program->eligibilitymingrade > (float)$item->grademax
        ) {
            return [get_string('readiness:invalidthreshold', 'local_shomokh_admissions')];
        }
        return [];
    }

    /**
     * Allows only common HTTPS Telegram invite/channel forms.
     *
     * @param string $url URL.
     * @return bool
     */
    public static function valid_telegram_url(string $url): bool {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        return ($parts['scheme'] ?? '') === 'https'
            && in_array($host, ['t.me', 'telegram.me', 'www.telegram.me'], true);
    }
}
