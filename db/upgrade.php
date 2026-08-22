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

    return true;
}
