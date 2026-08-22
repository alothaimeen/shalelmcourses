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
 * Short, mobile-friendly student application form.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class application_form extends \moodleform {
    /** @var array File manager options. */
    private array $fileoptions = [];

    /**
     * Defines the form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $program = $this->_customdata['program'];
        $external = !empty($this->_customdata['external']);
        $this->fileoptions = $this->_customdata['fileoptions'] ?? [];

        $mform->addElement('header', 'applicationheader', format_string($program->name));
        if (!empty($program->description)) {
            $mform->addElement(
                'static',
                'descriptiontext',
                get_string('description', 'local_shomokh_admissions'),
                format_text($program->description, FORMAT_MOODLE)
            );
        }
        if (!empty($program->requirements)) {
            $mform->addElement(
                'static',
                'requirementstext',
                get_string('requirements', 'local_shomokh_admissions'),
                format_text($program->requirements, FORMAT_MOODLE)
            );
        }
        $mform->addElement('static', 'eligibilitymessage', '', get_string(
            $external ? 'externalrequired' : 'internaleligible',
            'local_shomokh_admissions'
        ));

        if ($external) {
            $mform->addElement(
                'filemanager',
                'certificate',
                get_string('certificate', 'local_shomokh_admissions'),
                null,
                $this->fileoptions
            );
            $mform->addHelpButton('certificate', 'certificate', 'local_shomokh_admissions');
        }

        $mform->addElement('advcheckbox', 'confirmconditions', '', get_string(
            $external ? 'confirmconditions' : 'confirmconditionsinternal',
            'local_shomokh_admissions'
        ));
        $mform->addRule('confirmconditions', get_string(
            'confirmconditionsrequired',
            'local_shomokh_admissions'
        ), 'required', null, 'client');

        $mform->addElement('hidden', 'programid', (int)$program->id);
        $mform->setType('programid', PARAM_INT);
        $mform->addElement('hidden', 'external', $external ? 1 : 0);
        $mform->setType('external', PARAM_BOOL);
        $this->add_action_buttons(true, get_string('submitapplication', 'local_shomokh_admissions'));
    }

    /**
     * Performs server-side consent and certificate validation.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        global $USER;
        $errors = parent::validation($data, $files);
        if (empty($data['confirmconditions'])) {
            $errors['confirmconditions'] = get_string(
                'confirmconditionsrequired',
                'local_shomokh_admissions'
            );
        }
        if (empty($this->_customdata['external'])) {
            return $errors;
        }

        $draftitemid = (int)($data['certificate'] ?? 0);
        $usercontext = \context_user::instance((int)$USER->id);
        $fileservice = get_file_storage();
        $draftfiles = $fileservice->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            'id ASC',
            false
        );
        if (count($draftfiles) !== 1) {
            $errors['certificate'] = get_string('certificatefilecount', 'local_shomokh_admissions');
            return $errors;
        }

        $file = reset($draftfiles);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        $maxbytes = max(1024, (int)get_config('local_shomokh_admissions', 'maxcertificatebytes'));
        if (!in_array($file->get_mimetype(), $allowed, true) || $file->get_filesize() > $maxbytes) {
            $errors['certificate'] = get_string('invalidcertificate', 'local_shomokh_admissions');
        }
        return $errors;
    }
}
