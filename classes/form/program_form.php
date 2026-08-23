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
 * Defines the administrative program configuration form.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class program_form extends \moodleform {
    /**
     * Defines the form.
     */
    public function definition(): void {
        global $DB;
        $mform = $this->_form;
        $program = $this->_customdata['program'];

        $mform->addElement('static', 'codeview', get_string('programcode', 'local_shomokh_admissions'), s($program->code));
        $mform->addElement('text', 'name', get_string('programname', 'local_shomokh_admissions'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $types = [
            \local_shomokh_admissions\program_repository::TYPE_FOUNDATION => get_string(
                'programtype:foundation',
                'local_shomokh_admissions'
            ),
            \local_shomokh_admissions\program_repository::TYPE_SPECIALIST => get_string(
                'programtype:specialist',
                'local_shomokh_admissions'
            ),
        ];
        $mform->addElement(
            'static',
            'typeview',
            get_string('programtype', 'local_shomokh_admissions'),
            $types[$program->programtype] ?? s($program->programtype)
        );
        $mform->addElement('hidden', 'programtype', $program->programtype);
        $mform->setType('programtype', PARAM_ALPHANUMEXT);

        $tracks = [
            '' => get_string('track:none', 'local_shomokh_admissions'),
            'hadith' => get_string('track:hadith', 'local_shomokh_admissions'),
            'tafsir' => get_string('track:tafsir', 'local_shomokh_admissions'),
        ];
        $mform->addElement(
            'static',
            'trackview',
            get_string('track', 'local_shomokh_admissions'),
            $tracks[$program->track ?? ''] ?? s((string)$program->track)
        );
        $mform->addElement('hidden', 'track', $program->track ?? '');
        $mform->setType('track', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'batchname', get_string('batchname', 'local_shomokh_admissions'), ['size' => 40]);
        $mform->setType('batchname', PARAM_TEXT);
        $mform->addElement('textarea', 'description', get_string('description', 'local_shomokh_admissions'), [
            'rows' => 4,
            'cols' => 70,
        ]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('textarea', 'requirements', get_string('requirements', 'local_shomokh_admissions'), [
            'rows' => 6,
            'cols' => 70,
        ]);
        $mform->setType('requirements', PARAM_TEXT);

        if ($program->programtype === \local_shomokh_admissions\program_repository::TYPE_FOUNDATION) {
            $gradeitems = [0 => get_string('nolevelcompletiongradeitem', 'local_shomokh_admissions')];
            foreach (\local_shomokh_admissions\program_repository::get_level_completion_grade_items() as $item) {
                $itemname = $item->itemname ?: get_string('coursegradeitem', 'local_shomokh_admissions');
                $gradeitems[(int)$item->id] = format_string($item->categoryname)
                    . ' / ' . format_string($item->coursename)
                    . ' — ' . format_string($itemname);
            }
            $mform->addElement(
                'autocomplete',
                'eligibilitygradeitemid',
                get_string('levelcompletiongradeitem', 'local_shomokh_admissions'),
                $gradeitems,
                ['noselectionstring' => get_string('nolevelcompletiongradeitem', 'local_shomokh_admissions')]
            );
            $mform->setType('eligibilitygradeitemid', PARAM_INT);
            $mform->addHelpButton(
                'eligibilitygradeitemid',
                'levelcompletiongradeitem',
                'local_shomokh_admissions'
            );
            $mform->addElement(
                'text',
                'eligibilitymingrade',
                get_string('levelcompletionthreshold', 'local_shomokh_admissions'),
                ['size' => 10]
            );
            $mform->setType('eligibilitymingrade', PARAM_FLOAT);
            $mform->setDefault('eligibilitymingrade', 1);
            $mform->addHelpButton(
                'eligibilitymingrade',
                'levelcompletionthreshold',
                'local_shomokh_admissions'
            );
        } else {
            $mform->addElement('hidden', 'eligibilitygradeitemid', 0);
            $mform->setType('eligibilitygradeitemid', PARAM_INT);
            $mform->addElement('hidden', 'eligibilitymingrade', 1);
            $mform->setType('eligibilitymingrade', PARAM_FLOAT);
        }

        $cohorts = [0 => get_string('nocohort', 'local_shomokh_admissions')];
        foreach ($DB->get_records('cohort', null, 'name ASC', 'id,name') as $cohort) {
            $cohorts[(int)$cohort->id] = format_string($cohort->name);
        }
        $mform->addElement('autocomplete', 'cohortid', get_string('cohort', 'local_shomokh_admissions'), $cohorts, [
            'noselectionstring' => get_string('nocohort', 'local_shomokh_admissions'),
        ]);
        $mform->setType('cohortid', PARAM_INT);
        $mform->addElement('text', 'telegramurl', get_string('telegramurl', 'local_shomokh_admissions'), ['size' => 60]);
        $mform->setType('telegramurl', PARAM_URL);

        $mform->addElement('advcheckbox', 'enabled', get_string('programenabled', 'local_shomokh_admissions'));
        $mform->addElement('advcheckbox', 'registrationopen', get_string('registrationopen', 'local_shomokh_admissions'));
        $mform->addElement('advcheckbox', 'recognizeexisting', get_string('recognizeexisting', 'local_shomokh_admissions'));
        if ($program->programtype === \local_shomokh_admissions\program_repository::TYPE_FOUNDATION) {
            $mform->addElement('advcheckbox', 'defaultfallback', get_string(
                'defaultfallback',
                'local_shomokh_admissions'
            ));
        } else {
            $mform->addElement('hidden', 'defaultfallback', 0);
            $mform->setType('defaultfallback', PARAM_BOOL);
        }

        $mform->addElement('hidden', 'id', (int)$program->id);
        $mform->setType('id', PARAM_INT);
        $this->add_action_buttons(true, get_string('saveprogram', 'local_shomokh_admissions'));
    }

    /**
     * Validates linked entities and Telegram URLs.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if (!empty($data['cohortid']) && !$DB->record_exists('cohort', ['id' => (int)$data['cohortid']])) {
            $errors['cohortid'] = get_string('readiness:missingcohort', 'local_shomokh_admissions');
        }
        if (
            !empty($data['telegramurl']) && !\local_shomokh_admissions\readiness_service::valid_telegram_url(
                $data['telegramurl']
            )
        ) {
            $errors['telegramurl'] = get_string('readiness:telegram', 'local_shomokh_admissions');
        }
        if (!empty($data['eligibilitygradeitemid'])) {
            $item = $DB->get_record('grade_items', ['id' => (int)$data['eligibilitygradeitemid']]);
            if (!$item || (int)$item->gradetype !== 1 || $item->calculation === null) {
                $errors['eligibilitygradeitemid'] = get_string(
                    'readiness:missinggradeitem',
                    'local_shomokh_admissions'
                );
            } else if (
                !isset($data['eligibilitymingrade'])
                || (float)$data['eligibilitymingrade'] < (float)$item->grademin
                || (float)$data['eligibilitymingrade'] > (float)$item->grademax
            ) {
                $errors['eligibilitymingrade'] = get_string(
                    'readiness:invalidthreshold',
                    'local_shomokh_admissions'
                );
            }
        }
        return $errors;
    }
}
