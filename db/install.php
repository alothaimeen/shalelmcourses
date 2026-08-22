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
 * Post-installation defaults.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Seeds the four destinations without enabling any production behaviour.
 *
 * @return void
 */
function xmldb_local_shomokh_admissions_install(): void {
    global $DB;

    $now = time();
    $programs = [
        [
            'code' => 'foundation_b3',
            'name' => 'الدبلوم التأسيسي للعلوم الشرعية — الدفعة الثالثة',
            'programtype' => 'foundation',
            'batchname' => 'الدفعة الثالثة',
            'recognizeexisting' => 1,
            'sortorder' => 10,
        ],
        [
            'code' => 'foundation_b4',
            'name' => 'الدبلوم التأسيسي للعلوم الشرعية — الدفعة الرابعة',
            'programtype' => 'foundation',
            'batchname' => 'الدفعة الرابعة',
            'defaultfallback' => 1,
            'sortorder' => 20,
        ],
        [
            'code' => 'specialist_hadith',
            'name' => 'الدبلوم التخصصي — مسار الحديث',
            'programtype' => 'specialist',
            'track' => 'hadith',
            'sortorder' => 30,
        ],
        [
            'code' => 'specialist_tafsir',
            'name' => 'الدبلوم التخصصي — مسار التفسير',
            'programtype' => 'specialist',
            'track' => 'tafsir',
            'sortorder' => 40,
        ],
    ];

    foreach ($programs as $program) {
        $record = (object)array_merge([
            'description' => null,
            'requirements' => null,
            'track' => null,
            'batchname' => null,
            'cohortid' => null,
            'telegramurl' => null,
            'enabled' => 0,
            'registrationopen' => 0,
            'recognizeexisting' => 0,
            'defaultfallback' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $program);
        $DB->insert_record('local_shadm_program', $record);
    }

    set_config('enabled', 0, 'local_shomokh_admissions');
    set_config('publicpreview', 0, 'local_shomokh_admissions');
    set_config('maxcertificatebytes', 5 * 1024 * 1024, 'local_shomokh_admissions');
    set_config('retentiondays', 90, 'local_shomokh_admissions');
    set_config('retrylimit', 5, 'local_shomokh_admissions');
    set_config('recognitionbatch', 500, 'local_shomokh_admissions');
    set_config(
        'introhtml',
        'حيّاك الله طالبتنا المباركة، سعداء بانضمامك إلى منصة شموخ العلم. هنا تبدأ خطوتك الأولى في طلب العلم الشرعي.',
        'local_shomokh_admissions'
    );
}
