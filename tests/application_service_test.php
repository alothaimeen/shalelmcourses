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
 * Tests application, review, consent and enrolment states end to end.
 *
 * @covers     \local_shomokh_admissions\application_service
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class application_service_test extends \advanced_testcase {
    /**
     * Enables safe defaults and captures notifications.
     */
    private function prepare_test(): void {
        $this->resetAfterTest();
        $this->redirectMessages();
        set_config('enabled', 0, 'local_shomokh_admissions');
        set_config('maxcertificatebytes', 5 * 1024 * 1024, 'local_shomokh_admissions');
        set_config('retrylimit', 5, 'local_shomokh_admissions');
    }

    /**
     * Creates a completion-enabled course with its normal manual enrolment instance.
     *
     * @param string $suffix Unique course suffix.
     * @return \stdClass Created course.
     */
    private function create_admission_course(string $suffix): \stdClass {
        return $this->getDataGenerator()->create_course([
            'fullname' => 'Admission course ' . $suffix,
            'shortname' => 'admission_' . $suffix,
            'enablecompletion' => 1,
        ]);
    }

    /**
     * Updates one seeded program and optionally links a course.
     *
     * @param string $code Program code.
     * @param \stdClass|null $course Course to link.
     * @param bool $open Whether registration is open.
     * @param bool $fallback Whether this is the default fallback.
     * @param bool $recognise Whether recognition is enabled.
     * @return \stdClass Updated program.
     */
    private function configure_program(
        string $code,
        ?\stdClass $course = null,
        bool $open = true,
        bool $fallback = false,
        bool $recognise = false
    ): \stdClass {
        global $DB;
        $program = $DB->get_record('local_shadm_program', ['code' => $code], '*', MUST_EXIST);
        $program->enabled = 1;
        $program->registrationopen = $open ? 1 : 0;
        $program->defaultfallback = $fallback ? 1 : 0;
        $program->recognizeexisting = $recognise ? 1 : 0;
        $program->telegramurl = 'https://t.me/test_' . $code;
        $program->timemodified = time();
        $DB->update_record('local_shadm_program', $program);
        if ($course) {
            program_repository::add_course((int)$program->id, (int)$course->id);
        }
        return program_repository::get((int)$program->id);
    }

    /**
     * Configures a course total as the authoritative full-level result.
     *
     * @param \stdClass $program Foundation program.
     * @param \stdClass $course Result course.
     * @param \stdClass $student Student receiving the result.
     * @param float $grade Result value.
     * @return \grade_item Configured item.
     */
    private function configure_level_result(
        \stdClass $program,
        \stdClass $course,
        \stdClass $student,
        float $grade
    ): \grade_item {
        global $DB;
        $item = \grade_item::fetch_course_item((int)$course->id);
        $DB->set_field('grade_items', 'calculation', '=1', ['id' => $item->id]);
        $item = \grade_item::fetch(['id' => $item->id]);
        $DB->set_field('local_shadm_program', 'eligibilitygradeitemid', (int)$item->id, ['id' => $program->id]);
        $DB->set_field('local_shadm_program', 'eligibilitymingrade', 1, ['id' => $program->id]);
        $item->update_final_grade((int)$student->id, $grade, 'local_shomokh_admissions_test');
        return $item;
    }

    /**
     * Creates one valid draft qualification owned by the supplied user.
     *
     * @param \stdClass $user File owner.
     * @param int $itemid Draft item ID.
     * @return void
     */
    private function create_certificate_draft(\stdClass $user, int $itemid): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance((int)$user->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => 'qualification.pdf',
        ], "%PDF-1.4\nShomokh admissions test certificate");
    }

    /**
     * Verifies foundation applications enrol once and remain idempotent.
     */
    public function test_direct_foundation_application_enrols_idempotently(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->create_admission_course('foundation_direct');
        $program = $this->configure_program('foundation_b4', $course);
        set_config('enabled', 1, 'local_shomokh_admissions');

        $application = application_service::submit((int)$student->id, (int)$program->id, false);
        $duplicate = application_service::submit((int)$student->id, (int)$program->id, false);

        $this->assertSame((int)$application->id, (int)$duplicate->id);
        $this->assertSame(application_service::STATUS_ENROLLED, $duplicate->status);
        $this->assertTrue(is_enrolled(\context_course::instance((int)$course->id), (int)$student->id, '', true));
        $this->assertSame(1, $DB->count_records('local_shadm_application', ['userid' => $student->id]));
        $this->assertSame(1, $DB->count_records('local_shadm_enrollog', ['userid' => $student->id]));
    }

    /**
     * Verifies internal completion grants specialist admission without a certificate.
     */
    public function test_internal_completion_grants_specialist_automatically(): void {
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $foundationcourse = $this->create_admission_course('foundation_required');
        $foundation = $this->configure_program('foundation_b3', $foundationcourse, false);
        $item = $this->configure_level_result($foundation, $foundationcourse, $student, 0);
        $specialistcourse = $this->create_admission_course('hadith_internal');
        $specialist = $this->configure_program('specialist_hadith', $specialistcourse);

        $this->assertNull(eligibility_service::completed_foundation((int)$student->id));
        $item->update_final_grade((int)$student->id, 1, 'local_shomokh_admissions_test');
        $this->assertSame(
            (int)$foundation->id,
            (int)eligibility_service::completed_foundation((int)$student->id)->id
        );
        set_config('enabled', 1, 'local_shomokh_admissions');

        $application = application_service::submit((int)$student->id, (int)$specialist->id, false);

        $this->assertSame(application_service::SOURCE_INTERNAL, $application->eligibilitysource);
        $this->assertSame(application_service::STATUS_ENROLLED, $application->status);
        $this->assertTrue(is_enrolled(
            \context_course::instance((int)$specialistcourse->id),
            (int)$student->id,
            '',
            true
        ));
    }

    /**
     * Verifies an external certificate remains private and awaits a decision.
     */
    public function test_external_certificate_creates_pending_application(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $course = $this->create_admission_course('hadith_external_pending');
        $program = $this->configure_program('specialist_hadith', $course);
        $draftitemid = 10001;
        $this->create_certificate_draft($student, $draftitemid);
        set_config('enabled', 1, 'local_shomokh_admissions');

        $application = application_service::submit(
            (int)$student->id,
            (int)$program->id,
            true,
            $draftitemid
        );
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_shomokh_admissions',
            'certificate',
            (int)$application->id,
            'id ASC',
            false
        );

        $this->assertSame(application_service::STATUS_PENDING, $application->status);
        $this->assertSame(application_service::SOURCE_EXTERNAL, $application->eligibilitysource);
        $this->assertCount(1, $files);
        $this->assertFalse(is_enrolled(\context_course::instance((int)$course->id), (int)$student->id));
        $this->assertSame(2, $DB->count_records('local_shadm_audit', ['applicationid' => $application->id]));
    }

    /**
     * Verifies missing or invalid drafts cannot create an application.
     */
    public function test_external_application_requires_owned_valid_certificate(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $course = $this->create_admission_course('hadith_missing_certificate');
        $program = $this->configure_program('specialist_hadith', $course);
        set_config('enabled', 1, 'local_shomokh_admissions');

        try {
            application_service::submit((int)$student->id, (int)$program->id, true, 987654);
            $this->fail('A missing draft must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('certificatefilecount', $exception->errorcode);
        }
        $this->assertFalse($DB->record_exists('local_shadm_application', ['userid' => $student->id]));
    }

    /**
     * Verifies approval records the reviewer and enrols the chosen pathway.
     */
    public function test_reviewer_approval_enrols_selected_pathway(): void {
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $course = $this->create_admission_course('tafsir_approved');
        $program = $this->configure_program('specialist_tafsir', $course);
        $this->create_certificate_draft($student, 10002);
        set_config('enabled', 1, 'local_shomokh_admissions');
        $application = application_service::submit((int)$student->id, (int)$program->id, true, 10002);

        $application = application_service::review(
            (int)$application->id,
            'approve',
            'Verified qualification',
            'Welcome',
            (int)$reviewer->id,
            (int)$application->recordversion
        );

        $this->assertSame(application_service::STATUS_ENROLLED, $application->status);
        $this->assertSame((int)$reviewer->id, (int)$application->decisionby);
        $this->assertTrue(is_enrolled(\context_course::instance((int)$course->id), (int)$student->id, '', true));
    }

    /**
     * Verifies rejection waits for explicit foundation consent.
     */
    public function test_rejection_requires_student_consent_before_foundation(): void {
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $specialistcourse = $this->create_admission_course('hadith_rejected');
        $foundationcourse = $this->create_admission_course('foundation_fallback');
        $specialist = $this->configure_program('specialist_hadith', $specialistcourse);
        $foundation = $this->configure_program('foundation_b4', $foundationcourse, true, true);
        $this->create_certificate_draft($student, 10003);
        set_config('enabled', 1, 'local_shomokh_admissions');
        $application = application_service::submit((int)$student->id, (int)$specialist->id, true, 10003);

        $application = application_service::review(
            (int)$application->id,
            'reject',
            'Not sufficient',
            '',
            (int)$reviewer->id,
            (int)$application->recordversion
        );
        $this->assertSame(application_service::STATUS_REJECTED_OFFER, $application->status);
        $this->assertSame((int)$foundation->id, (int)$application->fallbackprogramid);
        $this->assertFalse(is_enrolled(\context_course::instance((int)$foundationcourse->id), (int)$student->id));

        $application = application_service::respond_to_fallback(
            (int)$application->id,
            (int)$student->id,
            true
        );
        $this->assertSame(application_service::STATUS_ENROLLED, $application->status);
        $this->assertSame((int)$foundation->id, (int)$application->enrolledprogramid);
        $this->assertTrue(is_enrolled(
            \context_course::instance((int)$foundationcourse->id),
            (int)$student->id,
            '',
            true
        ));
        $this->assertFalse(is_enrolled(
            \context_course::instance((int)$specialistcourse->id),
            (int)$student->id,
            '',
            true
        ));
    }

    /**
     * Verifies declining the offer creates no enrolment operation.
     */
    public function test_student_can_decline_foundation_offer(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $specialistcourse = $this->create_admission_course('tafsir_declined');
        $foundationcourse = $this->create_admission_course('foundation_declined');
        $specialist = $this->configure_program('specialist_tafsir', $specialistcourse);
        $this->configure_program('foundation_b4', $foundationcourse, true, true);
        $this->create_certificate_draft($student, 10004);
        set_config('enabled', 1, 'local_shomokh_admissions');
        $application = application_service::submit((int)$student->id, (int)$specialist->id, true, 10004);
        $application = application_service::review(
            (int)$application->id,
            'reject',
            '',
            '',
            (int)$reviewer->id,
            (int)$application->recordversion
        );

        $application = application_service::respond_to_fallback(
            (int)$application->id,
            (int)$student->id,
            false
        );

        $this->assertSame(application_service::STATUS_FALLBACK_DECLINED, $application->status);
        $this->assertFalse($DB->record_exists('local_shadm_enrolop', ['applicationid' => $application->id]));
        $this->assertFalse(is_enrolled(\context_course::instance((int)$foundationcourse->id), (int)$student->id));
    }

    /**
     * Verifies a student cannot hold applications for both specialist choices.
     */
    public function test_only_one_specialist_pathway_can_be_selected(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $hadith = $this->configure_program(
            'specialist_hadith',
            $this->create_admission_course('hadith_single_choice')
        );
        $tafsir = $this->configure_program(
            'specialist_tafsir',
            $this->create_admission_course('tafsir_single_choice')
        );
        $this->create_certificate_draft($student, 10005);
        set_config('enabled', 1, 'local_shomokh_admissions');
        application_service::submit((int)$student->id, (int)$hadith->id, true, 10005);
        $this->create_certificate_draft($student, 10006);

        try {
            application_service::submit((int)$student->id, (int)$tafsir->id, true, 10006);
            $this->fail('A second specialist choice must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('error:specialistalreadychosen', $exception->errorcode);
        }
        $this->assertSame(1, $DB->count_records('local_shadm_application', ['userid' => $student->id]));
    }

    /**
     * Verifies existing course enrolments are preserved rather than duplicated.
     */
    public function test_existing_destination_enrolment_is_preserved(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->create_admission_course('already_enrolled');
        $this->getDataGenerator()->enrol_user((int)$student->id, (int)$course->id);
        $before = $DB->count_records('user_enrolments', ['userid' => $student->id]);
        $program = $this->configure_program('foundation_b4', $course);
        set_config('enabled', 1, 'local_shomokh_admissions');

        $application = application_service::submit((int)$student->id, (int)$program->id, false);
        $log = $DB->get_record('local_shadm_enrollog', ['userid' => $student->id], '*', MUST_EXIST);

        $this->assertSame(application_service::STATUS_ENROLLED, $application->status);
        $this->assertSame('existing', $log->ownership);
        $this->assertSame($before, $DB->count_records('user_enrolments', ['userid' => $student->id]));
    }

    /**
     * Verifies repeated enrolment failure becomes visible to staff.
     */
    public function test_exhausted_enrolment_is_marked_for_attention(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->create_admission_course('broken_manual');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, [
            'courseid' => $course->id,
            'enrol' => 'manual',
        ]);
        $program = $this->configure_program('foundation_b4', $course);
        set_config('retrylimit', 1, 'local_shomokh_admissions');
        set_config('enabled', 1, 'local_shomokh_admissions');

        $application = application_service::submit((int)$student->id, (int)$program->id, false);
        $operation = $DB->get_record('local_shadm_enrolop', [
            'applicationid' => $application->id,
        ], '*', MUST_EXIST);

        $this->assertSame(application_service::STATUS_NEEDS_ATTENTION, $application->status);
        $this->assertSame(enrolment_service::STATUS_EXHAUSTED, $operation->status);
        $this->assertNotEmpty($operation->lasterror);
        $this->assertFalse(is_enrolled(\context_course::instance((int)$course->id), (int)$student->id));
    }

    /**
     * Verifies cohort membership is added alongside course enrolment.
     */
    public function test_successful_enrolment_adds_configured_cohort(): void {
        global $DB;
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->create_admission_course('with_cohort');
        $cohort = $this->getDataGenerator()->create_cohort();
        $program = $this->configure_program('foundation_b4', $course);
        $DB->set_field('local_shadm_program', 'cohortid', $cohort->id, ['id' => $program->id]);
        set_config('enabled', 1, 'local_shomokh_admissions');

        $application = application_service::submit((int)$student->id, (int)$program->id, false);

        $this->assertSame(application_service::STATUS_ENROLLED, $application->status);
        $this->assertTrue($DB->record_exists('cohort_members', [
            'cohortid' => $cohort->id,
            'userid' => $student->id,
        ]));
    }

    /**
     * Verifies optimistic versioning prevents two concurrent decisions.
     */
    public function test_stale_review_is_rejected(): void {
        $this->prepare_test();
        $student = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $course = $this->create_admission_course('stale_review');
        $specialist = $this->configure_program('specialist_hadith', $course);
        $fallbackcourse = $this->create_admission_course('stale_fallback');
        $this->configure_program('foundation_b4', $fallbackcourse, true, true);
        $this->create_certificate_draft($student, 10007);
        set_config('enabled', 1, 'local_shomokh_admissions');
        $application = application_service::submit((int)$student->id, (int)$specialist->id, true, 10007);
        $shownversion = (int)$application->recordversion;
        application_service::review(
            (int)$application->id,
            'reject',
            '',
            '',
            (int)$reviewer->id,
            $shownversion
        );

        try {
            application_service::review(
                (int)$application->id,
                'approve',
                '',
                '',
                (int)$reviewer->id,
                $shownversion
            );
            $this->fail('A stale reviewer decision must not be applied.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidtransition', $exception->errorcode);
        }
    }
}
