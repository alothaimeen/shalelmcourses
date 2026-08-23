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
 * Readiness and public preview tests.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shomokh_admissions;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests comprehensive readiness reporting and the safe public preview.
 *
 * @package local_shomokh_admissions
 * @covers \local_shomokh_admissions\readiness_service
 * @covers \local_shomokh_admissions\dashboard_card
 * @covers \local_shomokh_admissions\program_repository
 */
final class readiness_and_preview_test extends \advanced_testcase {
    /**
     * Missing courses do not hide the remaining readiness errors.
     */
    public function test_readiness_reports_all_errors_without_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $foundation = $DB->get_record(
            'local_shadm_program',
            ['code' => 'foundation_b4'],
            '*',
            MUST_EXIST
        );
        $specialist = $DB->get_record(
            'local_shadm_program',
            ['code' => 'specialist_hadith'],
            '*',
            MUST_EXIST
        );

        $foundationerrors = readiness_service::check_program($foundation);
        $specialisterrors = readiness_service::check_program($specialist);

        $this->assertContains(get_string('readiness:nocourses', 'local_shomokh_admissions'), $foundationerrors);
        $this->assertContains(get_string('readiness:telegram', 'local_shomokh_admissions'), $foundationerrors);
        $this->assertContains(get_string('readiness:nocourses', 'local_shomokh_admissions'), $specialisterrors);
        $this->assertContains(get_string('readiness:telegram', 'local_shomokh_admissions'), $specialisterrors);
    }

    /**
     * Hidden destination courses are not ready even when manual enrolment is available.
     */
    public function test_readiness_reports_hidden_linked_course(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Hidden specialist course',
            'visible' => 0,
        ]);
        $program = $DB->get_record('local_shadm_program', ['code' => 'specialist_hadith'], '*', MUST_EXIST);
        program_repository::add_course((int)$program->id, (int)$course->id);

        $errors = readiness_service::check_program($program);

        $this->assertContains(
            get_string('readiness:hiddencourse', 'local_shomokh_admissions', $course->fullname),
            $errors
        );
    }

    /**
     * Public preview renders information without creating admission data.
     */
    public function test_public_preview_does_not_run_admission_operations(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        set_config('enabled', 0, 'local_shomokh_admissions');
        set_config('publicpreview', 1, 'local_shomokh_admissions');

        $html = dashboard_card::render();

        $this->assertStringContainsString(
            get_string('previewclosedtitle', 'local_shomokh_admissions'),
            $html
        );
        $this->assertSame(0, $DB->count_records('local_shadm_application', ['userid' => $student->id]));
        $this->assertSame(0, $DB->count_records('local_shadm_audit'));
    }

    /**
     * Available courses include unlinked courses and exclude linked courses.
     */
    public function test_available_courses_exclude_existing_program_links(): void {
        global $DB;
        $this->resetAfterTest();
        $linked = $this->getDataGenerator()->create_course(['fullname' => 'Linked course']);
        $available = $this->getDataGenerator()->create_course(['fullname' => 'Available course']);
        $program = $DB->get_record(
            'local_shadm_program',
            ['code' => 'foundation_b3'],
            '*',
            MUST_EXIST
        );
        program_repository::add_course((int)$program->id, (int)$linked->id);

        $courses = program_repository::get_available_courses((int)$program->id);

        $this->assertArrayNotHasKey((int)$linked->id, $courses);
        $this->assertArrayHasKey((int)$available->id, $courses);
        $this->assertSame('Available course', $courses[$available->id]->fullname);
    }

    /**
     * Only calculated results are offered as full-level completion sources.
     */
    public function test_level_completion_options_exclude_plain_course_totals(): void {
        global $DB;
        $this->resetAfterTest();
        $plaincourse = $this->getDataGenerator()->create_course(['fullname' => 'Plain total course']);
        $resultcourse = $this->getDataGenerator()->create_course(['fullname' => 'Level result course']);
        $plainitem = \grade_item::fetch_course_item((int)$plaincourse->id);
        $resultitem = \grade_item::fetch_course_item((int)$resultcourse->id);
        $DB->set_field('grade_items', 'calculation', '=1', ['id' => $resultitem->id]);

        $options = program_repository::get_level_completion_grade_items();

        $this->assertArrayNotHasKey((int)$plainitem->id, $options);
        $this->assertArrayHasKey((int)$resultitem->id, $options);
    }

    /**
     * Open specialist pathways require at least one valid internal level result.
     */
    public function test_global_readiness_requires_internal_completion_source(): void {
        global $DB;
        $this->resetAfterTest();
        $specialist = $DB->get_record('local_shadm_program', ['code' => 'specialist_hadith'], '*', MUST_EXIST);
        $specialist->enabled = 1;
        $specialist->registrationopen = 1;
        $specialist->telegramurl = 'https://t.me/test_hadith';
        $DB->update_record('local_shadm_program', $specialist);
        program_repository::add_course(
            (int)$specialist->id,
            (int)$this->getDataGenerator()->create_course()->id
        );
        $foundation = $DB->get_record('local_shadm_program', ['code' => 'foundation_b3'], '*', MUST_EXIST);
        $foundation->enabled = 1;
        $foundation->defaultfallback = 1;
        $DB->update_record('local_shadm_program', $foundation);

        $errors = readiness_service::check_global();
        $this->assertContains(
            get_string('readiness:nocompletionsource', 'local_shomokh_admissions'),
            $errors['global']
        );

        $resultcourse = $this->getDataGenerator()->create_course();
        $resultitem = \grade_item::fetch_course_item((int)$resultcourse->id);
        $DB->set_field('grade_items', 'calculation', '=1', ['id' => $resultitem->id]);
        $DB->set_field('local_shadm_program', 'eligibilitygradeitemid', $resultitem->id, ['id' => $foundation->id]);
        $DB->set_field('local_shadm_program', 'eligibilitymingrade', 1, ['id' => $foundation->id]);

        $errors = readiness_service::check_global();
        $this->assertArrayNotHasKey('global', $errors);
    }
}
