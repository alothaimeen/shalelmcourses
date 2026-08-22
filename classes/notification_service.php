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

namespace local_shomokh_admissions;

/**
 * Sends non-blocking Moodle notifications for application changes.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_service {
    /**
     * Sends a status update; delivery failure never rolls back admission data.
     *
     * @param int $userid Recipient.
     * @param string $messagekey Language key suffix.
     * @return void
     */
    public static function send(int $userid, string $messagekey): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/messagelib.php');
        try {
            $userto = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
            $text = get_string('notify:' . $messagekey, 'local_shomokh_admissions');
            $message = new \core\message\message();
            $message->component = 'local_shomokh_admissions';
            $message->name = 'status_update';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $userto;
            $message->subject = get_string('notify:subject', 'local_shomokh_admissions');
            $message->fullmessage = $text;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', s($text));
            $message->smallmessage = $text;
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/local/shomokh_admissions/status.php'))->out(false);
            $message->contexturlname = get_string('viewstatus', 'local_shomokh_admissions');
            message_send($message);
        } catch (\Throwable $exception) {
            // Notifications are deliberately best-effort. In development mode,
            // debugging() raises E_USER_NOTICE and can replace an otherwise
            // successful application response with an error page. Keep the
            // diagnostic in the server log without exposing it to the student.
            debugging(
                '[local_shomokh_admissions] Status notification failed (' . get_class($exception) . ').',
                DEBUG_DEVELOPER
            );
        }
    }
}
