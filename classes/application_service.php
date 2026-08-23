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
 * Admission application state machine.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class application_service {
    /** Application is waiting for an external qualification review. */
    public const STATUS_PENDING = 'pending_review';
    /** Application is approved and waiting for enrolment. */
    public const STATUS_APPROVED = 'approved_pending_enrolment';
    /** Application enrolment is complete. */
    public const STATUS_ENROLLED = 'enrolled';
    /** External qualification was rejected and foundation was offered. */
    public const STATUS_REJECTED_OFFER = 'rejected_offer_foundation';
    /** Student accepted the foundation alternative. */
    public const STATUS_FALLBACK_ACCEPTED = 'foundation_offer_accepted';
    /** Student declined the foundation alternative. */
    public const STATUS_FALLBACK_DECLINED = 'foundation_offer_declined';
    /** Application requires an administrator's attention. */
    public const STATUS_NEEDS_ATTENTION = 'needs_attention';
    /** Existing student was recognised from current enrolments. */
    public const STATUS_RECOGNISED = 'recognized_existing';

    /** Eligibility was established from internal course completion. */
    public const SOURCE_INTERNAL = 'internal';
    /** Eligibility depends on an uploaded external qualification. */
    public const SOURCE_EXTERNAL = 'external';
    /** Student applied directly to a foundation program. */
    public const SOURCE_DIRECT = 'direct';
    /** Existing student was recognised from current enrolments. */
    public const SOURCE_RECOGNISED = 'recognized';

    /**
     * Gets an application.
     *
     * @param int $id Application ID.
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_shadm_application', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Returns a user's applications newest first.
     *
     * @param int $userid User ID.
     * @return array
     */
    public static function get_for_user(int $userid): array {
        global $DB;
        $sql = "SELECT a.*, target.name AS targetname, target.programtype,
                       enrolled.name AS enrolledname,
                       fallback.name AS fallbackname, enrolled.telegramurl
                  FROM {local_shadm_application} a
                  JOIN {local_shadm_program} target ON target.id = a.targetprogramid
             LEFT JOIN {local_shadm_program} enrolled ON enrolled.id = a.enrolledprogramid
             LEFT JOIN {local_shadm_program} fallback ON fallback.id = a.fallbackprogramid
                 WHERE a.userid = :userid
              ORDER BY a.timemodified DESC, a.id DESC";
        return $DB->get_records_sql($sql, ['userid' => $userid]);
    }

    /**
     * Creates a student application after independently resolving eligibility.
     *
     * @param int $userid User ID.
     * @param int $programid Requested program.
     * @param bool $externalprovided Whether the page validated an external certificate draft.
     * @param int|null $certificateitemid Validated draft item ID for an external qualification.
     * @return \stdClass Application, existing or new.
     */
    public static function submit(
        int $userid,
        int $programid,
        bool $externalprovided,
        ?int $certificateitemid = null
    ): \stdClass {
        global $DB, $CFG;

        if (empty(get_config('local_shomokh_admissions', 'enabled'))) {
            throw new \moodle_exception('disabled', 'local_shomokh_admissions');
        }
        $program = program_repository::get($programid);
        if (empty($program->enabled) || empty($program->registrationopen)) {
            throw new \moodle_exception('error:programunavailable', 'local_shomokh_admissions');
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_shomokh_admissions');
        $lock = $factory->get_lock('apply:' . $userid . ':' . $programid, 10);
        if (!$lock) {
            throw new \moodle_exception('invalidtransition', 'local_shomokh_admissions');
        }
        try {
            $existing = $DB->get_record('local_shadm_application', [
                'userid' => $userid,
                'targetprogramid' => $programid,
            ]);
            if ($existing) {
                return $existing;
            }

            if ($program->programtype === program_repository::TYPE_SPECIALIST) {
                $sql = "SELECT a.id
                          FROM {local_shadm_application} a
                          JOIN {local_shadm_program} p ON p.id = a.targetprogramid
                         WHERE a.userid = :userid
                           AND p.programtype = :programtype
                           AND a.targetprogramid <> :targetprogramid";
                if (
                    $DB->record_exists_sql($sql, [
                    'userid' => $userid,
                    'programtype' => program_repository::TYPE_SPECIALIST,
                    'targetprogramid' => $programid,
                    ])
                ) {
                    throw new \moodle_exception('error:specialistalreadychosen', 'local_shomokh_admissions');
                }
            }

            if ($program->programtype === program_repository::TYPE_FOUNDATION) {
                $source = self::SOURCE_DIRECT;
                $status = self::STATUS_APPROVED;
            } else if ($program->programtype === program_repository::TYPE_SPECIALIST) {
                if (eligibility_service::completed_foundation($userid)) {
                    $source = self::SOURCE_INTERNAL;
                    $status = self::STATUS_APPROVED;
                } else if ($externalprovided && !empty($certificateitemid)) {
                    self::validate_certificate_draft($userid, $certificateitemid);
                    $source = self::SOURCE_EXTERNAL;
                    $status = self::STATUS_PENDING;
                } else {
                    throw new \moodle_exception('invalidcertificate', 'local_shomokh_admissions');
                }
            } else {
                throw new \moodle_exception('error:programunavailable', 'local_shomokh_admissions');
            }

            $now = time();
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record('local_shadm_application', (object)[
                'userid' => $userid,
                'targetprogramid' => $programid,
                'fallbackprogramid' => null,
                'enrolledprogramid' => null,
                'eligibilitysource' => $source,
                'status' => $status,
                'decisionby' => null,
                'decisionnote' => null,
                'studentmessage' => null,
                'timedecided' => $source === self::SOURCE_EXTERNAL ? null : $now,
                'timeenrolled' => null,
                'recordversion' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            if ($source === self::SOURCE_EXTERNAL) {
                require_once($CFG->libdir . '/filelib.php');
                file_save_draft_area_files(
                    $certificateitemid,
                    \context_system::instance()->id,
                    'local_shomokh_admissions',
                    'certificate',
                    $id,
                    [
                        'accepted_types' => ['.pdf', '.jpg', '.jpeg', '.png'],
                        'maxbytes' => max(
                            1024,
                            (int)get_config('local_shomokh_admissions', 'maxcertificatebytes')
                        ),
                        'maxfiles' => 1,
                        'subdirs' => 0,
                    ]
                );
                $savedfiles = get_file_storage()->get_area_files(
                    \context_system::instance()->id,
                    'local_shomokh_admissions',
                    'certificate',
                    $id,
                    'id ASC',
                    false
                );
                if (count($savedfiles) !== 1) {
                    throw new \moodle_exception('invalidcertificate', 'local_shomokh_admissions');
                }
                audit_service::record($id, $userid, 'certificate_saved');
            }
            audit_service::record($id, $userid, 'application_submitted', null, $status, $source);
            $operationid = null;
            if ($status === self::STATUS_APPROVED) {
                $operationid = enrolment_service::queue($id, $userid, $programid);
            }
            $transaction->allow_commit();

            if ($operationid) {
                enrolment_service::process($operationid);
            } else {
                notification_service::send($userid, 'pending');
            }
            return self::get($id);
        } finally {
            $lock->release();
        }
    }

    /**
     * Revalidates the draft server-side, independent of the submitted form fields.
     *
     * @param int $userid Draft owner.
     * @param int $draftitemid Draft item ID.
     * @return void
     */
    private static function validate_certificate_draft(int $userid, int $draftitemid): void {
        $files = get_file_storage()->get_area_files(
            \context_user::instance($userid)->id,
            'user',
            'draft',
            $draftitemid,
            'id ASC',
            false
        );
        if (count($files) !== 1) {
            throw new \moodle_exception('certificatefilecount', 'local_shomokh_admissions');
        }
        $file = reset($files);
        $maxbytes = max(1024, (int)get_config('local_shomokh_admissions', 'maxcertificatebytes'));
        if (
            !in_array($file->get_mimetype(), ['application/pdf', 'image/jpeg', 'image/png'], true)
                || $file->get_filesize() > $maxbytes
        ) {
            throw new \moodle_exception('invalidcertificate', 'local_shomokh_admissions');
        }
    }

    /**
     * Converts one pending external application when a trusted internal
     * graduation rule now proves eligibility.
     *
     * The same lock used by manual review prevents a scheduled reassessment
     * from racing a staff decision. The now-unneeded certificate is deleted
     * after the durable transition to minimise retained personal data.
     *
     * @param int $applicationid Application ID.
     * @return bool True when converted and queued for enrolment.
     */
    public static function approve_internal_if_eligible(int $applicationid): bool {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('local_shomokh_admissions');
        $lock = $factory->get_lock('review:' . $applicationid, 0);
        if (!$lock) {
            return false;
        }
        try {
            $application = self::get($applicationid);
            if ($application->status !== self::STATUS_PENDING) {
                return false;
            }
            $group = eligibility_service::completed_foundation((int)$application->userid);
            if (!$group) {
                return false;
            }

            $oldstatus = $application->status;
            $now = time();
            $application->eligibilitysource = self::SOURCE_INTERNAL;
            $application->status = self::STATUS_APPROVED;
            $application->decisionby = null;
            $application->decisionnote = null;
            $application->studentmessage = null;
            $application->timedecided = $now;
            $application->timemodified = $now;
            $application->recordversion++;

            $transaction = $DB->start_delegated_transaction();
            $DB->update_record('local_shadm_application', $application);
            $operationid = enrolment_service::queue(
                (int)$application->id,
                (int)$application->userid,
                (int)$application->targetprogramid
            );
            audit_service::record(
                (int)$application->id,
                null,
                'internal_eligibility_reassessed',
                $oldstatus,
                self::STATUS_APPROVED,
                (string)$group->code
            );
            $transaction->allow_commit();

            get_file_storage()->delete_area_files(
                \context_system::instance()->id,
                'local_shomokh_admissions',
                'certificate',
                (int)$application->id
            );
            notification_service::send((int)$application->userid, 'approved');
            enrolment_service::process($operationid);
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Applies a reviewer decision using optimistic version checking and a lock.
     *
     * @param int $applicationid Application ID.
     * @param string $decision approve or reject.
     * @param string $note Internal note.
     * @param string $studentmessage Message visible to the student.
     * @param int $actorid Reviewer ID.
     * @param int $recordversion Version shown to the reviewer.
     * @return \stdClass Updated application.
     */
    public static function review(
        int $applicationid,
        string $decision,
        string $note,
        string $studentmessage,
        int $actorid,
        int $recordversion
    ): \stdClass {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('local_shomokh_admissions');
        $lock = $factory->get_lock('review:' . $applicationid, 10);
        if (!$lock) {
            throw new \moodle_exception('invalidtransition', 'local_shomokh_admissions');
        }
        try {
            $application = self::get($applicationid);
            if (
                $application->status !== self::STATUS_PENDING
                    || (int)$application->recordversion !== $recordversion
            ) {
                throw new \moodle_exception('invalidtransition', 'local_shomokh_admissions');
            }
            if (!in_array($decision, ['approve', 'reject'], true)) {
                throw new \invalid_parameter_exception('Invalid admission decision.');
            }

            $oldstatus = $application->status;
            $application->decisionby = $actorid;
            $application->decisionnote = trim($note) ?: null;
            $application->studentmessage = trim($studentmessage) ?: null;
            $application->timedecided = time();
            $application->timemodified = time();
            $application->recordversion++;
            $operationid = null;

            $transaction = $DB->start_delegated_transaction();
            if ($decision === 'approve') {
                $application->status = self::STATUS_APPROVED;
                $operationid = enrolment_service::queue(
                    (int)$application->id,
                    (int)$application->userid,
                    (int)$application->targetprogramid
                );
            } else {
                $fallback = program_repository::get_default_fallback();
                if (!$fallback) {
                    throw new \moodle_exception('error:nofallback', 'local_shomokh_admissions');
                }
                $application->fallbackprogramid = $fallback->id;
                $application->status = self::STATUS_REJECTED_OFFER;
            }
            $DB->update_record('local_shadm_application', $application);
            audit_service::record(
                $applicationid,
                $actorid,
                'review_' . $decision,
                $oldstatus,
                $application->status,
                null
            );
            $transaction->allow_commit();

            if ($operationid) {
                notification_service::send((int)$application->userid, 'approved');
                enrolment_service::process($operationid);
            } else {
                notification_service::send((int)$application->userid, 'rejected');
            }
            return self::get($applicationid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Records the student's response to a foundation fallback offer.
     *
     * @param int $applicationid Application ID.
     * @param int $userid Current user ID.
     * @param bool $accepted Whether the student accepted.
     * @return \stdClass Updated application.
     */
    public static function respond_to_fallback(int $applicationid, int $userid, bool $accepted): \stdClass {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('local_shomokh_admissions');
        $lock = $factory->get_lock('fallback:' . $applicationid, 10);
        if (!$lock) {
            throw new \moodle_exception('invalidtransition', 'local_shomokh_admissions');
        }
        try {
            $application = self::get($applicationid);
            if (
                (int)$application->userid !== $userid
                    || $application->status !== self::STATUS_REJECTED_OFFER
            ) {
                throw new \moodle_exception('invalidtransition', 'local_shomokh_admissions');
            }
            if ($accepted && empty($application->fallbackprogramid)) {
                throw new \moodle_exception('error:nofallback', 'local_shomokh_admissions');
            }

            $oldstatus = $application->status;
            $application->status = $accepted
                ? self::STATUS_FALLBACK_ACCEPTED
                : self::STATUS_FALLBACK_DECLINED;
            $application->recordversion++;
            $application->timemodified = time();
            $operationid = null;

            $transaction = $DB->start_delegated_transaction();
            $DB->update_record('local_shadm_application', $application);
            if ($accepted) {
                $operationid = enrolment_service::queue(
                    (int)$application->id,
                    $userid,
                    (int)$application->fallbackprogramid
                );
            }
            audit_service::record(
                $applicationid,
                $userid,
                $accepted ? 'fallback_accepted' : 'fallback_declined',
                $oldstatus,
                $application->status,
                null
            );
            $transaction->allow_commit();
            if ($operationid) {
                enrolment_service::process($operationid);
            }
            return self::get($applicationid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Maps an internal status to a translated label.
     *
     * @param string $status Status.
     * @return string
     */
    public static function status_label(string $status): string {
        $known = [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_ENROLLED,
            self::STATUS_REJECTED_OFFER,
            self::STATUS_FALLBACK_ACCEPTED,
            self::STATUS_FALLBACK_DECLINED,
            self::STATUS_NEEDS_ATTENTION,
            self::STATUS_RECOGNISED,
        ];
        $key = in_array($status, $known, true) ? 'status:' . $status : 'status:unknown';
        return get_string($key, 'local_shomokh_admissions');
    }
}
