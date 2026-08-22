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
 * Recognises existing students without asking them to submit a new application.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recognition_service {
    /**
     * Recognises the current user against each configured legacy destination.
     *
     * @param int $userid User ID.
     * @return array Applications created or already present for recognised programs.
     */
    public static function recognise_user(int $userid): array {
        $applications = [];
        foreach (program_repository::get_all(true) as $program) {
            if (empty($program->recognizeexisting) || !self::user_matches($userid, $program)) {
                continue;
            }
            $application = self::recognise_program_user($userid, $program);
            if ($application) {
                $applications[(int)$application->id] = $application;
            }
        }
        return $applications;
    }

    /**
     * Runs one bounded scan for each recognition program.
     *
     * @param bool $force Ignore the daily full-scan throttle (manual administrator action).
     * @return int Number of user rows inspected.
     */
    public static function run_batch(bool $force = false): int {
        global $DB;
        $batch = min(2000, max(50, (int)get_config('local_shomokh_admissions', 'recognitionbatch')));
        $processed = 0;
        foreach (program_repository::get_all(true) as $program) {
            if (empty($program->recognizeexisting)) {
                continue;
            }
            $cursorname = 'recognitioncursor_' . (int)$program->id;
            $cursor = (int)get_config('local_shomokh_admissions', $cursorname);
            $lastfull = (int)get_config(
                'local_shomokh_admissions',
                'recognitionlastfull_' . (int)$program->id
            );
            if (!$force && $cursor === 0 && $lastfull > 0 && $lastfull > time() - DAYSECS) {
                continue;
            }
            $courses = program_repository::get_courses((int)$program->id);
            $courseids = array_map(static fn($course): int => (int)$course->courseid, $courses);
            if (!$courseids && empty($program->cohortid)) {
                continue;
            }

            $params = [
                'cursor' => $cursor,
                'useractive' => ENROL_USER_ACTIVE,
                'instanceactive' => ENROL_INSTANCE_ENABLED,
            ];
            $clauses = [];
            if ($courseids) {
                [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
                $params += $inparams;
                $clauses[] = "EXISTS (
                    SELECT 1
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.userid = u.id
                       AND e.courseid $insql
                       AND ue.status = :useractive
                       AND e.status = :instanceactive
                )";
            }
            if (!empty($program->cohortid)) {
                $params['cohortid'] = (int)$program->cohortid;
                $clauses[] = "EXISTS (
                    SELECT 1 FROM {cohort_members} cm
                     WHERE cm.userid = u.id AND cm.cohortid = :cohortid
                )";
            }
            $sql = "SELECT u.id
                      FROM {user} u
                     WHERE u.id > :cursor
                       AND u.deleted = 0
                       AND u.suspended = 0
                       AND (" . implode(' OR ', $clauses) . ")
                  ORDER BY u.id ASC";
            $users = $DB->get_records_sql($sql, $params, 0, $batch);
            foreach ($users as $user) {
                self::recognise_program_user((int)$user->id, $program);
                $cursor = max($cursor, (int)$user->id);
                $processed++;
            }
            if (count($users) < $batch) {
                $cursor = 0;
                set_config('recognitionlastfull_' . (int)$program->id, time(), 'local_shomokh_admissions');
            }
            set_config($cursorname, $cursor, 'local_shomokh_admissions');
        }
        return $processed;
    }

    /**
     * Checks current enrolment or cohort membership.
     *
     * @param int $userid User ID.
     * @param \stdClass $program Program.
     * @return bool
     */
    private static function user_matches(int $userid, \stdClass $program): bool {
        global $DB;
        if (
            !empty($program->cohortid) && $DB->record_exists('cohort_members', [
            'cohortid' => $program->cohortid,
            'userid' => $userid,
            ])
        ) {
            return true;
        }
        $courses = program_repository::get_courses((int)$program->id);
        $courseids = array_map(static fn($course): int => (int)$course->courseid, $courses);
        if (!$courseids) {
            return false;
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params += [
            'userid' => $userid,
            'useractive' => ENROL_USER_ACTIVE,
            'instanceactive' => ENROL_INSTANCE_ENABLED,
        ];
        $sql = "SELECT 1
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid $insql
                   AND ue.status = :useractive
                   AND e.status = :instanceactive";
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Inserts the durable recognition record without changing enrolments.
     *
     * @param int $userid User ID.
     * @param \stdClass $program Program.
     * @return \stdClass|null
     */
    private static function recognise_program_user(int $userid, \stdClass $program): ?\stdClass {
        global $DB;
        $existing = $DB->get_record('local_shadm_application', [
            'userid' => $userid,
            'targetprogramid' => $program->id,
        ]);
        if ($existing) {
            return $existing;
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_shomokh_admissions');
        $lock = $factory->get_lock('recognise:' . $userid . ':' . $program->id, 0);
        if (!$lock) {
            return null;
        }
        try {
            $existing = $DB->get_record('local_shadm_application', [
                'userid' => $userid,
                'targetprogramid' => $program->id,
            ]);
            if ($existing) {
                return $existing;
            }
            $now = time();
            $id = $DB->insert_record('local_shadm_application', (object)[
                'userid' => $userid,
                'targetprogramid' => $program->id,
                'fallbackprogramid' => null,
                'enrolledprogramid' => $program->id,
                'eligibilitysource' => application_service::SOURCE_RECOGNISED,
                'status' => application_service::STATUS_RECOGNISED,
                'decisionby' => null,
                'decisionnote' => null,
                'studentmessage' => null,
                'timedecided' => null,
                'timeenrolled' => $now,
                'recordversion' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            audit_service::record(
                $id,
                null,
                'existing_recognised',
                null,
                application_service::STATUS_RECOGNISED,
                $program->code
            );
            return application_service::get($id);
        } finally {
            $lock->release();
        }
    }
}
