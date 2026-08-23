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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding one alternative eligibility group.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligibility_group_form extends \moodleform {
    /**
     * Defines the form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('eligibility:groupname', 'local_shomokh_admissions'), [
            'size' => 50,
        ]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addElement('hidden', 'formtype', 'group');
        $mform->setType('formtype', PARAM_ALPHA);
        $this->add_action_buttons(false, get_string('eligibility:addgroup', 'local_shomokh_admissions'));
    }
}
