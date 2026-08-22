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
 * Tests recognition of legacy students from their current Moodle enrolments.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recognition_service_test extends \advanced_testcase {
    /**
     * Verifies existing enrolments create durable recognition without enrolment changes.
     */
    public function test_existing_student_is_recognised_without_application(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int)$student->id, (int)$course->id);
        $program = $DB->get_record('local_shadm_program', ['code' => 'foundation_b3'], '*', MUST_EXIST);
        $program->enabled = 1;
        $program->recognizeexisting = 1;
        $DB->update_record('local_shadm_program', $program);
        program_repository::add_course((int)$program->id, (int)$course->id);
        $before = $DB->count_records('user_enrolments', ['userid' => $student->id]);

        $applications = recognition_service::recognise_user((int)$student->id);
        $applicationsagain = recognition_service::recognise_user((int)$student->id);

        $this->assertCount(1, $applications);
        $this->assertCount(1, $applicationsagain);
        $application = reset($applications);
        $this->assertSame(application_service::STATUS_RECOGNISED, $application->status);
        $this->assertSame(application_service::SOURCE_RECOGNISED, $application->eligibilitysource);
        $this->assertSame($before, $DB->count_records('user_enrolments', ['userid' => $student->id]));
        $this->assertSame(1, $DB->count_records('local_shadm_application', ['userid' => $student->id]));
    }

    /**
     * Verifies scheduled scans are bounded and throttled for one day.
     */
    public function test_batch_recognition_is_bounded_and_throttled(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('recognitionbatch', 50, 'local_shomokh_admissions');
        $course = $this->getDataGenerator()->create_course();
        $program = $DB->get_record('local_shadm_program', ['code' => 'foundation_b3'], '*', MUST_EXIST);
        $program->enabled = 1;
        $program->recognizeexisting = 1;
        $DB->update_record('local_shadm_program', $program);
        program_repository::add_course((int)$program->id, (int)$course->id);
        for ($index = 0; $index < 3; $index++) {
            $student = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user((int)$student->id, (int)$course->id);
        }

        $processed = recognition_service::run_batch();
        $processedagain = recognition_service::run_batch();
        $forced = recognition_service::run_batch(true);

        $this->assertSame(3, $processed);
        $this->assertSame(0, $processedagain);
        $this->assertSame(3, $forced);
        $this->assertSame(3, $DB->count_records('local_shadm_application', [
            'targetprogramid' => $program->id,
        ]));
    }
}
