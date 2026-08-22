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
 * Idempotent enrolment orchestration with a durable retry record.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolment_service {
    /** Enrolment operation is waiting to run. */
    public const STATUS_QUEUED = 'queued';
    /** Enrolment operation is currently running. */
    public const STATUS_RUNNING = 'running';
    /** Enrolment operation failed and can be retried. */
    public const STATUS_FAILED = 'failed';
    /** Enrolment operation exhausted its automatic retries. */
    public const STATUS_EXHAUSTED = 'exhausted';
    /** Enrolment operation completed successfully. */
    public const STATUS_COMPLETE = 'complete';

    /**
     * Creates or returns the one operation belonging to an application.
     *
     * @param int $applicationid Application ID.
     * @param int $userid User ID.
     * @param int $programid Destination program.
     * @return int Operation ID.
     */
    public static function queue(int $applicationid, int $userid, int $programid): int {
        global $DB;
        $existing = $DB->get_record('local_shadm_enrolop', ['applicationid' => $applicationid]);
        if ($existing) {
            if ((int)$existing->programid !== $programid) {
                throw new \coding_exception('An application cannot target two enrolment operations.');
            }
            if ($existing->status !== self::STATUS_COMPLETE) {
                $existing->status = self::STATUS_QUEUED;
                $existing->nextrun = time();
                $existing->timemodified = time();
                $DB->update_record('local_shadm_enrolop', $existing);
            }
            return (int)$existing->id;
        }
        $now = time();
        return (int)$DB->insert_record('local_shadm_enrolop', (object)[
            'applicationid' => $applicationid,
            'userid' => $userid,
            'programid' => $programid,
            'status' => self::STATUS_QUEUED,
            'attempts' => 0,
            'nextrun' => $now,
            'lasterror' => null,
            'cohortadded' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Processes one operation. Safe to call repeatedly.
     *
     * @param int $operationid Operation ID.
     * @return bool True on completion.
     */
    public static function process(int $operationid): bool {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        $factory = \core\lock\lock_config::get_lock_factory('local_shomokh_admissions');
        $lock = $factory->get_lock('enrolop:' . $operationid, 0);
        if (!$lock) {
            return false;
        }
        try {
            $operation = $DB->get_record('local_shadm_enrolop', ['id' => $operationid], '*', MUST_EXIST);
            if ($operation->status === self::STATUS_COMPLETE) {
                return true;
            }
            $operation->status = self::STATUS_RUNNING;
            $operation->attempts++;
            $operation->timemodified = time();
            $DB->update_record('local_shadm_enrolop', $operation);

            try {
                $program = program_repository::get((int)$operation->programid);
                $courses = program_repository::get_courses((int)$program->id);
                if (!$courses) {
                    throw new \moodle_exception('readiness:nocourses', 'local_shomokh_admissions');
                }
                foreach ($courses as $mapping) {
                    self::enrol_course($operation, $mapping);
                }

                if (!empty($program->cohortid)) {
                    if (!$DB->record_exists('cohort', ['id' => $program->cohortid])) {
                        throw new \moodle_exception('readiness:missingcohort', 'local_shomokh_admissions');
                    }
                    if (
                        !$DB->record_exists('cohort_members', [
                        'cohortid' => $program->cohortid,
                        'userid' => $operation->userid,
                        ])
                    ) {
                        cohort_add_member((int)$program->cohortid, (int)$operation->userid);
                        $operation->cohortadded = 1;
                    }
                }

                $operation->status = self::STATUS_COMPLETE;
                $operation->lasterror = null;
                $operation->nextrun = 0;
                $operation->timemodified = time();
                $DB->update_record('local_shadm_enrolop', $operation);

                $application = application_service::get((int)$operation->applicationid);
                $oldstatus = $application->status;
                $application->status = application_service::STATUS_ENROLLED;
                $application->enrolledprogramid = $operation->programid;
                $application->timeenrolled = time();
                $application->timemodified = time();
                $application->recordversion++;
                $DB->update_record('local_shadm_application', $application);
                audit_service::record(
                    (int)$application->id,
                    null,
                    'enrolment_completed',
                    $oldstatus,
                    application_service::STATUS_ENROLLED,
                    null
                );
                notification_service::send((int)$operation->userid, 'enrolled');
                return true;
            } catch (\Throwable $exception) {
                $retrylimit = max(1, (int)get_config('local_shomokh_admissions', 'retrylimit'));
                $exhausted = (int)$operation->attempts >= $retrylimit;
                $operation->status = $exhausted ? self::STATUS_EXHAUSTED : self::STATUS_FAILED;
                $operation->lasterror = self::safe_error($exception);
                $delay = min(3600, 60 * (2 ** min(6, (int)$operation->attempts - 1)));
                $operation->nextrun = $exhausted ? 0 : time() + $delay;
                $operation->timemodified = time();
                $DB->update_record('local_shadm_enrolop', $operation);
                if ($exhausted) {
                    $application = application_service::get((int)$operation->applicationid);
                    $oldstatus = $application->status;
                    $application->status = application_service::STATUS_NEEDS_ATTENTION;
                    $application->timemodified = time();
                    $application->recordversion++;
                    $DB->update_record('local_shadm_application', $application);
                    audit_service::record(
                        (int)$application->id,
                        null,
                        'enrolment_exhausted',
                        $oldstatus,
                        application_service::STATUS_NEEDS_ATTENTION,
                        get_class($exception)
                    );
                }
                return false;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Processes due queued or failed operations.
     *
     * @param int $limit Maximum operations.
     * @return int Number attempted.
     */
    public static function process_due(int $limit = 20): int {
        global $DB;
        $now = time();
        $sql = "SELECT *
                  FROM {local_shadm_enrolop}
                 WHERE status IN (:queued, :failed)
                   AND nextrun <= :now
              ORDER BY nextrun ASC, id ASC";
        $operations = $DB->get_records_sql($sql, [
            'queued' => self::STATUS_QUEUED,
            'failed' => self::STATUS_FAILED,
            'now' => $now,
        ], 0, max(1, $limit));
        foreach ($operations as $operation) {
            self::process((int)$operation->id);
        }
        return count($operations);
    }

    /**
     * Requeues an operation after an authorised administrator chooses retry.
     *
     * @param int $operationid Operation ID.
     * @param int $actorid Administrator ID.
     * @return void
     */
    public static function retry(int $operationid, int $actorid): void {
        global $DB;
        $operation = $DB->get_record('local_shadm_enrolop', ['id' => $operationid], '*', MUST_EXIST);
        if ($operation->status === self::STATUS_COMPLETE) {
            return;
        }
        $operation->status = self::STATUS_QUEUED;
        $operation->attempts = 0;
        $operation->nextrun = time();
        $operation->lasterror = null;
        $operation->timemodified = time();
        $DB->update_record('local_shadm_enrolop', $operation);

        $application = application_service::get((int)$operation->applicationid);
        if ($application->status === application_service::STATUS_NEEDS_ATTENTION) {
            $oldstatus = $application->status;
            $application->status = $application->fallbackprogramid == $operation->programid
                ? application_service::STATUS_FALLBACK_ACCEPTED
                : application_service::STATUS_APPROVED;
            $application->recordversion++;
            $application->timemodified = time();
            $DB->update_record('local_shadm_application', $application);
            audit_service::record(
                (int)$application->id,
                $actorid,
                'enrolment_requeued',
                $oldstatus,
                $application->status,
                null
            );
        }
    }

    /**
     * Idempotently enrols a user in one mapped course and records ownership.
     *
     * @param \stdClass $operation Operation record.
     * @param \stdClass $mapping Mapping joined to course data.
     * @return void
     */
    private static function enrol_course(\stdClass $operation, \stdClass $mapping): void {
        global $DB;

        if (
            $DB->record_exists('local_shadm_enrollog', [
            'enrolopid' => $operation->id,
            'courseid' => $mapping->courseid,
            ])
        ) {
            return;
        }

        $context = \context_course::instance((int)$mapping->courseid);
        $existing = self::active_user_enrolment((int)$operation->userid, (int)$mapping->courseid);
        if ($existing && is_enrolled($context, (int)$operation->userid, '', true)) {
            self::write_course_log($operation, $mapping, $existing->enrolinstanceid, $existing->userenrolid, 'existing');
            return;
        }

        $manualinstance = null;
        foreach (enrol_get_instances((int)$mapping->courseid, true) as $instance) {
            if ($instance->enrol === 'manual' && (int)$instance->status === ENROL_INSTANCE_ENABLED) {
                $manualinstance = $instance;
                break;
            }
        }
        if (!$manualinstance) {
            throw new \moodle_exception(
                'readiness:nomanual',
                'local_shomokh_admissions',
                '',
                $mapping->fullname
            );
        }
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            throw new \coding_exception('Manual enrolment plugin is unavailable.');
        }
        $roleid = (int)($manualinstance->roleid ?? 0);
        if (!$roleid) {
            $roles = get_archetype_roles('student');
            $studentrole = reset($roles);
            $roleid = $studentrole ? (int)$studentrole->id : 0;
        }
        if (!$roleid) {
            throw new \coding_exception('No student role is available for enrolment.');
        }
        $plugin->enrol_user(
            $manualinstance,
            (int)$operation->userid,
            $roleid,
            0,
            0,
            ENROL_USER_ACTIVE
        );
        $userenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $manualinstance->id,
            'userid' => $operation->userid,
        ], '*', MUST_EXIST);
        self::write_course_log(
            $operation,
            $mapping,
            (int)$manualinstance->id,
            (int)$userenrolment->id,
            'created'
        );
    }

    /**
     * Gets one active enrolment for a user and course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return \stdClass|null
     */
    private static function active_user_enrolment(int $userid, int $courseid): ?\stdClass {
        global $DB;
        $sql = "SELECT ue.id AS userenrolid, e.id AS enrolinstanceid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid = :courseid
                   AND ue.status = :useractive
                   AND e.status = :instanceactive
              ORDER BY ue.id ASC";
        $record = $DB->get_record_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'useractive' => ENROL_USER_ACTIVE,
            'instanceactive' => ENROL_INSTANCE_ENABLED,
        ], IGNORE_MULTIPLE);
        return $record ?: null;
    }

    /**
     * Writes the per-course ownership record.
     *
     * @param \stdClass $operation Operation.
     * @param \stdClass $mapping Course mapping.
     * @param int|null $instanceid Enrolment instance.
     * @param int|null $userenrolid User enrolment.
     * @param string $ownership existing or created.
     * @return void
     */
    private static function write_course_log(
        \stdClass $operation,
        \stdClass $mapping,
        ?int $instanceid,
        ?int $userenrolid,
        string $ownership
    ): void {
        global $DB;
        $DB->insert_record('local_shadm_enrollog', (object)[
            'enrolopid' => $operation->id,
            'userid' => $operation->userid,
            'programid' => $operation->programid,
            'courseid' => $mapping->courseid,
            'enrolinstanceid' => $instanceid,
            'userenrolid' => $userenrolid,
            'ownership' => $ownership,
            'timecreated' => time(),
        ]);
    }

    /**
     * Produces a bounded administrator-only error without a stack trace.
     *
     * @param \Throwable $exception Exception.
     * @return string
     */
    private static function safe_error(\Throwable $exception): string {
        $message = preg_replace('/\s+/', ' ', $exception->getMessage());
        return substr(get_class($exception) . ': ' . clean_param((string)$message, PARAM_TEXT), 0, 1000);
    }
}
