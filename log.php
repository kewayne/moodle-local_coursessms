<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Log Page for viewing and deleting SMS history with manual status refresh.
 *
 * @package   local_coursessms
 * @copyright 2025 Kewayne Davidson <admin.kewayne.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$logid = optional_param('logid', 0, PARAM_INT);
$notify = optional_param('notify', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$clearall = optional_param('clearall', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/coursessms:viewlog', $context);

// Process log deletion.
if ($action === 'delete' && confirm_sesskey()) {
    require_capability('local/coursessms:deletelog', $context);

    if ($logid) {
        $DB->delete_records('local_coursessms_log', ['id' => $logid, 'courseid' => $courseid]);
        redirect(
            new moodle_url('/local/coursessms/log.php', ['id' => $courseid]),
            get_string('log_deleted', 'local_coursessms'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else if ($clearall) {
        $DB->delete_records('local_coursessms_log', ['courseid' => $courseid]);
        redirect(
            new moodle_url('/local/coursessms/log.php', ['id' => $courseid]),
            get_string('all_logs_deleted', 'local_coursessms'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$PAGE->set_url('/local/coursessms/log.php', ['id' => $courseid]);
$PAGE->set_title(get_string('smslog_page_title', 'local_coursessms'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->navbar->add(get_string('pluginname', 'local_coursessms'));

if ($notify) {
    \core\notification::success(get_string('messages_queued', 'local_coursessms'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('smslog_page_title', 'local_coursessms'));

$tabrows = [];
$row = [];

if (has_capability('local/coursessms:sendsms', $context)) {
    $row[] = new tabobject(
        'sendsms',
        new moodle_url('/local/coursessms/index.php', ['id' => $courseid]),
        get_string('sendsms', 'local_coursessms')
    );
}

$row[] = new tabobject(
    'smslog',
    new moodle_url('/local/coursessms/log.php', ['id' => $courseid]),
    get_string('smslog', 'local_coursessms')
);

$tabrows[] = $row;
print_tabs($tabrows, 'smslog');

$canDelete = has_capability('local/coursessms:deletelog', $context);

if ($logid) {
    $log = $DB->get_record('local_coursessms_log', ['id' => $logid, 'courseid' => $courseid], '*', MUST_EXIST);
    $sender = $DB->get_record('user', ['id' => $log->senderid]);

    echo $OUTPUT->box_start('generalbox');
    echo html_writer::tag('h4', get_string('log_details_title', 'local_coursessms'));
    echo html_writer::tag('p', get_string('log_sent_by', 'local_coursessms') . ': ' . fullname($sender));

    $gwName = !empty($log->gatewayname) ? $log->gatewayname : get_string('system_default_gateway', 'local_coursessms');
    echo html_writer::tag('p', get_string('log_gateway_used', 'local_coursessms') . ': ' . htmlspecialchars($gwName));

    echo html_writer::tag('p', get_string('log_sent_on', 'local_coursessms') . ': ' . userdate($log->timecreated));
    echo html_writer::tag('p',
        get_string('log_message', 'local_coursessms') . ': ' .
        format_text($log->messagecontent, FORMAT_PLAIN)
    );

    $targetlabel = '';
    switch ($log->targettype) {
        case 'role':
            $role = $DB->get_record('role', ['id' => $log->targetid], '*', IGNORE_MISSING);
            $rolename = $role ? role_get_name($role, $context) : 'Unknown Role';
            $targetlabel = get_string('target_role', 'local_coursessms') . ': ' . $rolename;
            break;
        case 'group':
            $group = $DB->get_record('groups', ['id' => $log->targetid], '*', IGNORE_MISSING);
            $targetlabel = get_string('target_group', 'local_coursessms') . ': ' .
                ($group->name ?? get_string('unknown_group', 'local_coursessms'));
            break;
        case 'all':
        default:
            $targetlabel = get_string('target_all', 'local_coursessms');
            break;
    }

    echo html_writer::tag('p', get_string('log_target', 'local_coursessms') . ': ' . $targetlabel);

    $msgMap = json_decode($log->messageids ?? '', true) ?? [];
    $rawSuccessids = json_decode($log->success_userids ?? '', true) ?? [];
    $rawFailedids = json_decode($log->failed_userids ?? '', true) ?? [];

    $queuedUsers = [];
    $sentUsers = [];
    $failedUsers = [];

    // Classify user IDs by real-time delivery status from core_sms table.
    if (!empty($msgMap)) {
        foreach ($msgMap as $uid => $msgId) {
            $msgRec = $DB->get_record('sms_messages', ['id' => $msgId]);
            if ($msgRec) {
                if ($msgRec->status === 'gateway_queued' || $msgRec->status === 'queued') {
                    $queuedUsers[] = (int)$uid;
                } else if ($msgRec->status === 'gateway_sent' || $msgRec->status === 'sent') {
                    $sentUsers[] = (int)$uid;
                } else {
                    $failedUsers[] = (int)$uid;
                }
            } else {
                $sentUsers[] = (int)$uid;
            }
        }
    } else {
        $sentUsers = $rawSuccessids;
    }

    foreach ($rawFailedids as $fid) {
        if (!in_array((int)$fid, $failedUsers, true)) {
            $failedUsers[] = (int)$fid;
        }
    }

    $summaryText = count($sentUsers) . ' sent, ' . count($queuedUsers) . ' queued, ' . count($failedUsers) . ' failed';
    echo html_writer::tag('p', get_string('log_recipients', 'local_coursessms') . ': ' . $summaryText);

    echo html_writer::start_tag('details', ['open' => true]);
    echo html_writer::tag('summary', get_string('log_recipient_details', 'local_coursessms'));

    // 1. Queued Sends Section (if any).
    if (!empty($queuedUsers)) {
        echo html_writer::tag('h5', get_string('log_queued_sends', 'local_coursessms') . ' (' . count($queuedUsers) . ')', ['class' => 'mt-3 text-info']);
        $table = new html_table();
        $table->head = ['Name', 'Phone', 'Status'];
        foreach ($queuedUsers as $userid) {
            $user = $DB->get_record('user', ['id' => $userid]);
            if ($user) {
                $p1 = trim((string)($user->phone1 ?? ''));
                $p2 = trim((string)($user->phone2 ?? ''));
                $phone = !empty($p1) ? $p1 : (!empty($p2) ? $p2 : '-');
                $table->data[] = [fullname($user), $phone, get_string('status_queued', 'local_coursessms')];
            }
        }
        echo html_writer::table($table);
    }

    // 2. Successful Sends Section.
    echo html_writer::tag('h5', get_string('log_successful_sends', 'local_coursessms') . ' (' . count($sentUsers) . ')', ['class' => 'mt-3 text-success']);
    if (!empty($sentUsers)) {
        $table = new html_table();
        $table->head = ['Name', 'Phone', 'Status'];
        foreach ($sentUsers as $userid) {
            $user = $DB->get_record('user', ['id' => $userid]);
            if ($user) {
                $p1 = trim((string)($user->phone1 ?? ''));
                $p2 = trim((string)($user->phone2 ?? ''));
                $phone = !empty($p1) ? $p1 : (!empty($p2) ? $p2 : '-');
                $table->data[] = [fullname($user), $phone, get_string('status_sent', 'local_coursessms')];
            }
        }
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('log_none', 'local_coursessms'));
    }

    // 3. Failed Sends Section.
    echo html_writer::tag('h5', get_string('log_failed_sends', 'local_coursessms') . ' (' . count($failedUsers) . ')', ['class' => 'mt-3 text-danger']);
    if (!empty($failedUsers)) {
        $table = new html_table();
        $table->head = ['Name', 'Phone', 'Reason'];
        foreach ($failedUsers as $userid) {
            $user = $DB->get_record('user', ['id' => $userid]);
            if ($user) {
                $p1 = trim((string)($user->phone1 ?? ''));
                $p2 = trim((string)($user->phone2 ?? ''));
                $phone = !empty($p1) ? $p1 : (!empty($p2) ? $p2 : '');
                $phonedisplay = !empty($phone) ? $phone : '-';
                $reason = empty($phone) ? get_string('reason_no_phone', 'local_coursessms') : get_string('reason_gateway_error', 'local_coursessms');
                $table->data[] = [fullname($user), $phonedisplay, $reason];
            }
        }
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('log_none', 'local_coursessms'));
    }

    echo html_writer::end_tag('details');

    echo html_writer::start_div('mt-3 d-flex gap-2');
    $backurl = new moodle_url('/local/coursessms/log.php', ['id' => $courseid]);
    echo $OUTPUT->single_button($backurl, get_string('back_to_log', 'local_coursessms'), 'get');

    $refreshurl = new moodle_url('/local/coursessms/log.php', ['id' => $courseid, 'logid' => $log->id]);
    echo $OUTPUT->single_button($refreshurl, get_string('refresh_status', 'local_coursessms'), 'get', ['class' => 'btn-secondary']);

    if ($canDelete) {
        $deleteurl = new moodle_url('/local/coursessms/log.php', [
            'id' => $courseid,
            'logid' => $log->id,
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->single_button(
            $deleteurl,
            get_string('delete_log', 'local_coursessms'),
            'post',
            ['class' => 'btn-danger', 'confirm' => get_string('confirm_delete_log', 'local_coursessms')]
        );
    }
    echo html_writer::end_div();

    echo $OUTPUT->box_end();

} else {
    $logs = $DB->get_records('local_coursessms_log', ['courseid' => $courseid], 'timecreated DESC');

    if (empty($logs)) {
        echo $OUTPUT->notification(get_string('log_no_logs', 'local_coursessms'));
    } else {
        if ($canDelete) {
            echo html_writer::start_div('mb-3 text-end');
            $clearallurl = new moodle_url('/local/coursessms/log.php', [
                'id' => $courseid,
                'action' => 'delete',
                'clearall' => 1,
                'sesskey' => sesskey(),
            ]);
            echo $OUTPUT->single_button(
                $clearallurl,
                get_string('delete_all_logs', 'local_coursessms'),
                'post',
                ['class' => 'btn-outline-danger btn-sm', 'confirm' => get_string('confirm_delete_all_logs', 'local_coursessms')]
            );
            echo html_writer::end_div();
        }

        $table = new html_table();
        $table->head = [
            get_string('log_sent_on', 'local_coursessms'),
            get_string('log_sent_by', 'local_coursessms'),
            get_string('log_gateway_used', 'local_coursessms'),
            get_string('log_message', 'local_coursessms'),
            get_string('log_recipients', 'local_coursessms'),
            '',
        ];

        foreach ($logs as $log) {
            $sender = $DB->get_record('user', ['id' => $log->senderid]);
            $preview = mb_strimwidth($log->messagecontent, 0, 50, '...');
            $successcount = count(json_decode($log->success_userids ?? '', true) ?? []);
            $failedcount = count(json_decode($log->failed_userids ?? '', true) ?? []);
            $recipientinfo = "Sent: {$successcount}, Failed: {$failedcount}";

            $gwName = !empty($log->gatewayname) ? $log->gatewayname : get_string('system_default_gateway', 'local_coursessms');

            $url = new moodle_url('/local/coursessms/log.php', ['id' => $courseid, 'logid' => $log->id]);

            $actionsHtml = $OUTPUT->action_link($url, get_string('log_view_details', 'local_coursessms'));

            if ($canDelete) {
                $delUrl = new moodle_url('/local/coursessms/log.php', [
                    'id' => $courseid,
                    'logid' => $log->id,
                    'action' => 'delete',
                    'sesskey' => sesskey(),
                ]);
                $actionsHtml .= ' | ' . $OUTPUT->action_link(
                    $delUrl,
                    get_string('action_delete', 'local_coursessms'),
                    new confirm_action(get_string('confirm_delete_log', 'local_coursessms'))
                );
            }

            $row = new html_table_row();
            $row->cells[] = userdate($log->timecreated);
            $row->cells[] = fullname($sender);
            $row->cells[] = htmlspecialchars($gwName);
            $row->cells[] = $preview;
            $row->cells[] = $recipientinfo;
            $row->cells[] = $actionsHtml;

            $table->data[] = $row;
        }

        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
