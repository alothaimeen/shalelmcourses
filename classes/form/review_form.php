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
 * Defines the reviewer decision form.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review_form extends \moodleform {
    /**
     * Defines the form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'radio',
            'decision',
            get_string('reviewdecision', 'local_shomokh_admissions'),
            get_string('decisionapprove', 'local_shomokh_admissions'),
            'approve'
        );
        $mform->addElement(
            'radio',
            'decision',
            '',
            get_string('decisionreject', 'local_shomokh_admissions'),
            'reject'
        );
        $mform->addRule('decision', get_string('required'), 'required', null, 'client');
        $mform->addElement('textarea', 'decisionnote', get_string('decisionnote', 'local_shomokh_admissions'), [
            'rows' => 3,
        ]);
        $mform->setType('decisionnote', PARAM_TEXT);
        $mform->addElement('textarea', 'studentmessage', get_string('studentmessage', 'local_shomokh_admissions'), [
            'rows' => 3,
        ]);
        $mform->setType('studentmessage', PARAM_TEXT);
        $mform->addElement('hidden', 'applicationid');
        $mform->setType('applicationid', PARAM_INT);
        $mform->addElement('hidden', 'recordversion');
        $mform->setType('recordversion', PARAM_INT);
        $this->add_action_buttons(true, get_string('savereview', 'local_shomokh_admissions'));
    }
}
