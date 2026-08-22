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
 * Searchable course linking form.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shomokh_admissions\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Adds one course from a searchable list of available courses.
 *
 * @package local_shomokh_admissions
 */
final class course_add_form extends \moodleform {
    /**
     * Defines the course linking form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $options = $this->_customdata['options'] ?? [];
        $selectoptions = ['' => get_string('choosecourse', 'local_shomokh_admissions')] + $options;
        $mform->addElement('autocomplete', 'courseid', get_string(
            'availablecourses',
            'local_shomokh_admissions',
            count($options)
        ), $selectoptions, [
            'multiple' => false,
            'noselectionstring' => get_string('choosecourse', 'local_shomokh_admissions'),
        ]);
        $mform->setType('courseid', PARAM_INT);
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');
        $mform->addElement('hidden', 'programid', (int)$this->_customdata['programid']);
        $mform->setType('programid', PARAM_INT);
        $mform->addElement('submit', 'addcourse', get_string('addcourse', 'local_shomokh_admissions'));
    }

    /**
     * Prevents submitting the placeholder as a course choice.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['courseid'])) {
            $errors['courseid'] = get_string('required');
        }

        return $errors;
    }
}
