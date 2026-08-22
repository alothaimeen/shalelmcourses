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

namespace local_shomokh_admissions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * Tests Privacy API coverage for admission records and qualification files.
 *
 * @covers     \local_shomokh_admissions\privacy\provider
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Creates one minimal application and attached private file.
     *
     * @param \stdClass $user Application owner.
     * @return \stdClass Created application.
     */
    private function create_application(\stdClass $user): \stdClass {
        global $DB;
        $program = $DB->get_record('local_shadm_program', ['code' => 'specialist_hadith'], '*', MUST_EXIST);
        $now = time();
        $id = $DB->insert_record('local_shadm_application', (object)[
            'userid' => $user->id,
            'targetprogramid' => $program->id,
            'fallbackprogramid' => null,
            'enrolledprogramid' => null,
            'eligibilitysource' => \local_shomokh_admissions\application_service::SOURCE_EXTERNAL,
            'status' => \local_shomokh_admissions\application_service::STATUS_PENDING,
            'decisionby' => null,
            'decisionnote' => null,
            'studentmessage' => 'Application message',
            'timedecided' => null,
            'timeenrolled' => null,
            'recordversion' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_shomokh_admissions',
            'filearea' => 'certificate',
            'itemid' => $id,
            'filepath' => '/',
            'filename' => 'private.pdf',
        ], '%PDF-1.4 private test');
        return $DB->get_record('local_shadm_application', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Verifies metadata declares the stored tables and private file subsystem.
     */
    public function test_metadata_and_user_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_application($user);

        $metadata = provider::get_metadata(new collection('local_shomokh_admissions'));
        $contexts = provider::get_contexts_for_userid((int)$user->id);

        $this->assertCount(3, $metadata->get_collection());
        $this->assertCount(1, $contexts);
        $this->assertSame(\context_system::instance()->id, $contexts->current()->id);
    }

    /**
     * Verifies student data and qualification files can be exported.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_application($user);
        $context = \context_system::instance();

        $this->export_context_data_for_user((int)$user->id, $context, 'local_shomokh_admissions');

        $this->assertTrue(writer::with_context($context)->has_any_data());
    }

    /**
     * Verifies deletion removes the user's application and qualification file.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $application = $this->create_application($user);
        $context = \context_system::instance();
        $contextlist = new approved_contextlist(
            $user,
            'local_shomokh_admissions',
            [$context->id]
        );

        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('local_shadm_application', ['id' => $application->id]));
        $this->assertEmpty(get_file_storage()->get_area_files(
            $context->id,
            'local_shomokh_admissions',
            'certificate',
            (int)$application->id,
            'id ASC',
            false
        ));
    }
}
