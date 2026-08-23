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
 * Form for adding one required condition to an eligibility group.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligibility_item_form extends \moodleform {
    /**
     * Defines the form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $groups = $this->_customdata['groups'];
        $gradeitems = [0 => get_string('nolevelcompletiongradeitem', 'local_shomokh_admissions')];
        foreach (\local_shomokh_admissions\program_repository::get_level_completion_grade_items() as $item) {
            $itemname = $item->itemname ?: get_string('coursegradeitem', 'local_shomokh_admissions');
            $gradeitems[(int)$item->id] = format_string($item->categoryname)
                . ' / ' . format_string($item->coursename)
                . ' — ' . format_string($itemname);
        }

        $mform->addElement('select', 'groupid', get_string('eligibility:group', 'local_shomokh_admissions'), $groups);
        $mform->setType('groupid', PARAM_INT);
        $mform->addElement(
            'autocomplete',
            'gradeitemid',
            get_string('eligibility:gradeitem', 'local_shomokh_admissions'),
            $gradeitems,
            ['noselectionstring' => get_string('nolevelcompletiongradeitem', 'local_shomokh_admissions')]
        );
        $mform->setType('gradeitemid', PARAM_INT);
        $mform->addRule('gradeitemid', get_string('required'), 'required', null, 'client');
        $mform->addElement('text', 'mingrade', get_string('eligibility:mingrade', 'local_shomokh_admissions'), [
            'size' => 10,
        ]);
        $mform->setType('mingrade', PARAM_FLOAT);
        $mform->setDefault('mingrade', 1);
        $mform->addRule('mingrade', get_string('required'), 'required', null, 'client');
        $mform->addElement('hidden', 'formtype', 'item');
        $mform->setType('formtype', PARAM_ALPHA);
        $this->add_action_buttons(false, get_string('eligibility:additem', 'local_shomokh_admissions'));
    }

    /**
     * Validates the selected calculated grade item and threshold.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);
        if (empty($data['groupid']) || !$DB->record_exists('local_shadm_eliggroup', ['id' => $data['groupid']])) {
            $errors['groupid'] = get_string('invaliddata');
        }
        $item = empty($data['gradeitemid']) ? null : $DB->get_record('grade_items', [
            'id' => (int)$data['gradeitemid'],
        ]);
        if (!$item || (int)$item->gradetype !== 1 || $item->calculation === null) {
            $errors['gradeitemid'] = get_string('readiness:missinggradeitem', 'local_shomokh_admissions');
        } else if (
            !isset($data['mingrade'])
            || (float)$data['mingrade'] < (float)$item->grademin
            || (float)$data['mingrade'] > (float)$item->grademax
        ) {
            $errors['mingrade'] = get_string('readiness:invalidthreshold', 'local_shomokh_admissions');
        }
        return $errors;
    }
}
