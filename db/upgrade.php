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
 * Upgrade steps for local_shomokh_admissions.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Applies database and configuration upgrades.
 *
 * @param int $oldversion Installed version.
 * @return bool
 * @package local_shomokh_admissions
 */
function xmldb_local_shomokh_admissions_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026082001) {
        // No schema change: this savepoint promotes the locally tested RC build.
        upgrade_plugin_savepoint(true, 2026082001, 'local', 'shomokh_admissions');
    }

    if ($oldversion < 2026082002) {
        // Keep the new public closed-display mode opt-in on existing sites.
        set_config('publicpreview', 0, 'local_shomokh_admissions');
        upgrade_plugin_savepoint(true, 2026082002, 'local', 'shomokh_admissions');
    }

    if ($oldversion < 2026082003) {
        // No schema change: correct the Moodle 4.5 manual recognition button rendering.
        upgrade_plugin_savepoint(true, 2026082003, 'local', 'shomokh_admissions');
    }

    if ($oldversion < 2026082004) {
        // No schema change: replace the course search round-trip with a filterable list.
        upgrade_plugin_savepoint(true, 2026082004, 'local', 'shomokh_admissions');
    }

    if ($oldversion < 2026082201) {
        // No schema change: publish the repository metadata and Moodle coding-standard cleanup.
        upgrade_plugin_savepoint(true, 2026082201, 'local', 'shomokh_admissions');
    }

    if ($oldversion < 2026082202) {
        // No schema change: preserve the selected program when an administrator links a course.
        upgrade_plugin_savepoint(true, 2026082202, 'local', 'shomokh_admissions');
    }

    if ($oldversion < 2026082300) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_shadm_program');

        $gradeitemfield = new xmldb_field(
            'eligibilitygradeitemid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            false,
            null,
            null,
            'batchname'
        );
        if (!$dbman->field_exists($table, $gradeitemfield)) {
            $dbman->add_field($table, $gradeitemfield);
        }

        $mingradefield = new xmldb_field(
            'eligibilitymingrade',
            XMLDB_TYPE_NUMBER,
            '10, 5',
            null,
            true,
            null,
            '1',
            'eligibilitygradeitemid'
        );
        if (!$dbman->field_exists($table, $mingradefield)) {
            $dbman->add_field($table, $mingradefield);
        }

        $index = new xmldb_index('eligibilitygradeitem_ix', XMLDB_INDEX_NOTUNIQUE, ['eligibilitygradeitemid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Course mappings are enrolment destinations only; discard the obsolete per-course eligibility flags.
        $DB->set_field('local_shadm_progcourse', 'eligibilityrequired', 0);

        // Preserve the existing production result mechanism when exactly one matching calculated item is found.
        $programs = $DB->get_records('local_shadm_program', ['programtype' => 'foundation']);
        foreach ($programs as $program) {
            if (empty($program->batchname)) {
                continue;
            }
            $params = [
                'itemname' => 'اكمال الدورات',
                'gradetype' => 1,
                'batchname' => '%' . $DB->sql_like_escape($program->batchname) . '%',
            ];
            $like = $DB->sql_like('c.fullname', ':batchname', false);
            $sql = "SELECT gi.id
                      FROM {grade_items} gi
                      JOIN {course} c ON c.id = gi.courseid
                     WHERE gi.itemname = :itemname
                       AND gi.gradetype = :gradetype
                       AND gi.calculation IS NOT NULL
                       AND {$like}";
            $matches = $DB->get_records_sql($sql, $params, 0, 2);
            if (count($matches) === 1) {
                $DB->set_field('local_shadm_program', 'eligibilitygradeitemid', key($matches), [
                    'id' => $program->id,
                ]);
                $DB->set_field('local_shadm_program', 'eligibilitymingrade', 1, ['id' => $program->id]);
            }
        }

        // The third batch is recognised from existing enrolments and must not accept new applications.
        $DB->set_field('local_shadm_program', 'registrationopen', 0, ['code' => 'foundation_b3']);

        // Fail closed only if the migration could not identify any enabled internal completion source.
        if (
            !$DB->record_exists_select(
                'local_shadm_program',
                'programtype = :programtype AND enabled = 1 AND eligibilitygradeitemid IS NOT NULL',
                ['programtype' => 'foundation']
            )
        ) {
            set_config('enabled', 0, 'local_shomokh_admissions');
        }

        upgrade_plugin_savepoint(true, 2026082300, 'local', 'shomokh_admissions');
    }

    return true;
}
