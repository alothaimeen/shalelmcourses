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

    if ($oldversion < 2026082301) {
        // No schema change: readiness now rejects hidden enrolment destinations.
        upgrade_plugin_savepoint(true, 2026082301, 'local', 'shomokh_admissions');
    }

    if ($oldversion < 2026082302) {
        $dbman = $DB->get_manager();

        $grouptable = new xmldb_table('local_shadm_eliggroup');
        $grouptable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $grouptable->add_field('code', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $grouptable->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $grouptable->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $grouptable->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $grouptable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $grouptable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $grouptable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $grouptable->add_index('code_uix', XMLDB_INDEX_UNIQUE, ['code']);
        $grouptable->add_index('enabled_sort_ix', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'sortorder']);
        if (!$dbman->table_exists($grouptable)) {
            $dbman->create_table($grouptable);
        }

        $itemtable = new xmldb_table('local_shadm_eligitem');
        $itemtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $itemtable->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $itemtable->add_field('gradeitemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $itemtable->add_field('mingrade', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '1');
        $itemtable->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $itemtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $itemtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $itemtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $itemtable->add_key('group_fk', XMLDB_KEY_FOREIGN, ['groupid'], 'local_shadm_eliggroup', ['id']);
        $itemtable->add_index('groupitem_uix', XMLDB_INDEX_UNIQUE, ['groupid', 'gradeitemid']);
        $itemtable->add_index('gradeitem_ix', XMLDB_INDEX_NOTUNIQUE, ['gradeitemid']);
        if (!$dbman->table_exists($itemtable)) {
            $dbman->create_table($itemtable);
        }

        // Specialist eligibility is independent from the four enrolment destinations.
        // Explicitly discard the migrated third-batch level-one condition.
        $DB->set_field('local_shadm_program', 'eligibilitygradeitemid', null);
        $DB->set_field('local_shadm_program', 'eligibilitymingrade', 1);

        local_shomokh_admissions_seed_legacy_graduation_rules();
        upgrade_plugin_savepoint(true, 2026082302, 'local', 'shomokh_admissions');
    }

    return true;
}

/**
 * Seeds the two manager-approved legacy graduation alternatives.
 *
 * The migration enables a group only when every expected calculated result
 * is found exactly once. Ambiguity therefore fails closed and remains visible
 * on the eligibility administration page.
 *
 * @return void
 */
function local_shomokh_admissions_seed_legacy_graduation_rules(): void {
    global $DB;

    $definitions = [
        [
            'code' => 'foundation_graduates_b1',
            'name' => 'خريجات الدبلوم التأسيسي — الدفعة الأولى',
            'course' => 'نتيجة المستوى الثالث',
            'items' => ['اكمال دورات 1', 'اكمال دورات 2', 'اكمال الدورات 3'],
            'sortorder' => 10,
        ],
        [
            'code' => 'foundation_graduates_b2',
            'name' => 'خريجات الدبلوم التأسيسي — الدفعة الثانية',
            'course' => 'نتيجة الدفعة الثانية - المستوى الثانى',
            'items' => ['اكمال الدورات1', 'اكمال الدورات2'],
            'sortorder' => 20,
        ],
    ];
    $sql = "SELECT gi.id, gi.itemname, c.fullname AS coursename
              FROM {grade_items} gi
              JOIN {course} c ON c.id = gi.courseid
             WHERE gi.gradetype = :gradetype
               AND gi.calculation IS NOT NULL
               AND gi.itemname IS NOT NULL";
    $candidates = $DB->get_records_sql($sql, ['gradetype' => 1]);
    $now = time();

    foreach ($definitions as $definition) {
        $group = $DB->get_record('local_shadm_eliggroup', ['code' => $definition['code']]);
        if (!$group) {
            $group = (object)[
                'code' => $definition['code'],
                'name' => $definition['name'],
                'enabled' => 0,
                'sortorder' => $definition['sortorder'],
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $group->id = $DB->insert_record('local_shadm_eliggroup', $group);
        }

        $matchedids = [];
        foreach ($definition['items'] as $expecteditem) {
            $matches = [];
            foreach ($candidates as $candidate) {
                if (
                    local_shomokh_admissions_normalise_result_label($candidate->coursename)
                        === local_shomokh_admissions_normalise_result_label($definition['course'])
                    && local_shomokh_admissions_normalise_result_label($candidate->itemname)
                        === local_shomokh_admissions_normalise_result_label($expecteditem)
                ) {
                    $matches[] = (int)$candidate->id;
                }
            }
            if (count($matches) !== 1) {
                $matchedids = [];
                break;
            }
            $matchedids[] = reset($matches);
        }

        $DB->delete_records('local_shadm_eligitem', ['groupid' => $group->id]);
        foreach ($matchedids as $index => $gradeitemid) {
            $DB->insert_record('local_shadm_eligitem', (object)[
                'groupid' => $group->id,
                'gradeitemid' => $gradeitemid,
                'mingrade' => 1,
                'sortorder' => ($index + 1) * 10,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $DB->set_field(
            'local_shadm_eliggroup',
            'enabled',
            count($matchedids) === count($definition['items']) ? 1 : 0,
            ['id' => $group->id]
        );
        $DB->set_field('local_shadm_eliggroup', 'timemodified', $now, ['id' => $group->id]);
    }
}

/**
 * Canonicalises known Arabic result labels for strict migration matching.
 *
 * @param string $value Label.
 * @return string
 */
function local_shomokh_admissions_normalise_result_label(string $value): string {
    $value = str_replace(["\u{00A0}", 'أ', 'إ', 'آ', 'ى'], ['', 'ا', 'ا', 'ا', 'ي'], trim($value));
    return (string)preg_replace('/\s+/u', '', $value);
}
