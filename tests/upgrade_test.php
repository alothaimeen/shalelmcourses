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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
if (!function_exists('local_shomokh_admissions_seed_legacy_graduation_rules')) {
    require_once($CFG->dirroot . '/local/shomokh_admissions/db/upgrade.php');
}

/**
 * Tests the production-safe legacy graduation migration.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Creates one calculated binary grade item.
     *
     * @param int $courseid Course ID.
     * @param string $name Item name.
     * @return \grade_item
     */
    private function create_calculated_item(int $courseid, string $name): \grade_item {
        $item = new \grade_item();
        $item->courseid = $courseid;
        $item->categoryid = null;
        $item->itemname = $name;
        $item->itemtype = 'manual';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 1;
        $item->calculation = '=1';
        $item->insert();
        return $item;
    }

    /**
     * Both required legacy batches are enabled only after exact complete matching.
     */
    public function test_seed_finds_all_legacy_graduation_conditions(): void {
        global $DB;

        $this->resetAfterTest();
        $batchone = $this->getDataGenerator()->create_course(['fullname' => 'نتيجة المستوى الثالث']);
        foreach (['اكمال دورات 1', 'اكمال دورات 2', ' اكمال الدورات 3'] as $name) {
            $this->create_calculated_item((int)$batchone->id, $name);
        }
        $batchtwo = $this->getDataGenerator()->create_course([
            'fullname' => 'نتيجة الدفعة الثانية - المستوى الثانى',
        ]);
        foreach (['اكمال الدورات1', 'اكمال الدورات2'] as $name) {
            $this->create_calculated_item((int)$batchtwo->id, $name);
        }

        \local_shomokh_admissions_seed_legacy_graduation_rules();

        $first = eligibility_repository::get_group_by_code(eligibility_repository::LEGACY_BATCH_1);
        $second = eligibility_repository::get_group_by_code(eligibility_repository::LEGACY_BATCH_2);
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame(1, (int)$first->enabled);
        $this->assertSame(1, (int)$second->enabled);
        $this->assertSame(3, $DB->count_records('local_shadm_eligitem', ['groupid' => $first->id]));
        $this->assertSame(2, $DB->count_records('local_shadm_eligitem', ['groupid' => $second->id]));
    }

    /**
     * Ambiguous evidence disables the affected group rather than guessing.
     */
    public function test_seed_fails_closed_when_a_result_is_ambiguous(): void {
        global $DB;

        $this->resetAfterTest();
        foreach ([1, 2] as $unused) {
            $course = $this->getDataGenerator()->create_course(['fullname' => 'نتيجة المستوى الثالث']);
            $this->create_calculated_item((int)$course->id, 'اكمال دورات 1');
        }

        \local_shomokh_admissions_seed_legacy_graduation_rules();

        $first = eligibility_repository::get_group_by_code(eligibility_repository::LEGACY_BATCH_1);
        $this->assertNotNull($first);
        $this->assertSame(0, (int)$first->enabled);
        $this->assertSame(0, $DB->count_records('local_shadm_eligitem', ['groupid' => $first->id]));
    }
}
