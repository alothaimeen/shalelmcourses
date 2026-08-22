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
 * Site administration navigation.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_shomokh_admissions_category',
        get_string('pluginname', 'local_shomokh_admissions')
    ));
    $ADMIN->add('local_shomokh_admissions_category', new admin_externalpage(
        'local_shomokh_admissions_dashboard',
        get_string('admindashboard', 'local_shomokh_admissions'),
        new moodle_url('/local/shomokh_admissions/manage.php'),
        'local/shomokh_admissions:viewreports'
    ));
    $ADMIN->add('local_shomokh_admissions_category', new admin_externalpage(
        'local_shomokh_admissions_programs',
        get_string('programs', 'local_shomokh_admissions'),
        new moodle_url('/local/shomokh_admissions/programs.php'),
        'local/shomokh_admissions:manageprograms'
    ));
    $ADMIN->add('local_shomokh_admissions_category', new admin_externalpage(
        'local_shomokh_admissions_config',
        get_string('generalsettings', 'local_shomokh_admissions'),
        new moodle_url('/local/shomokh_admissions/config.php'),
        'local/shomokh_admissions:manageprograms'
    ));
}
