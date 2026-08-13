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
 * Handles the form submission for sending SMS messages in the background.
 *
 * @package   local_coursessms
 * @copyright 2025 Kewayne Davidson <admin.kewayne.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once(__DIR__ . '/classes/form/send_form.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/coursessms:sendsms', $context);

$form = new \local_coursessms\form\send_form(
    new moodle_url('/local/coursessms/send_action.php'),
    ['courseid' => $courseid]
);

// Redirect if cancelled.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$data = $form->get_data();

if (!$data) {
    redirect(new moodle_url('/local/coursessms/index.php', ['id' => $courseid]));
}

$users = [];
$targetid = 0;

switch ($data->targettype) {
    case 'role':
        $targetid = $data->roleid;
        $users = get_role_users($data->roleid, $context, false, 'u.id, u.firstname, u.lastname, u.phone1, u.phone2');
        break;

    case 'group':
        $targetid = $data->groupid;
        $groupmembers = groups_get_members($data->groupid);
        $userids = array_keys($groupmembers);

        if (!empty($userids)) {
            list($insql, $params) = $DB->get_in_or_equal($userids);
            $params[] = $courseid;

            $users = $DB->get_records_sql(
                "SELECT u.id, u.firstname, u.lastname, u.phone1, u.phone2
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE u.id $insql AND e.courseid = ?",
                $params
            );
        }
        break;

    case 'all':
    default:
        $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.phone1, u.phone2');
        break;
}

$smsmanager = \core\di::get(\core_sms\manager::class);
$queueduserids = [];
$failedsends = [];
$messageidsMap = [];

$sendername = fullname($USER);
$courseshortname = $course->shortname;
$gatewayid = !empty($data->gatewayid) ? (int)$data->gatewayid : 0;

// Resolve gateway display name at time of sending (preserves history if gateway is deleted later).
$gatewayname = get_string('system_default_gateway', 'local_coursessms');
if ($gatewayid > 0) {
    $gwRecord = $DB->get_record('sms_gateways', ['id' => $gatewayid]);
    if ($gwRecord) {
        $gatewayname = $gwRecord->name;
    }
}

foreach ($users as $user) {
    $rawphone1 = trim((string)($user->phone1 ?? ''));
    $rawphone2 = trim((string)($user->phone2 ?? ''));
    $phonenumber = !empty($rawphone1) ? $rawphone1 : (!empty($rawphone2) ? $rawphone2 : '');

    if (empty($phonenumber)) {
        $failedsends[] = (int)$user->id;
        continue;
    }

    $personalizedmessage = str_replace(
        ['{sender}', '{coursename}', '{firstname}', '{lastname}'],
        [$sendername, $courseshortname, $user->firstname, $user->lastname],
        $data->messagecontent
    );

    try {
        $sendParams = [
            'recipientnumber' => $phonenumber,
            'content' => $personalizedmessage,
            'component' => 'local_coursessms',
            'messagetype' => 'coursemessage',
            'recipientuserid' => $user->id,
            'issensitive' => false,
            'async' => true, // Hand off to background queue for instant form redirection!
        ];

        if ($gatewayid > 0) {
            $sendParams['gatewayid'] = $gatewayid;
        }

        $queuedMessage = $smsmanager->send(...$sendParams);
        if ($queuedMessage->id) {
            $queueduserids[] = (int)$user->id;
            $messageidsMap[(int)$user->id] = (int)$queuedMessage->id;
        } else {
            $failedsends[] = (int)$user->id;
        }
    } catch (\Exception $e) {
        $failedsends[] = (int)$user->id;
    }
}

// Log the operation with gateway name preserved.
$logrecord = (object)[
    'courseid' => $courseid,
    'senderid' => $USER->id,
    'messagecontent' => $data->messagecontent,
    'targettype' => $data->targettype,
    'targetid' => $targetid,
    'gatewayname' => $gatewayname,
    'messageids' => json_encode($messageidsMap),
    'success_userids' => json_encode($queueduserids),
    'failed_userids' => json_encode($failedsends),
    'timecreated' => time(),
];

$logid = $DB->insert_record('local_coursessms_log', $logrecord);

redirect(new moodle_url('/local/coursessms/log.php', [
    'id' => $courseid,
    'logid' => $logid,
    'notify' => 1,
]));
