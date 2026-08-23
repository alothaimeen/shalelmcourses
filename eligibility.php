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
 * Manages independent specialist eligibility groups.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/shomokh_admissions:manageprograms', $context);
$url = new moodle_url('/local/shomokh_admissions/eligibility.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('eligibility:title', 'local_shomokh_admissions'));
$PAGE->set_heading(get_string('eligibility:title', 'local_shomokh_admissions'));

$action = optional_param('action', '', PARAM_ALPHA);
$groupid = optional_param('groupid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
if ($action !== '') {
    require_sesskey();
    if ($action === 'enable' && $groupid) {
        \local_shomokh_admissions\eligibility_repository::set_group_enabled($groupid, true);
    } else if ($action === 'disable' && $groupid) {
        \local_shomokh_admissions\eligibility_repository::set_group_enabled($groupid, false);
    } else if ($action === 'removeitem' && $itemid) {
        \local_shomokh_admissions\eligibility_repository::remove_item($itemid);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$groups = \local_shomokh_admissions\eligibility_repository::get_groups();
$groupoptions = [];
foreach ($groups as $group) {
    $groupoptions[(int)$group->id] = format_string($group->name);
}
$formtype = optional_param('formtype', '', PARAM_ALPHA);
$groupform = new \local_shomokh_admissions\form\eligibility_group_form();
$itemform = $groupoptions
    ? new \local_shomokh_admissions\form\eligibility_item_form(null, ['groups' => $groupoptions])
    : null;
if ($formtype === 'group' && ($data = $groupform->get_data())) {
    \local_shomokh_admissions\eligibility_repository::create_group($data->name);
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($formtype === 'item' && $itemform && ($data = $itemform->get_data())) {
    \local_shomokh_admissions\eligibility_repository::add_item(
        (int)$data->groupid,
        (int)$data->gradeitemid,
        (float)$data->mingrade
    );
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('eligibility:explanation', 'local_shomokh_admissions'), 'info');
if (!$groups) {
    echo $OUTPUT->notification(get_string('eligibility:nogroups', 'local_shomokh_admissions'), 'warning');
} else {
    echo html_writer::start_tag('div', ['class' => 'local-shadm-grid']);
    foreach ($groups as $group) {
        $items = \local_shomokh_admissions\eligibility_repository::get_items((int)$group->id);
        $errors = \local_shomokh_admissions\eligibility_service::group_errors($group, $items);
        $content = html_writer::tag('h2', format_string($group->name));
        $content .= html_writer::tag(
            'p',
            get_string(!empty($group->enabled) ? 'eligibility:enabled' : 'eligibility:disabled', 'local_shomokh_admissions'),
            ['class' => !empty($group->enabled) ? 'text-success' : 'text-muted']
        );
        if ($errors) {
            $erroritems = array_map(static fn($error): string => html_writer::tag('li', s($error)), $errors);
            $content .= html_writer::tag('ul', implode('', $erroritems), ['class' => 'text-danger']);
        }
        if ($items) {
            $rows = [];
            foreach ($items as $item) {
                if (empty($item->coursename)) {
                    $label = get_string('readiness:missinggradeitem', 'local_shomokh_admissions')
                        . ' (#' . (int)$item->gradeitemid . ')';
                } else {
                    $label = format_string($item->categoryname) . ' / ' . format_string($item->coursename)
                        . ' — ' . format_string(
                            $item->itemname ?: get_string('coursegradeitem', 'local_shomokh_admissions')
                        )
                        . ' ≥ ' . format_float((float)$item->mingrade, 2);
                }
                $remove = $OUTPUT->single_button(new moodle_url($url, [
                    'action' => 'removeitem',
                    'itemid' => $item->id,
                ]), get_string('remove'), 'post', ['class' => 'btn btn-sm btn-outline-danger']);
                $rows[] = html_writer::tag('li', s($label) . $remove);
            }
            $content .= html_writer::tag('ul', implode('', $rows));
        }
        $toggleaction = !empty($group->enabled) ? 'disable' : 'enable';
        $content .= $OUTPUT->single_button(new moodle_url($url, [
            'action' => $toggleaction,
            'groupid' => $group->id,
        ]), get_string('eligibility:' . $toggleaction, 'local_shomokh_admissions'), 'post');
        echo html_writer::tag('article', $content, ['class' => 'local-shadm-option']);
    }
    echo html_writer::end_tag('div');
}

echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
echo html_writer::tag('h2', get_string('eligibility:additem', 'local_shomokh_admissions'));
if ($itemform) {
    $itemform->display();
} else {
    echo $OUTPUT->notification(get_string('eligibility:addgroupfirst', 'local_shomokh_admissions'), 'info');
}
echo html_writer::end_tag('section');
echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
echo html_writer::tag('h2', get_string('eligibility:addgroup', 'local_shomokh_admissions'));
$groupform->display();
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
