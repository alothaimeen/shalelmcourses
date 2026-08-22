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

namespace local_shomokh_admissions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Implements Moodle's Privacy API for admission records and files.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_shadm_application', [
            'userid' => 'privacy:metadata:userid',
            'targetprogramid' => 'privacy:metadata:program',
            'status' => 'privacy:metadata:status',
            'decisionby' => 'privacy:metadata:decision',
            'decisionnote' => 'privacy:metadata:decision',
            'studentmessage' => 'privacy:metadata:decision',
        ], 'privacy:metadata:applications');
        $collection->add_database_table('local_shadm_audit', [
            'actorid' => 'privacy:metadata:userid',
            'action' => 'privacy:metadata:audit',
        ], 'privacy:metadata:audit');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:certificate');
        return $collection;
    }

    /**
     * Returns contexts containing personal data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_shadm_application', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Adds users with application data in the system context.
     *
     * @param userlist $userlist Approved user list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_shadm_application}', []);
    }

    /**
     * Exports applications and the user's uploaded certificates.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!$contextlist->count() || !$contextlist->get_user()) {
            return;
        }
        $userid = (int)$contextlist->get_user()->id;
        $context = \context_system::instance();
        $records = $DB->get_records('local_shadm_application', ['userid' => $userid], 'timecreated ASC');
        foreach ($records as $record) {
            $data = (object)[
                'programid' => $record->targetprogramid,
                'status' => $record->status,
                'source' => $record->eligibilitysource,
                'studentmessage' => $record->studentmessage,
                'created' => transform::datetime($record->timecreated),
                'modified' => transform::datetime($record->timemodified),
            ];
            $subcontext = [get_string('applicationtitle', 'local_shomokh_admissions'), (string)$record->id];
            writer::with_context($context)->export_data($subcontext, $data);
            writer::with_context($context)->export_area_files(
                $subcontext,
                'local_shomokh_admissions',
                'certificate',
                (int)$record->id
            );
        }
    }

    /**
     * Deletes all plugin personal data in the system context.
     *
     * @param \context $context Context to delete from.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        get_file_storage()->delete_area_files($context->id, 'local_shomokh_admissions', 'certificate');
        $DB->delete_records('local_shadm_enrollog');
        $DB->delete_records('local_shadm_enrolop');
        $DB->delete_records('local_shadm_audit');
        $DB->delete_records('local_shadm_application');
    }

    /**
     * Deletes data belonging to one approved user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!$contextlist->count() || !$contextlist->get_user()) {
            return;
        }
        self::delete_users([(int)$contextlist->get_user()->id]);
    }

    /**
     * Deletes data for approved users in the system context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        self::delete_users(array_map('intval', $userlist->get_userids()));
    }

    /**
     * Deletes dependent records and files for user IDs.
     *
     * @param array $userids User IDs.
     * @return void
     */
    private static function delete_users(array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        $context = \context_system::instance();
        [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $applications = $DB->get_records_select('local_shadm_application', "userid $usersql", $params, '', 'id');
        $applicationids = array_map('intval', array_keys($applications));
        foreach ($applicationids as $applicationid) {
            get_file_storage()->delete_area_files(
                $context->id,
                'local_shomokh_admissions',
                'certificate',
                $applicationid
            );
        }
        if ($applicationids) {
            [$appsql, $appparams] = $DB->get_in_or_equal($applicationids, SQL_PARAMS_NAMED, 'app');
            $operations = $DB->get_records_select('local_shadm_enrolop', "applicationid $appsql", $appparams, '', 'id');
            $operationids = array_map('intval', array_keys($operations));
            if ($operationids) {
                [$opsql, $opparams] = $DB->get_in_or_equal($operationids, SQL_PARAMS_NAMED, 'op');
                $DB->delete_records_select('local_shadm_enrollog', "enrolopid $opsql", $opparams);
            }
            $DB->delete_records_select('local_shadm_enrolop', "applicationid $appsql", $appparams);
            $DB->delete_records_select('local_shadm_audit', "applicationid $appsql", $appparams);
            $DB->delete_records_select('local_shadm_application', "id $appsql", $appparams);
        }
        $DB->set_field_select('local_shadm_audit', 'actorid', null, "actorid $usersql", $params);
    }
}
