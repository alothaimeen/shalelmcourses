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
 * Admissions management dashboard.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/shomokh_admissions:viewreports', $context);
$recognitionprograms = array_filter(
    \local_shomokh_admissions\program_repository::get_all(true),
    static fn($program): bool => !empty($program->recognizeexisting)
);

$action = optional_param('action', '', PARAM_ALPHA);
$operationid = optional_param('operationid', 0, PARAM_INT);
if ($action !== '') {
    require_sesskey();
    if ($action === 'sync') {
        require_capability('local/shomokh_admissions:sync', $context);
        if (!$recognitionprograms) {
            redirect(
                new moodle_url('/local/shomokh_admissions/manage.php'),
                get_string('syncunavailable', 'local_shomokh_admissions'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        $processed = \local_shomokh_admissions\recognition_service::run_batch(true);
        redirect(
            new moodle_url('/local/shomokh_admissions/manage.php'),
            get_string('synccomplete', 'local_shomokh_admissions', $processed),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if ($action === 'retry' && $operationid) {
        require_capability('local/shomokh_admissions:sync', $context);
        \local_shomokh_admissions\enrolment_service::retry($operationid, (int)$USER->id);
        \local_shomokh_admissions\enrolment_service::process($operationid);
        redirect(
            new moodle_url('/local/shomokh_admissions/manage.php'),
            get_string('operationqueued', 'local_shomokh_admissions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$status = optional_param('status', \local_shomokh_admissions\application_service::STATUS_PENDING, PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;
$validstatuses = [
    \local_shomokh_admissions\application_service::STATUS_PENDING,
    \local_shomokh_admissions\application_service::STATUS_APPROVED,
    \local_shomokh_admissions\application_service::STATUS_ENROLLED,
    \local_shomokh_admissions\application_service::STATUS_REJECTED_OFFER,
    \local_shomokh_admissions\application_service::STATUS_FALLBACK_ACCEPTED,
    \local_shomokh_admissions\application_service::STATUS_FALLBACK_DECLINED,
    \local_shomokh_admissions\application_service::STATUS_NEEDS_ATTENTION,
    \local_shomokh_admissions\application_service::STATUS_RECOGNISED,
];
if ($status !== 'all' && !in_array($status, $validstatuses, true)) {
    $status = \local_shomokh_admissions\application_service::STATUS_PENDING;
}

$where = '';
$params = [];
if ($status !== 'all') {
    $where = 'WHERE a.status = :status';
    $params['status'] = $status;
}
$countsql = "SELECT COUNT(1) FROM {local_shadm_application} a $where";
$total = $DB->count_records_sql($countsql, $params);
$sql = "SELECT a.*, a.id AS applicationid, p.name AS programname, p.code AS programcode,
               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename, op.id AS operationid, op.lasterror
          FROM {local_shadm_application} a
          JOIN {local_shadm_program} p ON p.id = a.targetprogramid
          JOIN {user} u ON u.id = a.userid
     LEFT JOIN {local_shadm_enrolop} op ON op.applicationid = a.id
          $where
      ORDER BY a.timecreated ASC, a.id ASC";
$applications = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/shomokh_admissions/manage.php', ['status' => $status]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('admindashboard', 'local_shomokh_admissions'));
$PAGE->set_heading(get_string('admindashboard', 'local_shomokh_admissions'));

echo $OUTPUT->header();
$today = usergetmidnight(time());
$metrics = [
    get_string('dashboardpending', 'local_shomokh_admissions') => $DB->count_records(
        'local_shadm_application',
        ['status' => \local_shomokh_admissions\application_service::STATUS_PENDING]
    ),
    get_string('dashboardfailed', 'local_shomokh_admissions') => $DB->count_records(
        'local_shadm_application',
        ['status' => \local_shomokh_admissions\application_service::STATUS_NEEDS_ATTENTION]
    ),
    get_string('dashboardtoday', 'local_shomokh_admissions') => $DB->count_records_select(
        'local_shadm_application',
        'timecreated >= :today',
        ['today' => $today]
    ),
    get_string('dashboardtotal', 'local_shomokh_admissions') => $DB->count_records('local_shadm_application'),
];
echo html_writer::start_tag('div', ['class' => 'local-shadm-card']);
echo html_writer::start_tag('div', ['class' => 'local-shadm-grid']);
foreach ($metrics as $label => $value) {
    echo html_writer::tag('div', html_writer::tag('strong', (string)$value) . s($label), [
        'class' => 'local-shadm-metric',
    ]);
}
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', ['class' => 'local-shadm-actions mt-3']);
if ($recognitionprograms) {
    $syncbutton = new single_button(new moodle_url('/local/shomokh_admissions/manage.php', [
        'action' => 'sync',
    ]), get_string('runsync', 'local_shomokh_admissions'), 'post', single_button::BUTTON_SECONDARY);
    $syncbutton->add_confirm_action(get_string('syncconfirm', 'local_shomokh_admissions'));
    echo $OUTPUT->render($syncbutton);
} else {
    echo html_writer::tag('button', get_string('runsync', 'local_shomokh_admissions'), [
        'class' => 'btn btn-secondary',
        'type' => 'button',
        'disabled' => 'disabled',
        'title' => get_string('syncunavailable', 'local_shomokh_admissions'),
    ]);
}
echo html_writer::link(
    new moodle_url('/local/shomokh_admissions/programs.php'),
    get_string('programs', 'local_shomokh_admissions'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::link(
    new moodle_url('/local/shomokh_admissions/config.php'),
    get_string('generalsettings', 'local_shomokh_admissions'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::start_tag('section', ['class' => 'local-shadm-card']);
echo html_writer::tag('h2', get_string('reviewqueue', 'local_shomokh_admissions'));
$filteroptions = ['all' => get_string('allstatuses', 'local_shomokh_admissions')];
foreach ($validstatuses as $optionstatus) {
    $filteroptions[$optionstatus] = \local_shomokh_admissions\application_service::status_label($optionstatus);
}
$select = new single_select(
    new moodle_url('/local/shomokh_admissions/manage.php'),
    'status',
    $filteroptions,
    $status,
    null,
    'admission-status-filter'
);
$select->set_label(get_string('filterstatus', 'local_shomokh_admissions'));
echo $OUTPUT->render($select);

if (!$applications) {
    echo $OUTPUT->notification(get_string('noapplications', 'local_shomokh_admissions'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('student', 'local_shomokh_admissions'),
        get_string('program', 'local_shomokh_admissions'),
        get_string('source', 'local_shomokh_admissions'),
        get_string('status', 'local_shomokh_admissions'),
        get_string('submittedat', 'local_shomokh_admissions'),
        get_string('actions', 'local_shomokh_admissions'),
    ];
    foreach ($applications as $application) {
        $student = (object)[
            'id' => (int)$application->userid,
            'firstname' => $application->firstname,
            'lastname' => $application->lastname,
            'firstnamephonetic' => $application->firstnamephonetic,
            'lastnamephonetic' => $application->lastnamephonetic,
            'middlename' => $application->middlename,
            'alternatename' => $application->alternatename,
        ];
        $studentname = fullname($student);
        $actions = '';
        if (
            $application->status === \local_shomokh_admissions\application_service::STATUS_PENDING
                && has_capability('local/shomokh_admissions:review', $context)
        ) {
            $actions = html_writer::link(new moodle_url('/local/shomokh_admissions/review.php', [
                'id' => $application->applicationid,
            ]), get_string('review', 'local_shomokh_admissions'), ['class' => 'btn btn-sm btn-primary']);
        }
        if (
            $application->status === \local_shomokh_admissions\application_service::STATUS_NEEDS_ATTENTION
                && !empty($application->operationid)
        ) {
            $actions .= $OUTPUT->single_button(new moodle_url('/local/shomokh_admissions/manage.php', [
                'action' => 'retry',
                'operationid' => $application->operationid,
            ]), get_string('retryoperation', 'local_shomokh_admissions'), 'post', [
                'class' => 'btn btn-sm btn-warning',
            ]);
        }
        $sourcekey = 'source:' . $application->eligibilitysource;
        $table->data[] = [
            s($studentname),
            format_string($application->programname),
            get_string($sourcekey, 'local_shomokh_admissions'),
            \local_shomokh_admissions\application_service::status_label($application->status),
            userdate($application->timecreated),
            $actions,
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
}
echo html_writer::end_tag('section');
echo $OUTPUT->footer();
