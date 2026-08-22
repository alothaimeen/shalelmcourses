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

namespace local_shomokh_admissions\form;

/**
 * Tests the administrative course-linking form.
 *
 * @covers     \local_shomokh_admissions\form\course_add_form
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_add_form_test extends \advanced_testcase {
    /**
     * Ensures submitting the form preserves the required program ID.
     */
    public function test_form_preserves_required_program_id(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $programid = 123;
        $url = new \moodle_url('/local/shomokh_admissions/program.php', ['id' => $programid]);
        $PAGE->set_url($url);
        $form = new course_add_form($url, [
            'programid' => $programid,
            'options' => [456 => 'Regression test course'],
        ]);

        ob_start();
        $form->display();
        $html = ob_get_clean();
        $this->assertStringContainsString('name="id"', $html);
        $this->assertStringContainsString('value="123"', $html);
    }
}
