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

namespace local_shomokh_admissions\hook;

/**
 * Provides dashboard integration hooks for Moodle 4.5 and later.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class output {
    /**
     * Places the admissions card before a potentially long dashboard.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook Output hook.
     * @return void
     */
    public static function before_standard_body(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE;
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return;
        }
        if (!in_array($PAGE->url->get_path(), ['/my/', '/my/index.php'], true)) {
            return;
        }
        $html = \local_shomokh_admissions\dashboard_card::render();
        if ($html !== '') {
            $hook->add_html($html);
        }
    }
}
