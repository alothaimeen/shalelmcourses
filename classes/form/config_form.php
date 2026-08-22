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
 * General settings form.
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
 * General operational settings form.
 *
 * @package local_shomokh_admissions
 */
final class config_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('advcheckbox', 'publicpreview', get_string(
            'publicpreview',
            'local_shomokh_admissions'
        ));
        $mform->addHelpButton('publicpreview', 'publicpreview', 'local_shomokh_admissions');
        $mform->addElement('advcheckbox', 'enabled', get_string('configenabled', 'local_shomokh_admissions'));
        $mform->addElement('textarea', 'introhtml', get_string('introhtml', 'local_shomokh_admissions'), [
            'rows' => 5,
            'cols' => 80,
        ]);
        $mform->setType('introhtml', PARAM_TEXT);
        $mform->addRule('introhtml', get_string('required'), 'required', null, 'client');
        $mform->addElement('select', 'maxcertificatebytes', get_string(
            'maxcertificatebytes',
            'local_shomokh_admissions'
        ), [
            2 * 1024 * 1024 => '2 MB',
            5 * 1024 * 1024 => '5 MB',
            10 * 1024 * 1024 => '10 MB',
        ]);
        $mform->setType('maxcertificatebytes', PARAM_INT);
        $mform->addElement('text', 'retentiondays', get_string('retentiondays', 'local_shomokh_admissions'));
        $mform->setType('retentiondays', PARAM_INT);
        $mform->addRule('retentiondays', get_string('required'), 'required', null, 'client');
        $mform->addElement('text', 'retrylimit', get_string('retrylimit', 'local_shomokh_admissions'));
        $mform->setType('retrylimit', PARAM_INT);
        $mform->addRule('retrylimit', get_string('required'), 'required', null, 'client');
        $mform->addElement('text', 'recognitionbatch', get_string('recognitionbatch', 'local_shomokh_admissions'));
        $mform->setType('recognitionbatch', PARAM_INT);
        $mform->addRule('recognitionbatch', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(true, get_string('saveconfig', 'local_shomokh_admissions'));
    }

    /**
     * Validates ranges and readiness before activation.
     *
     * @param array $data Submitted form values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)$data['retentiondays'] < 7 || (int)$data['retentiondays'] > 3650) {
            $errors['retentiondays'] = get_string('invaliddata');
        }
        if ((int)$data['retrylimit'] < 1 || (int)$data['retrylimit'] > 10) {
            $errors['retrylimit'] = get_string('invaliddata');
        }
        if ((int)$data['recognitionbatch'] < 50 || (int)$data['recognitionbatch'] > 2000) {
            $errors['recognitionbatch'] = get_string('invaliddata');
        }
        if (!empty($data['enabled']) && \local_shomokh_admissions\readiness_service::check_global()) {
            $errors['enabled'] = get_string('cannotenable', 'local_shomokh_admissions');
        }
        return $errors;
    }
}
