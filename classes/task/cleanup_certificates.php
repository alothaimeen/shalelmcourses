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

namespace local_shomokh_admissions\task;

/**
 * Deletes external certificates after the configured retention period.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cleanup_certificates extends \core\task\scheduled_task {
    /**
     * Returns the translated task name.
     *
     * @return string Task name.
     */
    public function get_name(): string {
        return get_string('task:cleanupcertificates', 'local_shomokh_admissions');
    }

    /**
     * Executes retention cleanup without deleting application decisions.
     */
    public function execute(): void {
        global $DB;
        $days = max(7, (int)get_config('local_shomokh_admissions', 'retentiondays'));
        $cutoff = time() - ($days * DAYSECS);
        $params = [
            'external' => \local_shomokh_admissions\application_service::SOURCE_EXTERNAL,
            'enrolled' => \local_shomokh_admissions\application_service::STATUS_ENROLLED,
            'declined' => \local_shomokh_admissions\application_service::STATUS_FALLBACK_DECLINED,
            'cutoff' => $cutoff,
        ];
        $sql = "SELECT id
                  FROM {local_shadm_application}
                 WHERE eligibilitysource = :external
                   AND status IN (:enrolled, :declined)
                   AND timemodified < :cutoff";
        $applications = $DB->get_records_sql($sql, $params);
        $context = \context_system::instance();
        $storage = get_file_storage();
        $deleted = 0;
        foreach ($applications as $application) {
            if (
                $storage->get_area_files(
                    $context->id,
                    'local_shomokh_admissions',
                    'certificate',
                    (int)$application->id,
                    'id',
                    false
                )
            ) {
                $storage->delete_area_files(
                    $context->id,
                    'local_shomokh_admissions',
                    'certificate',
                    (int)$application->id
                );
                \local_shomokh_admissions\audit_service::record(
                    (int)$application->id,
                    null,
                    'certificate_retention_deleted'
                );
                $deleted++;
            }
        }
        mtrace('Shomokh admissions certificates deleted by retention: ' . $deleted);
    }
}
